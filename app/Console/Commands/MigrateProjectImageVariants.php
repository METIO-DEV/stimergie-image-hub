<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Support\ImageVariantGenerator;
use App\Support\ObjectStoragePolicy;
use App\Support\ProjectImageVariantConvention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

#[Signature('images:migrate-project-variants
    {--project= : ID du projet a migrer}
    {--folder= : Dossier source projet a migrer}
    {--limit= : Limite le nombre d images analysees}
    {--execute : Applique les copies et mises a jour DB. Dry-run par defaut}')]
#[Description('Prepare les dossiers projet puis deplace les variantes legacy web et HD vers web/ et hd/')]
class MigrateProjectImageVariants extends Command
{
    public function handle(
        ProjectImageVariantConvention $convention,
        ObjectStoragePolicy $storagePolicy,
    ): int {
        $execute = (bool) $this->option('execute');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $totals = [
            'checked' => 0,
            'skipped' => 0,
            'copied' => 0,
            'db_updated' => 0,
            'missing' => 0,
            'conflicts' => 0,
            'errors' => 0,
        ];

        if (! $execute) {
            $this->warn('Dry-run: aucune copie objet ni mise a jour DB. Ajoutez --execute pour appliquer.');
        }

        $query = Image::query()
            ->with(['project', 'variants'])
            ->when($this->option('project'), fn ($query) => $query->where('project_id', (int) $this->option('project')))
            ->when($this->option('folder'), fn ($query) => $this->constrainFolder($query, (string) $this->option('folder')))
            ->orderBy('id');

        $preparedProjects = [];

        $query->lazyById()->each(function (Image $image) use ($convention, $execute, $storagePolicy, $limit, &$totals, &$preparedProjects): bool {
            if ($limit !== null && $totals['checked'] >= $limit) {
                return false;
            }

            $totals['checked']++;

            if (! $image->project) {
                $totals['skipped']++;
                $this->line("[skipped] image {$image->id}: aucun projet associe");

                return true;
            }

            try {
                if (! isset($preparedProjects[$image->project->id])) {
                    $projectPrefix = $convention->projectPrefixForImage($image);
                    $this->ensureProjectVariantFolders(
                        $projectPrefix,
                        $convention->disk($image->storage_provider),
                        $storagePolicy,
                        $execute,
                    );
                    $preparedProjects[$image->project->id] = true;
                }

                foreach (['web', 'hd'] as $kind) {
                    $result = $this->migrateVariant($image, $kind, $convention, $storagePolicy, $execute);
                    if ($result['status'] !== 'processed') {
                        $totals[$result['status']]++;
                    }

                    if ($result['copied']) {
                        $totals['copied']++;
                    }

                    if ($result['db_updated']) {
                        $totals['db_updated']++;
                    }
                }
            } catch (Throwable $exception) {
                $totals['errors']++;
                $this->error("[error] image {$image->id}: {$exception->getMessage()}");
            }

            return true;
        });

        $this->newLine();
        $this->components->twoColumnDetail('Images analysees', (string) $totals['checked']);
        $this->components->twoColumnDetail('Variantes ignorees', (string) $totals['skipped']);
        $this->components->twoColumnDetail($execute ? 'Objets deplaces' : 'Objets a deplacer', (string) $totals['copied']);
        $this->components->twoColumnDetail($execute ? 'References DB mises a jour' : 'References DB a mettre a jour', (string) $totals['db_updated']);
        $this->components->twoColumnDetail('Sources manquantes', (string) $totals['missing']);
        $this->components->twoColumnDetail('Conflits', (string) $totals['conflicts']);
        $this->components->twoColumnDetail('Erreurs', (string) $totals['errors']);

        return ($totals['errors'] > 0 || $totals['conflicts'] > 0) ? self::FAILURE : self::SUCCESS;
    }

    private function constrainFolder($query, string $folder): void
    {
        $folder = trim($folder, '/');
        $folderPrefix = 'photos/'.$folder;

        $query->where(function ($query) use ($folder, $folderPrefix): void {
            $query
                ->where('object_key_original', 'like', "{$folderPrefix}/%")
                ->orWhereHas('project', function ($query) use ($folder): void {
                    $query
                        ->where('source_folder', $folder)
                        ->orWhere('name', $folder)
                        ->orWhere('slug', $folder);
                });
        });
    }

    /**
     * @return array{status: 'processed'|'skipped'|'missing'|'conflicts', copied: bool, db_updated: bool}
     */
    private function migrateVariant(
        Image $image,
        string $kind,
        ProjectImageVariantConvention $convention,
        ObjectStoragePolicy $storagePolicy,
        bool $execute,
    ): array {
        $currentKey = match ($kind) {
            'thumb' => $image->object_key_thumb,
            'hd' => $image->object_key_hd,
            default => $image->object_key_web,
        };
        $isConforming = match ($kind) {
            'thumb' => $convention->isConformingThumbnailKey($image, $currentKey),
            'hd' => $convention->isConformingHdKey($image, $currentKey),
            default => $convention->isConformingWebKey($image, $currentKey),
        };
        $diskName = $convention->disk($image->storage_provider);
        $disk = Storage::disk($diskName);

        if ($isConforming && $currentKey && $disk->exists($currentKey)) {
            $this->line("[skipped] image {$image->id} {$kind}: deja conforme {$currentKey}");

            return ['status' => 'skipped', 'copied' => false, 'db_updated' => false];
        }

        $sourceKey = $this->sourceKey($image, $kind, $convention, $disk);

        if (! $sourceKey) {
            $this->warn("[missing] image {$image->id} {$kind}: aucune source legacy disponible");

            return ['status' => 'missing', 'copied' => false, 'db_updated' => false];
        }

        $targetKey = $convention->targetKey($image, $kind, $sourceKey);

        if ($this->targetUsedByAnotherImage($image, $targetKey)) {
            $this->warn("[conflict] image {$image->id} {$kind}: cible deja referencee {$targetKey}");

            return ['status' => 'conflicts', 'copied' => false, 'db_updated' => false];
        }

        $targetExists = $disk->exists($targetKey);
        $copied = false;

        if ($sourceKey !== $targetKey && ! $targetExists) {
            if ($execute) {
                $this->copyObject($diskName, $sourceKey, $targetKey, $storagePolicy);
                $this->info("[moved] image {$image->id} {$kind}: {$sourceKey} -> {$targetKey}");
            } else {
                $this->line("[dry-run] image {$image->id} {$kind}: deplacer {$sourceKey} -> {$targetKey}");
            }

            $copied = true;
        } elseif ($sourceKey !== $targetKey && $targetExists) {
            $this->line(($execute ? '[skipped]' : '[dry-run]')." image {$image->id} {$kind}: cible deja presente {$targetKey}");
        }

        $needsDbUpdate = $currentKey !== $targetKey || ! $this->variantRowMatches($image, $kind, $targetKey);

        if ($needsDbUpdate) {
            if ($execute) {
                DB::transaction(function () use ($image, $kind, $sourceKey, $targetKey): void {
                    if ($kind === 'hd') {
                        $image->forceFill([
                            'object_key_original' => $targetKey,
                            'object_key_hd' => $targetKey,
                        ])->save();

                        foreach (['original', 'hd'] as $variantKind) {
                            $image->variants()->updateOrCreate(
                                ['kind' => $variantKind],
                                [
                                    'object_key' => $targetKey,
                                    'mime_type' => $image->mime_type,
                                    'width' => $image->width,
                                    'height' => $image->height,
                                    'size_bytes' => $image->size_bytes,
                                ],
                            );
                        }

                        return;
                    }

                    $thumbReferencesWebSource = $image->object_key_thumb === $sourceKey;
                    $updates = ['object_key_web' => $targetKey];

                    if ($thumbReferencesWebSource) {
                        $updates['object_key_thumb'] = null;
                    }

                    $image->forceFill($updates)->save();
                    $image->variants()->updateOrCreate(
                        ['kind' => $kind],
                        [
                            'object_key' => $targetKey,
                            'mime_type' => $image->mime_type,
                            'width' => $image->width,
                            'height' => $image->height,
                            'size_bytes' => null,
                        ],
                    );

                    if ($thumbReferencesWebSource) {
                        $image->variants()->where('kind', 'thumb')->delete();
                    }
                });
                $this->info("[db-updated] image {$image->id} {$kind}: {$targetKey}");
                $this->deleteSourceIfUnreferenced($diskName, $sourceKey, $targetKey);
            } else {
                $this->line("[dry-run] image {$image->id} {$kind}: maj DB vers {$targetKey}");
            }

            return ['status' => 'processed', 'copied' => $copied, 'db_updated' => true];
        }

        return ['status' => $copied ? 'processed' : 'skipped', 'copied' => $copied, 'db_updated' => false];
    }

    private function sourceKey(Image $image, string $kind, ProjectImageVariantConvention $convention, $disk): ?string
    {
        foreach ($this->sourceCandidates($image, $kind, $convention) as $candidate) {
            if (
                $candidate !== null
                && $candidate !== ''
                && $this->isImageObject($candidate)
                && $this->isAcceptableSource($image, $kind, $candidate)
                && $disk->exists($candidate)
            ) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, string|null>
     */
    private function sourceCandidates(Image $image, string $kind, ProjectImageVariantConvention $convention): array
    {
        $variantKey = $image->variants->firstWhere('kind', $kind)?->object_key;
        $currentKey = $kind === 'hd' ? $image->object_key_hd : $image->object_key_web;
        $basename = basename((string) ($currentKey ?: $image->object_key_original ?: $image->object_key_hd));
        $extension = $this->extensionFromImage($image, $currentKey ?: $image->object_key_original ?: $image->object_key_hd);
        $projectPrefix = $image->project ? $convention->projectPrefix($image->project) : null;
        $candidates = [$currentKey, $variantKey];

        if ($kind === 'web') {
            $candidates[] = $image->object_key_original && $this->isLegacyWebFolder($image->object_key_original)
                ? $image->object_key_original
                : null;
            $candidates[] = $projectPrefix ? "{$projectPrefix}/JPG/{$basename}" : null;
            $candidates[] = $image->object_key_original
                ? trim(dirname($image->object_key_original), '/')."/JPG/{$basename}"
                : null;
            $candidates[] = "images/JPG/{$basename}";
            $candidates[] = "images/web/{$basename}";
            $candidates[] = "images/JPG/{$image->id}.{$extension}";
            $candidates[] = "images/web/{$image->id}.{$extension}";
        } elseif ($kind === 'hd') {
            $candidates[] = $image->object_key_original;
            $candidates[] = $this->withoutLegacyJpgSegment($image->object_key_original);
            $candidates[] = $this->withoutLegacyJpgSegment($image->object_key_web);
            $candidates[] = $projectPrefix ? "{$projectPrefix}/{$basename}" : null;
        }

        return collect($candidates)
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function isImageObject(string $key): bool
    {
        if ($key === '' || str_ends_with($key, '/') || str_starts_with(basename($key), '.')) {
            return false;
        }

        return in_array(Str::lower(pathinfo($key, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    private function isAcceptableSource(Image $image, string $kind, string $candidate): bool
    {
        if ($kind === 'web') {
            return $this->isLegacyWebFolder($candidate)
                || ! in_array($candidate, array_filter([$image->object_key_original, $image->object_key_hd]), true);
        }

        if ($kind === 'hd') {
            return ! $this->isWebOrThumbnailObject($candidate);
        }

        return false;
    }

    private function isLegacyWebFolder(string $objectKey): bool
    {
        $folder = '/'.Str::lower(trim(dirname($objectKey), '/')).'/';

        return str_contains($folder, '/jpg/')
            || str_contains($folder, '/images/jpg/')
            || str_contains($folder, '/images/web/');
    }

    private function targetUsedByAnotherImage(Image $image, string $targetKey): bool
    {
        return Image::query()
            ->whereKeyNot($image->id)
            ->where(function ($query) use ($targetKey): void {
                $query
                    ->where('object_key_original', $targetKey)
                    ->orWhere('object_key_web', $targetKey)
                    ->orWhere('object_key_thumb', $targetKey)
                    ->orWhere('object_key_hd', $targetKey)
                    ->orWhereHas('variants', fn ($query) => $query->where('object_key', $targetKey));
            })
            ->exists();
    }

    private function withoutLegacyJpgSegment(?string $objectKey): ?string
    {
        if (! is_string($objectKey) || $objectKey === '') {
            return null;
        }

        return str_replace('/JPG/', '/', $objectKey);
    }

    private function isWebOrThumbnailObject(string $objectKey): bool
    {
        $folder = '/'.Str::lower(trim(dirname($objectKey), '/')).'/';

        return str_contains($folder, '/jpg/')
            || str_contains($folder, '/web/')
            || str_contains($folder, '/miniatures/')
            || str_contains($folder, '/thumbs/')
            || str_contains($folder, '/images/jpg/')
            || str_contains($folder, '/images/web/')
            || str_contains($folder, '/images/thumbs/');
    }

    private function ensureProjectVariantFolders(
        string $projectPrefix,
        string $diskName,
        ObjectStoragePolicy $storagePolicy,
        bool $execute,
    ): void {
        $projectPrefix = trim($projectPrefix, '/');
        $folders = [
            $projectPrefix.'/'.ImageVariantGenerator::HD_VARIANT_DIRECTORY,
            $projectPrefix.'/'.ImageVariantGenerator::WEB_VARIANT_DIRECTORY,
            $projectPrefix.'/'.ImageVariantGenerator::THUMBNAIL_VARIANT_DIRECTORY,
        ];

        foreach ($folders as $folder) {
            $markerKey = trim($folder, '/').'/.keep';

            if ($execute) {
                if (! Storage::disk($diskName)->exists($markerKey)) {
                    Storage::disk($diskName)->put($markerKey, '', $storagePolicy->putOptions($markerKey, 'text/plain'));
                    $this->line("[folder] {$folder}/");
                }
            } else {
                $this->line("[dry-run] creer dossier {$folder}/");
            }
        }
    }

    private function deleteSourceIfUnreferenced(string $diskName, string $sourceKey, string $targetKey): void
    {
        if ($sourceKey === $targetKey || $this->isReferencedObjectKey($sourceKey)) {
            return;
        }

        Storage::disk($diskName)->delete($sourceKey);
        $this->line("[deleted-source] {$sourceKey}");
    }

    private function isReferencedObjectKey(string $objectKey): bool
    {
        return Image::query()
            ->where(function ($query) use ($objectKey): void {
                $query
                    ->where('object_key_original', $objectKey)
                    ->orWhere('object_key_web', $objectKey)
                    ->orWhere('object_key_thumb', $objectKey)
                    ->orWhere('object_key_hd', $objectKey)
                    ->orWhereHas('variants', fn ($query) => $query->where('object_key', $objectKey));
            })
            ->exists();
    }

    private function extensionFromImage(Image $image, ?string $objectKey): string
    {
        $extension = Str::lower(pathinfo((string) $objectKey, PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $extension;
        }

        return match ($image->mime_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function variantRowMatches(Image $image, string $kind, string $targetKey): bool
    {
        return $image->variants->firstWhere('kind', $kind)?->object_key === $targetKey;
    }

    private function copyObject(
        string $diskName,
        string $sourceKey,
        string $targetKey,
        ObjectStoragePolicy $storagePolicy,
    ): void {
        $disk = Storage::disk($diskName);
        $stream = $disk->readStream($sourceKey);

        if ($stream === false) {
            throw new \RuntimeException("Impossible de lire {$sourceKey}.");
        }

        try {
            $disk->put($targetKey, $stream, $storagePolicy->putOptions($targetKey, $this->mimeType($targetKey)));
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function mimeType(string $objectKey): ?string
    {
        return match (Str::lower(pathinfo($objectKey, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
    }
}

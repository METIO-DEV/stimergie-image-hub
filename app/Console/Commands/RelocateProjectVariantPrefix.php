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

#[Signature('images:relocate-project-variant-prefix
    {--project= : ID du projet a reparer}
    {--target-prefix= : Prefixe cible existant, ex: photos/CLIENT_PROJET}
    {--execute : Applique les copies, suppressions et mises a jour DB. Dry-run par defaut}')]
#[Description('Repositionne les variantes web/HD d un projet dans un autre prefixe projet')]
class RelocateProjectVariantPrefix extends Command
{
    public function handle(
        ProjectImageVariantConvention $convention,
        ObjectStoragePolicy $storagePolicy,
    ): int {
        $projectId = $this->option('project') ? (int) $this->option('project') : null;
        $targetPrefix = trim((string) $this->option('target-prefix'), '/');
        $execute = (bool) $this->option('execute');

        if (! $projectId || $targetPrefix === '' || ! Str::startsWith($targetPrefix, 'photos/')) {
            $this->error('Indiquez --project=<id> et --target-prefix=photos/<dossier>.');

            return self::FAILURE;
        }

        if (! $execute) {
            $this->warn('Dry-run: aucune copie objet, suppression ni mise a jour DB. Ajoutez --execute pour appliquer.');
        }

        $images = Image::query()
            ->with(['project', 'variants'])
            ->where('project_id', $projectId)
            ->orderBy('id')
            ->get();

        $totals = [
            'checked' => 0,
            'moved' => 0,
            'db_updated' => 0,
            'missing' => 0,
            'deleted_sources' => 0,
        ];
        $sourceRoots = [];

        foreach ($images as $image) {
            $totals['checked']++;
            $diskName = $convention->disk($image->storage_provider);
            $this->ensureProjectVariantFolders($diskName, $targetPrefix, $storagePolicy, $execute);

            foreach (['web', 'hd'] as $kind) {
                $sourceKey = $kind === 'hd' ? $image->object_key_hd : $image->object_key_web;

                if (! is_string($sourceKey) || $sourceKey === '') {
                    $totals['missing']++;
                    $this->warn("[missing] image {$image->id} {$kind}: aucune source DB.");

                    continue;
                }

                $sourceRoots[] = $convention->projectRootFromObjectKey($sourceKey);
                $targetKey = $targetPrefix.'/'.$kind.'/'.$image->id.'.'.$this->extension($sourceKey);

                if ($sourceKey === $targetKey) {
                    $this->line("[skipped] image {$image->id} {$kind}: deja dans {$targetKey}");

                    continue;
                }

                $disk = Storage::disk($diskName);

                if (! $disk->exists($sourceKey) && ! $disk->exists($targetKey)) {
                    $totals['missing']++;
                    $this->warn("[missing] image {$image->id} {$kind}: {$sourceKey}");

                    continue;
                }

                if ($execute && ! $disk->exists($targetKey)) {
                    $this->copyObject($diskName, $sourceKey, $targetKey, $storagePolicy);
                    $totals['moved']++;
                    $this->info("[moved] image {$image->id} {$kind}: {$sourceKey} -> {$targetKey}");
                } elseif (! $execute) {
                    $totals['moved']++;
                    $this->line("[dry-run] image {$image->id} {$kind}: deplacer {$sourceKey} -> {$targetKey}");
                }

                if ($execute) {
                    DB::transaction(function () use ($image, $kind, $targetKey): void {
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

                        $image->forceFill(['object_key_web' => $targetKey])->save();
                        $image->variants()->updateOrCreate(
                            ['kind' => 'web'],
                            [
                                'object_key' => $targetKey,
                                'mime_type' => $image->mime_type,
                                'width' => $image->width,
                                'height' => $image->height,
                                'size_bytes' => null,
                            ],
                        );
                    });
                    $totals['db_updated']++;

                    if ($sourceKey !== $targetKey && ! $this->isReferencedObjectKey($sourceKey)) {
                        $disk->delete($sourceKey);
                        $totals['deleted_sources']++;
                        $this->line("[deleted-source] {$sourceKey}");
                    }
                }
            }
        }

        if ($execute) {
            foreach (array_unique(array_filter($sourceRoots)) as $sourceRoot) {
                if ($sourceRoot !== $targetPrefix) {
                    $this->deleteFolderMarkers((string) $sourceRoot, $convention);
                }
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail('Images analysees', (string) $totals['checked']);
        $this->components->twoColumnDetail($execute ? 'Objets deplaces' : 'Objets a deplacer', (string) $totals['moved']);
        $this->components->twoColumnDetail($execute ? 'References DB mises a jour' : 'References DB a mettre a jour', (string) $totals['db_updated']);
        $this->components->twoColumnDetail('Sources manquantes', (string) $totals['missing']);
        $this->components->twoColumnDetail('Anciennes sources supprimees', (string) $totals['deleted_sources']);

        return $totals['missing'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function ensureProjectVariantFolders(
        string $diskName,
        string $targetPrefix,
        ObjectStoragePolicy $storagePolicy,
        bool $execute,
    ): void {
        foreach ([ImageVariantGenerator::HD_VARIANT_DIRECTORY, ImageVariantGenerator::WEB_VARIANT_DIRECTORY, ImageVariantGenerator::THUMBNAIL_VARIANT_DIRECTORY] as $directory) {
            $markerKey = "{$targetPrefix}/{$directory}/.keep";

            if ($execute) {
                if (! Storage::disk($diskName)->exists($markerKey)) {
                    Storage::disk($diskName)->put($markerKey, '', $storagePolicy->putOptions($markerKey, 'text/plain'));
                }
            } else {
                $this->line("[dry-run] creer dossier {$targetPrefix}/{$directory}/");
            }
        }
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

    private function deleteFolderMarkers(string $sourceRoot, ProjectImageVariantConvention $convention): void
    {
        $disk = Storage::disk($convention->disk(null));

        foreach ([ImageVariantGenerator::HD_VARIANT_DIRECTORY, ImageVariantGenerator::WEB_VARIANT_DIRECTORY, ImageVariantGenerator::THUMBNAIL_VARIANT_DIRECTORY] as $directory) {
            $disk->delete("{$sourceRoot}/{$directory}/.keep");
        }
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

    private function extension(string $objectKey): string
    {
        $extension = Str::lower(pathinfo($objectKey, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
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

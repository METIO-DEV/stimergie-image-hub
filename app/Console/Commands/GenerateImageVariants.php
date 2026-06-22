<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Support\ImageVariantGenerator;
use App\Support\ProjectImageStoragePath;
use App\Support\ProjectImageVariantConvention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('images:generate-variants
    {--source-prefix=photos : Prefixe des originaux legacy a traiter}
    {--target-prefix= : Prefixe de destination force. Par defaut, utilise le dossier du projet}
    {--project= : ID du projet a traiter}
    {--folder= : Dossier source a traiter}
    {--limit= : Limite le nombre d images traitees}
    {--dry-run : Affiche les images concernees sans generer les variantes}
    {--missing-web-only : Traite uniquement les images sans vraie variante web}
    {--force : Regenere les variantes meme si les cles web/thumb/hd sont deja renseignees}')]
#[Description('Genere les variantes web/thumb/hd depuis les originaux deja presents dans le bucket')]
class GenerateImageVariants extends Command
{
    public function handle(
        ImageVariantGenerator $variants,
        ProjectImageStoragePath $storagePath,
        ProjectImageVariantConvention $convention,
    ): int {
        $sourcePrefix = trim((string) $this->option('source-prefix'), '/');
        $targetPrefix = trim((string) $this->option('target-prefix'), '/');
        $projectId = $this->option('project') ? (int) $this->option('project') : null;
        $folder = trim((string) $this->option('folder'));
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');
        $missingWebOnly = (bool) $this->option('missing-web-only');
        $force = (bool) $this->option('force');

        $query = Image::query()
            ->with('project')
            ->whereNotNull('object_key_original')
            ->where('object_key_original', 'like', "{$sourcePrefix}/%")
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->when($folder !== '', fn ($query) => $this->constrainFolder($query, $sourcePrefix, $folder))
            ->when(! $force, fn ($query) => $this->constrainMissingProjectVariants($query, $missingWebOnly))
            ->orderBy('id');

        $checked = 0;
        $generated = 0;
        $alreadyConforming = 0;
        $missingOriginals = 0;
        $failed = 0;

        $query->lazyById()->each(function (Image $image) use (
            $variants,
            $storagePath,
            $targetPrefix,
            $dryRun,
            $force,
            $missingWebOnly,
            $convention,
            $limit,
            &$checked,
            &$generated,
            &$alreadyConforming,
            &$missingOriginals,
            &$failed,
        ): bool {
            if ($limit !== null && $checked >= $limit) {
                return false;
            }

            $checked++;
            $disk = match ($image->storage_provider) {
                'public' => 'public',
                'local' => 'local',
                default => 'scaleway',
            };

            $generationPlan = $this->generationPlan($image, $convention, $disk, $missingWebOnly, $force);

            if (! $generationPlan['web'] && ! $generationPlan['thumb'] && ! $generationPlan['hd']) {
                $alreadyConforming++;

                return true;
            }

            if (! $image->object_key_original || ! Storage::disk($disk)->exists($image->object_key_original)) {
                $missingOriginals++;
                $this->warn("Original absent: {$image->object_key_original} ({$image->title})");

                return true;
            }

            if ($dryRun) {
                $generated++;
                $plannedKinds = implode('+', array_keys(array_filter($generationPlan)));
                $this->line("[dry-run] {$image->id} {$plannedKinds} -> {$image->object_key_original} => ".$this->targetPrefixForImage($image, $storagePath, $targetPrefix));

                return true;
            }

            try {
                $fileData = $variants->generateFromOriginal(
                    $image,
                    $this->targetPrefixForImage($image, $storagePath, $targetPrefix),
                    $generationPlan['web'],
                    $generationPlan['thumb'],
                    $generationPlan['hd'],
                );

                $image->update([
                    'storage_provider' => $fileData['disk'],
                    'object_key_web' => $fileData['web'],
                    'object_key_thumb' => $fileData['thumb'] ?? null,
                    'object_key_original' => $fileData['original'],
                    'object_key_hd' => $fileData['hd'],
                    'width' => $image->width ?: $fileData['width'],
                    'height' => $image->height ?: $fileData['height'],
                    'orientation' => $image->orientation ?: $fileData['orientation'],
                    'mime_type' => $image->mime_type ?: $fileData['mime_type'],
                    'size_bytes' => $image->size_bytes ?: $fileData['size_bytes'],
                    'checksum' => $image->checksum ?: $fileData['checksum'],
                    'status' => 'ready',
                    'processed_at' => now(),
                    'processing_error' => null,
                ]);

                $variants->syncImageVariants($image, $fileData['variants']);
                $generated++;

                if ($generated % 50 === 0) {
                    $this->info("Variantes generees: {$generated}");
                }
            } catch (Throwable $exception) {
                $failed++;
                $image->update(['processing_error' => $exception->getMessage()]);
                $this->error("Echec image {$image->id}: {$exception->getMessage()}");
            }

            return true;
        });

        $this->newLine();
        $this->components->twoColumnDetail('Images verifiees', (string) $checked);
        $this->components->twoColumnDetail($dryRun ? 'Images a generer' : 'Images generees', (string) $generated);
        $this->components->twoColumnDetail('Images deja conformes', (string) $alreadyConforming);
        $this->components->twoColumnDetail('Originaux absents', (string) $missingOriginals);
        $this->components->twoColumnDetail('Echecs', (string) $failed);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function constrainFolder($query, string $sourcePrefix, string $folder): void
    {
        $folderPrefix = trim($sourcePrefix.'/'.$folder, '/');

        $query->where(function ($query) use ($folder, $folderPrefix): void {
            $query
                ->where('object_key_original', 'like', "{$folderPrefix}/%")
                ->orWhereHas('project', function ($query) use ($folder): void {
                    $query
                        ->where('source_folder', $folder)
                        ->orWhere('name', $folder);
                });
        });
    }

    private function constrainMissingProjectVariants($query, bool $missingWebOnly): void
    {
        $query->where(function ($query) use ($missingWebOnly): void {
            $query = $query
                ->whereNull('object_key_web')
                ->orWhereColumn('object_key_web', 'object_key_original')
                ->orWhereColumn('object_key_web', 'object_key_hd')
                ->orWhere('object_key_web', 'like', 'images/%')
                ->orWhere('object_key_web', 'like', '%/JPG/%')
                ->orWhere('object_key_web', 'not like', 'photos/%/web/%');

            if (! $missingWebOnly) {
                $query
                    ->orWhereNull('object_key_thumb')
                    ->orWhereColumn('object_key_thumb', 'object_key_web')
                    ->orWhereColumn('object_key_thumb', 'object_key_original')
                    ->orWhereColumn('object_key_thumb', 'object_key_hd')
                    ->orWhere('object_key_thumb', 'like', 'images/%')
                    ->orWhere('object_key_thumb', 'like', '%/JPG/%')
                    ->orWhere('object_key_thumb', 'not like', 'photos/%/miniatures/%')
                    ->orWhereNull('object_key_hd')
                    ->orWhere('object_key_hd', 'not like', 'photos/%/hd/%')
                    ->orWhereNull('object_key_original')
                    ->orWhere('object_key_original', 'not like', 'photos/%/hd/%');
            }
        });
    }

    /**
     * @return array{web: bool, thumb: bool, hd: bool}
     */
    private function generationPlan(
        Image $image,
        ProjectImageVariantConvention $convention,
        string $disk,
        bool $missingWebOnly,
        bool $force,
    ): array {
        $generateWeb = $force
            || ! $convention->isConformingWebKey($image, $image->object_key_web)
            || ($image->object_key_web && ! Storage::disk($disk)->exists($image->object_key_web));

        $thumbAllowed = ! $missingWebOnly || $generateWeb || $force;
        $generateThumb = $thumbAllowed && (
            $force
            || ! $convention->isConformingThumbnailKey($image, $image->object_key_thumb)
            || ($image->object_key_thumb && ! Storage::disk($disk)->exists($image->object_key_thumb))
        );
        $generateHd = ! $missingWebOnly && (
            $force
            || ! $convention->isConformingHdKey($image, $image->object_key_hd)
            || ! $convention->isConformingHdKey($image, $image->object_key_original)
            || ($image->object_key_hd && ! Storage::disk($disk)->exists($image->object_key_hd))
        );

        return [
            'web' => $generateWeb,
            'thumb' => $generateThumb,
            'hd' => $generateHd,
        ];
    }

    private function targetPrefixForImage(Image $image, ProjectImageStoragePath $storagePath, string $targetPrefix): string
    {
        if ($targetPrefix !== '') {
            return $targetPrefix;
        }

        if ($image->project) {
            return $storagePath->prefix($image->project);
        }

        return trim((string) dirname((string) $image->object_key_original), '/');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Support\ImageVariantGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('images:generate-variants
    {--source-prefix=photos : Prefixe des originaux legacy a traiter}
    {--target-prefix=images : Prefixe de destination des variantes}
    {--project= : ID du projet a traiter}
    {--folder= : Dossier source a traiter}
    {--limit= : Limite le nombre d images traitees}
    {--dry-run : Affiche les images concernees sans generer les variantes}
    {--missing-web-only : Traite uniquement les images sans vraie variante web}
    {--force : Regenere les variantes meme si les cles web/thumb/hd sont deja renseignees}')]
#[Description('Genere les variantes web/thumb/hd depuis les originaux deja presents dans le bucket')]
class GenerateImageVariants extends Command
{
    public function handle(ImageVariantGenerator $variants): int
    {
        $sourcePrefix = trim((string) $this->option('source-prefix'), '/');
        $targetPrefix = trim((string) $this->option('target-prefix'), '/') ?: 'images';
        $projectId = $this->option('project') ? (int) $this->option('project') : null;
        $folder = trim((string) $this->option('folder'));
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');
        $missingWebOnly = (bool) $this->option('missing-web-only');
        $force = (bool) $this->option('force');

        $query = Image::query()
            ->whereNotNull('object_key_original')
            ->where('object_key_original', 'like', "{$sourcePrefix}/%")
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->when($folder !== '', fn ($query) => $this->constrainFolder($query, $sourcePrefix, $folder))
            ->when($missingWebOnly, fn ($query) => $this->constrainMissingUsableWeb($query))
            ->when(! $force && ! $missingWebOnly, fn ($query) => $query->where(function ($query) {
                $query
                    ->whereNull('object_key_web')
                    ->orWhereNull('object_key_thumb')
                    ->orWhereNull('object_key_hd');
            }))
            ->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $checked = 0;
        $generated = 0;
        $missingOriginals = 0;
        $failed = 0;

        $query->lazyById()->each(function (Image $image) use (
            $variants,
            $targetPrefix,
            $dryRun,
            &$checked,
            &$generated,
            &$missingOriginals,
            &$failed,
        ): void {
            $checked++;
            $disk = match ($image->storage_provider) {
                'public' => 'public',
                'local' => 'local',
                default => 'scaleway',
            };

            if (! $image->object_key_original || ! Storage::disk($disk)->exists($image->object_key_original)) {
                $missingOriginals++;
                $this->warn("Original absent: {$image->object_key_original} ({$image->title})");

                return;
            }

            if ($dryRun) {
                $generated++;
                $this->line("[dry-run] {$image->id} -> {$image->object_key_original}");

                return;
            }

            try {
                $fileData = $variants->generateFromOriginal($image, $targetPrefix);

                $image->update([
                    'storage_provider' => $fileData['disk'],
                    'object_key_web' => $fileData['web'],
                    'object_key_thumb' => $fileData['thumb'] ?? null,
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
        });

        $this->newLine();
        $this->components->twoColumnDetail('Images verifiees', (string) $checked);
        $this->components->twoColumnDetail($dryRun ? 'Images a generer' : 'Images generees', (string) $generated);
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

    private function constrainMissingUsableWeb($query): void
    {
        $query->where(function ($query): void {
            $query
                ->whereNull('object_key_web')
                ->orWhereColumn('object_key_web', 'object_key_original')
                ->orWhereColumn('object_key_web', 'object_key_hd');
        });
    }
}

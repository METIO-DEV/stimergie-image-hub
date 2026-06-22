<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Support\ImageVariantGenerator;
use App\Support\ProjectImageVariantConvention;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('images:generate-project-thumbnails
    {--project= : ID du projet a traiter}
    {--folder= : Dossier source projet a traiter}
    {--limit= : Limite le nombre d images traitees}
    {--dry-run : Affiche les miniatures concernees sans les generer}
    {--force : Regenere les miniatures meme si elles sont deja conformes}')]
#[Description('Genere uniquement les miniatures projet dans photos/<projet>/miniatures/')]
class GenerateProjectThumbnails extends Command
{
    public function handle(
        ImageVariantGenerator $variants,
        ProjectImageVariantConvention $convention,
    ): int {
        $projectId = $this->option('project') ? (int) $this->option('project') : null;
        $folder = trim((string) $this->option('folder'));
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = Image::query()
            ->with('project')
            ->whereNotNull('object_key_original')
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->when($folder !== '', fn ($query) => $this->constrainFolder($query, $folder))
            ->orderBy('id');

        $checked = 0;
        $generated = 0;
        $alreadyConforming = 0;
        $missingOriginals = 0;
        $failed = 0;

        $query->lazyById()->each(function (Image $image) use (
            $variants,
            $convention,
            $limit,
            $dryRun,
            $force,
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

            if (! $image->project) {
                $failed++;
                $this->warn("Image {$image->id}: aucun projet associe.");

                return true;
            }

            $disk = $convention->disk($image->storage_provider);
            $hasValidThumbnail = $convention->isConformingThumbnailKey($image, $image->object_key_thumb)
                && $image->object_key_thumb
                && Storage::disk($disk)->exists($image->object_key_thumb)
                && $this->thumbnailFitsExpectedSize($disk, $image->object_key_thumb);

            if ($hasValidThumbnail && ! $force) {
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
                $this->line("[dry-run] miniature {$image->id} -> ".$convention->projectPrefixForImage($image).'/'.ImageVariantGenerator::THUMBNAIL_VARIANT_DIRECTORY);

                return true;
            }

            try {
                $fileData = $variants->generateFromOriginal(
                    $image,
                    $convention->projectPrefixForImage($image),
                    generateWeb: false,
                    generateThumb: true,
                    generateHd: false,
                );

                $image->update([
                    'object_key_thumb' => $fileData['thumb'] ?? null,
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

                if (isset($fileData['variants']['thumb'])) {
                    $variants->syncImageVariants($image, ['thumb' => $fileData['variants']['thumb']]);
                }

                $generated++;
            } catch (Throwable $exception) {
                $failed++;
                $image->update(['processing_error' => $exception->getMessage()]);
                $this->error("Echec image {$image->id}: {$exception->getMessage()}");
            }

            return true;
        });

        $this->newLine();
        $this->components->twoColumnDetail('Images verifiees', (string) $checked);
        $this->components->twoColumnDetail($dryRun ? 'Miniatures a generer' : 'Miniatures generees', (string) $generated);
        $this->components->twoColumnDetail('Miniatures deja conformes', (string) $alreadyConforming);
        $this->components->twoColumnDetail('Originaux absents', (string) $missingOriginals);
        $this->components->twoColumnDetail('Echecs', (string) $failed);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
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

    private function thumbnailFitsExpectedSize(string $disk, string $objectKey): bool
    {
        $stream = Storage::disk($disk)->readStream($objectKey);

        if ($stream === false) {
            return false;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'stimergie-thumb-audit-');

        if ($tempPath === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            return false;
        }

        $target = fopen($tempPath, 'w');

        if ($target === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            @unlink($tempPath);

            return false;
        }

        stream_copy_to_stream($stream, $target);

        if (is_resource($stream)) {
            fclose($stream);
        }

        fclose($target);

        try {
            $size = @getimagesize($tempPath);

            if (! $size) {
                return false;
            }

            return max($size[0], $size[1]) <= ImageVariantGenerator::THUMBNAIL_MAX_SIZE;
        } finally {
            @unlink($tempPath);
        }
    }
}

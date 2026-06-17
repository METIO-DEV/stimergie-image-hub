<?php

namespace App\Jobs;

use App\Models\DownloadJob;
use App\Models\Image;
use App\Support\ImageExportPresets;
use App\Support\ImageUrlResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class PrepareDownloadArchive implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(private readonly int $downloadJobId) {}

    public function handle(ImageUrlResolver $imageUrls): void
    {
        $job = DownloadJob::query()->findOrFail($this->downloadJobId);
        $variant = (string) data_get($job->payload, 'variant', 'web');
        $cropPreset = (string) data_get($job->payload, 'crop_preset', 'square');
        $cropSource = (string) data_get($job->payload, 'crop_source', 'web');
        $cropSettings = $this->cropSettingsByImage(data_get($job->payload, 'crops', []));
        $imageIds = collect(data_get($job->payload, 'requested_image_ids', []))
            ->map(fn ($imageId) => (int) $imageId)
            ->filter()
            ->unique()
            ->values();

        $job->update([
            'status' => 'processing',
            'payload' => [
                ...($job->payload ?? []),
                'archive_progress' => [
                    'status' => 'processing',
                    'started_at' => now()->toIso8601String(),
                    'total' => $imageIds->count(),
                    'processed' => 0,
                    'added' => 0,
                    'skipped' => 0,
                ],
            ],
        ]);

        try {
            $images = Image::query()
                ->with([
                    'client:id,name',
                    'project:id,name',
                    'variants:id,image_id,kind,object_key',
                ])
                ->whereIn('id', $imageIds)
                ->get()
                ->sortBy(fn (Image $image) => $imageIds->search($image->id))
                ->values();

            $result = $this->buildArchive($job, $images, $variant, $imageUrls, $cropPreset, $cropSource, $cropSettings);

            $job->update([
                'status' => 'ready',
                'image_count' => $result['imageCount'],
                'object_key' => $result['objectKey'],
                'download_url' => $result['downloadUrl'],
                'download_url_expires_at' => now()->addDays(7),
                'processed_at' => now(),
                'error_details' => null,
                'payload' => [
                    ...($job->payload ?? []),
                    'archive_progress' => [
                        ...data_get($job->payload, 'archive_progress', []),
                        'status' => 'completed',
                        'processed' => $images->count(),
                        'added' => $result['imageCount'],
                        'skipped' => count($result['skippedImages']),
                        'finished_at' => now()->toIso8601String(),
                    ],
                    'skipped_images' => $result['skippedImages'],
                    'crop_source_fallbacks' => $result['cropSourceFallbacks'],
                ],
            ]);
        } catch (Throwable $exception) {
            $job->update([
                'status' => 'failed',
                'error_details' => $exception->getMessage(),
                'payload' => [
                    ...($job->payload ?? []),
                    'archive_progress' => [
                        ...data_get($job->payload, 'archive_progress', []),
                        'status' => 'failed',
                        'failed_at' => now()->toIso8601String(),
                        'error' => $exception->getMessage(),
                    ],
                ],
            ]);

            throw $exception;
        }
    }

    /**
     * @param  Collection<int, Image>  $images
     * @param  array<int, array{focus_x: float, focus_y: float, zoom: float}>  $cropSettings
     * @return array{objectKey: string, downloadUrl: string, imageCount: int, skippedImages: array<int, array{id: int, title: string}>, cropSourceFallbacks: array<int, array{id: int, title: string, from: string, to: string, object_key: string|null}>}
     */
    private function buildArchive(
        DownloadJob $job,
        Collection $images,
        string $variant,
        ImageUrlResolver $imageUrls,
        string $cropPreset,
        string $cropSource,
        array $cropSettings,
    ): array {
        $tempPath = tempnam(sys_get_temp_dir(), 'stimergie-download-');
        $zip = new ZipArchive;
        $zipIsOpen = false;
        $processed = 0;
        $added = 0;
        $skipped = [];
        $cropSourceFallbacks = [];

        if ($tempPath === false || $zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de creer l archive ZIP.');
        }

        $zipIsOpen = true;

        try {
            foreach ($images as $image) {
                $processed++;
                $source = $variant === 'crop'
                    ? $this->cropSource($image, $imageUrls, $cropSource)
                    : $imageUrls->downloadSource($image, $variant);

                if (($source['fallbackFrom'] ?? null) === 'web') {
                    $cropSourceFallbacks[] = [
                        'id' => $image->id,
                        'title' => $image->title,
                        'from' => 'web',
                        'to' => 'hd',
                        'object_key' => $source['objectKey'],
                    ];
                }

                if (! $source['objectKey'] || ! Storage::disk($source['disk'])->exists($source['objectKey'])) {
                    $skipped[] = ['id' => $image->id, 'title' => $image->title];
                    $this->updateProgress($job, $processed, $added, $skipped, $image);

                    continue;
                }

                $temporaryImagePaths = [];

                try {
                    $temporaryImagePath = $this->copyObjectToTemporaryFile(
                        $source['disk'],
                        $source['objectKey'],
                    );
                    $temporaryImagePaths[] = $temporaryImagePath;

                    if ($variant === 'crop') {
                        $temporaryImagePath = $this->cropImage(
                            $temporaryImagePath,
                            ImageExportPresets::get($cropPreset),
                            $cropSettings[$image->id] ?? ['focus_x' => 0.5, 'focus_y' => 0.5, 'zoom' => 1.0],
                        );
                        $temporaryImagePaths[] = $temporaryImagePath;
                    }

                    $entryName = $this->archiveFilename($image, $variant, $added + 1, $source['objectKey'], $cropPreset);

                    $addedToArchive = $zip->addFile($temporaryImagePath, $entryName);

                    if (! $addedToArchive) {
                        throw new RuntimeException("Impossible d ajouter l image {$image->id} a l archive ZIP.");
                    }

                    $this->storeEntryWithoutCompression($zip, $entryName);

                    if (! $zip->close()) {
                        throw new RuntimeException('Impossible de finaliser une entree de l archive ZIP.');
                    }

                    $zipIsOpen = false;
                    $added++;
                    $this->updateProgress($job, $processed, $added, $skipped, $image);

                    if ($zip->open($tempPath, ZipArchive::CREATE) !== true) {
                        throw new RuntimeException('Impossible de rouvrir l archive ZIP.');
                    }

                    $zipIsOpen = true;
                } finally {
                    foreach ($temporaryImagePaths as $temporaryImagePath) {
                        @unlink($temporaryImagePath);
                    }
                }
            }

            if ($added === 0) {
                throw new RuntimeException('Aucune image telechargeable trouvee.');
            }

            $this->closeArchive($zip, $zipIsOpen);
            $zipIsOpen = false;

            $disk = (string) config('filesystems.image_disk', 'scaleway');
            $objectKey = sprintf(
                'downloads/%d/%s-%s.zip',
                $job->user_id,
                $variant === 'crop' ? ImageExportPresets::get($cropPreset)['slug'].'-'.$cropSource : $variant,
                Str::uuid(),
            );

            $this->putArchive($disk, $objectKey, $tempPath);

            return [
                'objectKey' => $objectKey,
                'downloadUrl' => $this->temporaryDownloadUrl($disk, $objectKey, now()->addDays(7)),
                'imageCount' => $added,
                'skippedImages' => $skipped,
                'cropSourceFallbacks' => $cropSourceFallbacks,
            ];
        } finally {
            if ($zipIsOpen) {
                $zip->close();
            }

            @unlink($tempPath);
        }
    }

    /**
     * @return array{provider: string|null, disk: string, objectKey: string|null, fallbackFrom?: string}
     */
    private function cropSource(Image $image, ImageUrlResolver $imageUrls, string $cropSource): array
    {
        if ($cropSource === 'web') {
            $webObjectKey = $this->standaloneWebObjectKey($image);

            if ($webObjectKey) {
                return [
                    'provider' => $image->storage_provider,
                    'disk' => $imageUrls->disk($image->storage_provider),
                    'objectKey' => $webObjectKey,
                ];
            }

            $source = $imageUrls->downloadSource($image, 'hd');
            $source['fallbackFrom'] = 'web';

            return $source;
        }

        return $imageUrls->downloadSource($image, 'hd');
    }

    private function standaloneWebObjectKey(Image $image): ?string
    {
        $objectKey = null;

        if ($image->relationLoaded('variants')) {
            $objectKey = $image->variants
                ->firstWhere('kind', 'web')
                ?->object_key;
        }

        $objectKey = $objectKey ?: $image->object_key_web;

        if (! is_string($objectKey) || $objectKey === '') {
            return null;
        }

        return in_array($objectKey, array_filter([
            $image->object_key_original,
            $image->object_key_hd,
        ]), true) ? null : $objectKey;
    }

    private function copyObjectToTemporaryFile(string $disk, string $objectKey): string
    {
        $sourceStream = Storage::disk($disk)->readStream($objectKey);

        if ($sourceStream === false) {
            throw new RuntimeException("Impossible de lire l image {$objectKey}.");
        }

        $temporaryImagePath = tempnam(sys_get_temp_dir(), 'stimergie-download-image-');

        if ($temporaryImagePath === false) {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }

            throw new RuntimeException('Impossible de creer un fichier temporaire image.');
        }

        $targetStream = fopen($temporaryImagePath, 'w');

        if ($targetStream === false) {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }

            @unlink($temporaryImagePath);

            throw new RuntimeException('Impossible d ouvrir le fichier temporaire image.');
        }

        try {
            if (stream_copy_to_stream($sourceStream, $targetStream) === false) {
                throw new RuntimeException("Impossible de copier l image {$objectKey}.");
            }
        } finally {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }

            fclose($targetStream);
        }

        return $temporaryImagePath;
    }

    private function putArchive(string $disk, string $objectKey, string $tempPath): void
    {
        $stream = fopen($tempPath, 'r');

        if ($stream === false) {
            throw new RuntimeException('Impossible de lire l archive ZIP.');
        }

        try {
            Storage::disk($disk)->put($objectKey, $stream, [
                'visibility' => 'private',
                'ContentType' => 'application/zip',
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function cropImage(string $sourcePath, array $preset, array $settings): string
    {
        $size = @getimagesize($sourcePath);

        if (! $size) {
            throw new RuntimeException('Impossible de lire les dimensions de l image a recadrer.');
        }

        $mimeType = $size['mime'] ?? null;
        $source = match ($mimeType) {
            'image/png' => imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($sourcePath) : false,
            default => imagecreatefromjpeg($sourcePath),
        };

        if (! $source) {
            throw new RuntimeException('Impossible de preparer le recadrage image.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetWidth = $preset['width'];
        $targetHeight = $preset['height'];
        $targetRatio = $targetWidth / $targetHeight;
        $sourceRatio = $sourceWidth / $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($cropHeight * $targetRatio);
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($cropWidth / $targetRatio);
        }

        $zoom = max(1.0, min(4.0, (float) $settings['zoom']));
        $cropWidth = max(1, (int) round($cropWidth / $zoom));
        $cropHeight = max(1, (int) round($cropHeight / $zoom));
        $focusX = max(0.0, min(1.0, (float) $settings['focus_x']));
        $focusY = max(0.0, min(1.0, (float) $settings['focus_y']));
        $sourceX = (int) round(($sourceWidth * $focusX) - ($cropWidth / 2));
        $sourceY = (int) round(($sourceHeight * $focusY) - ($cropHeight / 2));
        $sourceX = max(0, min($sourceX, $sourceWidth - $cropWidth));
        $sourceY = max(0, min($sourceY, $sourceHeight - $cropHeight));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight,
        );

        $temporaryImagePath = tempnam(sys_get_temp_dir(), 'stimergie-crop-');

        if ($temporaryImagePath === false) {
            imagedestroy($source);
            imagedestroy($target);
            throw new RuntimeException('Impossible de creer le fichier recadre.');
        }

        imagejpeg($target, $temporaryImagePath, 88);
        imagedestroy($source);
        imagedestroy($target);

        return $temporaryImagePath;
    }

    private function archiveFilename(
        Image $image,
        string $variant,
        int $index,
        ?string $objectKey,
        string $cropPreset,
    ): string {
        $extension = $variant === 'crop'
            ? 'jpg'
            : (pathinfo($objectKey ?? '', PATHINFO_EXTENSION) ?: 'jpg');
        $name = Str::slug($image->title) ?: "image-{$image->id}";
        $suffix = $variant === 'crop' ? '-'.ImageExportPresets::get($cropPreset)['slug'] : '';

        return sprintf('%03d-%s%s.%s', $index, $name, $suffix, $extension);
    }

    /**
     * JPEG, PNG and WebP files are already compressed. Storing them as-is avoids
     * wasting CPU on ZIP deflate and keeps large HD archives predictable.
     */
    private function storeEntryWithoutCompression(ZipArchive $zip, string $entryName): void
    {
        if (method_exists($zip, 'setCompressionName')) {
            $zip->setCompressionName($entryName, ZipArchive::CM_STORE);
        }
    }

    private function closeArchive(ZipArchive $zip, bool $zipIsOpen): void
    {
        if ($zipIsOpen && ! $zip->close()) {
            throw new RuntimeException('Impossible de finaliser l archive ZIP.');
        }
    }

    /**
     * @param  array<int, array{id: int, title: string}>  $skipped
     */
    private function updateProgress(DownloadJob $job, int $processed, int $added, array $skipped, Image $image): void
    {
        $job->forceFill([
            'image_count' => $added,
            'payload' => [
                ...($job->payload ?? []),
                'archive_progress' => [
                    ...data_get($job->payload, 'archive_progress', []),
                    'status' => 'processing',
                    'processed' => $processed,
                    'added' => $added,
                    'skipped' => count($skipped),
                    'current_image_id' => $image->id,
                    'updated_at' => now()->toIso8601String(),
                ],
                'skipped_images' => $skipped,
            ],
        ])->save();
    }

    /**
     * @return array<int, array{focus_x: float, focus_y: float, zoom: float}>
     */
    private function cropSettingsByImage(mixed $crops): array
    {
        if (! is_array($crops)) {
            return [];
        }

        $settings = [];

        foreach ($crops as $crop) {
            if (! is_array($crop) || ! isset($crop['image_id'])) {
                continue;
            }

            $settings[(int) $crop['image_id']] = [
                'focus_x' => (float) ($crop['focus_x'] ?? 0.5),
                'focus_y' => (float) ($crop['focus_y'] ?? 0.5),
                'zoom' => (float) ($crop['zoom'] ?? 1.0),
            ];
        }

        return $settings;
    }

    private function temporaryDownloadUrl(string $disk, string $objectKey, \DateTimeInterface $expiresAt): string
    {
        try {
            return Storage::disk($disk)->temporaryUrl($objectKey, $expiresAt, [
                'ResponseContentType' => 'application/zip',
            ]);
        } catch (Throwable) {
            return Storage::disk($disk)->url($objectKey);
        }
    }
}

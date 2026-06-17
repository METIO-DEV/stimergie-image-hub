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

        $job->update(['status' => 'processing']);

        try {
            $images = Image::query()
                ->with(['client:id,name', 'project:id,name'])
                ->whereIn('id', $imageIds)
                ->get();

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
                    'skipped_images' => $result['skippedImages'],
                ],
            ]);
        } catch (Throwable $exception) {
            $job->update([
                'status' => 'failed',
                'error_details' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  Collection<int, Image>  $images
     * @param  array<int, array{focus_x: float, focus_y: float, zoom: float}>  $cropSettings
     * @return array{objectKey: string, downloadUrl: string, imageCount: int, skippedImages: array<int, array{id: int, title: string}>}
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
        $temporaryImagePaths = [];

        if ($tempPath === false || $zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de creer l archive ZIP.');
        }

        $zipIsOpen = true;

        try {
            $added = 0;
            $skipped = [];

            foreach ($images as $image) {
                $source = $variant === 'crop'
                    ? $this->cropSource($image, $imageUrls, $cropSource)
                    : $imageUrls->downloadSource($image, $variant);

                if (! $source['objectKey'] || ! Storage::disk($source['disk'])->exists($source['objectKey'])) {
                    $skipped[] = ['id' => $image->id, 'title' => $image->title];

                    continue;
                }

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

                $addedToArchive = $zip->addFile(
                    $temporaryImagePath,
                    $this->archiveFilename($image, $variant, $added + 1, $imageUrls, $cropPreset),
                );

                if (! $addedToArchive) {
                    throw new RuntimeException("Impossible d ajouter l image {$image->id} a l archive ZIP.");
                }

                $added++;
            }

            if ($added === 0) {
                throw new RuntimeException('Aucune image telechargeable trouvee.');
            }

            $archiveWasClosed = $zip->close();

            $zipIsOpen = false;

            if (! $archiveWasClosed) {
                throw new RuntimeException('Impossible de finaliser l archive ZIP.');
            }

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
            ];
        } finally {
            if ($zipIsOpen) {
                $zip->close();
            }

            foreach ($temporaryImagePaths as $temporaryImagePath) {
                @unlink($temporaryImagePath);
            }

            @unlink($tempPath);
        }
    }

    /**
     * @return array{provider: string|null, disk: string, objectKey: string|null}
     */
    private function cropSource(Image $image, ImageUrlResolver $imageUrls, string $cropSource): array
    {
        if ($cropSource === 'web') {
            $source = $imageUrls->downloadSource($image, 'web');

            if (
                ! $image->object_key_web ||
                $source['objectKey'] !== $image->object_key_web ||
                in_array($image->object_key_web, array_filter([
                    $image->object_key_original,
                    $image->object_key_hd,
                ]), true)
            ) {
                $source['objectKey'] = null;
            }

            return $source;
        }

        return $imageUrls->downloadSource($image, 'hd');
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
        ImageUrlResolver $imageUrls,
        string $cropPreset,
    ): string {
        $extension = $variant === 'crop'
            ? 'jpg'
            : (pathinfo(
                $imageUrls->downloadSource($image, $variant)['objectKey'] ?? '',
                PATHINFO_EXTENSION,
            ) ?: 'jpg');
        $name = Str::slug($image->title) ?: "image-{$image->id}";
        $suffix = $variant === 'crop' ? '-'.ImageExportPresets::get($cropPreset)['slug'] : '';

        return sprintf('%03d-%s%s.%s', $index, $name, $suffix, $extension);
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

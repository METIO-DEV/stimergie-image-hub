<?php

namespace App\Jobs;

use App\Models\DownloadJob;
use App\Models\Image;
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

            $result = $this->buildArchive($job, $images, $variant, $imageUrls);

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
     * @return array{objectKey: string, downloadUrl: string, imageCount: int, skippedImages: array<int, array{id: int, title: string}>}
     */
    private function buildArchive(
        DownloadJob $job,
        Collection $images,
        string $variant,
        ImageUrlResolver $imageUrls,
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
                $source = $imageUrls->downloadSource($image, $variant);

                if (! $source['objectKey'] || ! Storage::disk($source['disk'])->exists($source['objectKey'])) {
                    $skipped[] = ['id' => $image->id, 'title' => $image->title];

                    continue;
                }

                $temporaryImagePath = $this->copyObjectToTemporaryFile(
                    $source['disk'],
                    $source['objectKey'],
                );
                $temporaryImagePaths[] = $temporaryImagePath;

                $addedToArchive = $zip->addFile(
                    $temporaryImagePath,
                    $this->archiveFilename($image, $variant, $added + 1, $imageUrls),
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
                $variant,
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

    private function archiveFilename(Image $image, string $variant, int $index, ImageUrlResolver $imageUrls): string
    {
        $extension = pathinfo(
            $imageUrls->downloadSource($image, $variant)['objectKey'] ?? '',
            PATHINFO_EXTENSION,
        ) ?: 'jpg';
        $name = Str::slug($image->title) ?: "image-{$image->id}";

        return sprintf('%03d-%s.%s', $index, $name, $extension);
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

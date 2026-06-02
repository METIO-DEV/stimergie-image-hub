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

        if ($tempPath === false || $zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de creer l archive ZIP.');
        }

        $added = 0;
        $skipped = [];

        foreach ($images as $image) {
            $source = $imageUrls->downloadSource($image, $variant);

            if (! $source['objectKey'] || ! Storage::disk($source['disk'])->exists($source['objectKey'])) {
                $skipped[] = ['id' => $image->id, 'title' => $image->title];

                continue;
            }

            $zip->addFromString(
                $this->archiveFilename($image, $variant, $added + 1, $imageUrls),
                Storage::disk($source['disk'])->get($source['objectKey']),
            );
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            @unlink($tempPath);
            throw new RuntimeException('Aucune image telechargeable trouvee.');
        }

        $disk = (string) config('filesystems.image_disk', 'scaleway');
        $objectKey = sprintf(
            'downloads/%d/%s-%s.zip',
            $job->user_id,
            $variant,
            Str::uuid(),
        );

        $stream = fopen($tempPath, 'r');

        if ($stream === false) {
            @unlink($tempPath);
            throw new RuntimeException('Impossible de lire l archive ZIP.');
        }

        Storage::disk($disk)->put($objectKey, $stream, [
            'visibility' => 'private',
            'ContentType' => 'application/zip',
        ]);

        if (is_resource($stream)) {
            fclose($stream);
        }

        @unlink($tempPath);

        return [
            'objectKey' => $objectKey,
            'downloadUrl' => $this->temporaryDownloadUrl($disk, $objectKey, now()->addDays(7)),
            'imageCount' => $added,
            'skippedImages' => $skipped,
        ];
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

<?php

namespace App\Jobs;

use App\Models\AssetTransferJob;
use App\Models\Image;
use App\Support\ImageVariantGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RunMissingWebVariantGenerationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(public readonly int $variantJobId) {}

    public function handle(ImageVariantGenerator $variants): void
    {
        $job = AssetTransferJob::find($this->variantJobId);

        if (! $job || ! $job->isActive()) {
            return;
        }

        $sourcePrefix = trim((string) data_get($job->metadata, 'web_variant_generation.source_prefix', 'photos'), '/') ?: 'photos';
        $targetPrefix = trim((string) data_get($job->metadata, 'web_variant_generation.target_prefix', 'images'), '/') ?: 'images';
        $projectId = data_get($job->metadata, 'web_variant_generation.project_id');
        $folder = trim((string) data_get($job->metadata, 'web_variant_generation.folder', ''));
        $scopeLabel = (string) data_get($job->metadata, 'web_variant_generation.scope_label', 'Tous les projets');

        $query = $this->candidateQuery($sourcePrefix, $projectId ? (int) $projectId : null, $folder);
        $this->prepare($job, $query->count(), $scopeLabel);

        $checked = 0;
        $generated = 0;
        $missingOriginals = 0;
        $failed = 0;

        $this->candidateQuery($sourcePrefix, $projectId ? (int) $projectId : null, $folder)
            ->lazyById()
            ->each(function (Image $image) use (
                $job,
                $variants,
                $targetPrefix,
                &$checked,
                &$generated,
                &$missingOriginals,
                &$failed,
            ): bool {
                $job->refresh();

                if ($job->cancel_requested_at) {
                    $this->cancel($job, 'Arrêt demandé.');

                    return false;
                }

                $checked++;
                $job->forceFill([
                    'current_folder' => "#{$image->id} {$image->title}",
                    'processed_folders' => $checked,
                ])->save();

                $disk = match ($image->storage_provider) {
                    'public' => 'public',
                    'local' => 'local',
                    default => 'scaleway',
                };

                if (! $image->object_key_original || ! Storage::disk($disk)->exists($image->object_key_original)) {
                    $missingOriginals++;
                    $this->appendLog($job, "Original absent: {$image->object_key_original} ({$image->title})".PHP_EOL);
                    $this->storeTotals($job, $checked, $generated, $missingOriginals, $failed);

                    return true;
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

                    $this->appendLog($job, "Variante web générée: #{$image->id} {$image->title}".PHP_EOL);
                } catch (Throwable $exception) {
                    $failed++;
                    $failedDetails = $job->failed_folder_details ?? [];
                    $failedDetails["#{$image->id} {$image->title}"] = $exception->getMessage();

                    $image->update(['processing_error' => $exception->getMessage()]);
                    $job->forceFill([
                        'failed_folders' => $failed,
                        'failed_folder_details' => $failedDetails,
                    ])->save();
                    $this->appendLog($job, "Échec image {$image->id}: {$exception->getMessage()}".PHP_EOL);
                }

                $this->storeTotals($job, $checked, $generated, $missingOriginals, $failed);

                return true;
            });

        $job->refresh();

        if ($job->status === 'cancelled') {
            return;
        }

        $totals = data_get($job->metadata, 'web_variant_generation.totals', []);

        $job->forceFill([
            'status' => ((int) ($totals['failed'] ?? 0) > 0 || (int) ($totals['missing_originals'] ?? 0) > 0) ? 'failed' : 'completed',
            'current_folder' => null,
            'finished_at' => now(),
        ])->save();

        $this->appendLog($job, sprintf(
            PHP_EOL.'Génération terminée: %d vérifiée(s), %d générée(s), %d original(aux) absent(s), %d échec(s).'.PHP_EOL,
            (int) ($totals['checked'] ?? 0),
            (int) ($totals['generated'] ?? 0),
            (int) ($totals['missing_originals'] ?? 0),
            (int) ($totals['failed'] ?? 0),
        ));
    }

    private function prepare(AssetTransferJob $job, int $totalImages, string $scopeLabel): void
    {
        File::ensureDirectoryExists(storage_path('app/asset-transfers'));

        $job->forceFill([
            'status' => 'running',
            'total_folders' => $totalImages,
            'started_at' => $job->started_at ?? now(),
            'log_file' => $job->log_file ?: storage_path("app/asset-transfers/web-variants-{$job->id}.log"),
        ])->save();

        $this->storeTotals($job, 0, 0, 0, 0);
        $this->appendLog($job, sprintf(
            'Démarrage génération variantes web #%d - %s - %d image(s).'.PHP_EOL,
            $job->id,
            $scopeLabel,
            $totalImages,
        ));
    }

    private function candidateQuery(string $sourcePrefix, ?int $projectId, string $folder)
    {
        return Image::query()
            ->whereNotNull('object_key_original')
            ->where('object_key_original', 'like', "{$sourcePrefix}/%")
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->when($folder !== '', fn ($query) => $this->constrainFolder($query, $sourcePrefix, $folder))
            ->where(function ($query): void {
                $query
                    ->whereNull('object_key_web')
                    ->orWhereColumn('object_key_web', 'object_key_original')
                    ->orWhereColumn('object_key_web', 'object_key_hd');
            })
            ->orderBy('id');
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

    private function storeTotals(AssetTransferJob $job, int $checked, int $generated, int $missingOriginals, int $failed): void
    {
        $metadata = $job->metadata ?? [];
        $metadata['web_variant_generation']['totals'] = [
            'checked' => $checked,
            'generated' => $generated,
            'missing_originals' => $missingOriginals,
            'failed' => $failed,
        ];

        $job->forceFill(['metadata' => $metadata])->save();
    }

    private function cancel(AssetTransferJob $job, string $reason): void
    {
        $job->forceFill([
            'status' => 'cancelled',
            'current_folder' => null,
            'finished_at' => now(),
        ])->save();

        $this->appendLog($job, PHP_EOL."Annulé: {$reason}".PHP_EOL);
    }

    private function appendLog(AssetTransferJob $job, string $message): void
    {
        if (! $job->log_file) {
            return;
        }

        File::append($job->log_file, $message);
    }
}

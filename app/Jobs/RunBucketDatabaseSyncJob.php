<?php

namespace App\Jobs;

use App\Models\AssetTransferJob;
use App\Models\Project;
use App\Support\ProjectBucketImageSynchronizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Throwable;

class RunBucketDatabaseSyncJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(public readonly int $syncJobId) {}

    public function handle(ProjectBucketImageSynchronizer $synchronizer): void
    {
        $job = AssetTransferJob::find($this->syncJobId);

        if (! $job || ! $job->isActive()) {
            return;
        }

        $this->prepare($job);

        $projects = Project::query()
            ->with('client:id,name')
            ->orderBy('id')
            ->get();

        foreach ($projects as $project) {
            $job->refresh();

            if ($job->cancel_requested_at) {
                $this->cancel($job, 'Arrêt demandé.');

                return;
            }

            $label = trim(($project->client?->name ? "{$project->client->name} / " : '').$project->name);

            $job->forceFill([
                'current_folder' => $label,
            ])->save();

            try {
                $result = $synchronizer->sync($project, $job->started_by);
                $this->storeProjectResult($job, $project->id, $label, $result);
                $this->appendLog($job, sprintf(
                    '#%d %s - %s : total %d, créées %d, maj %d, ignorées %d'.PHP_EOL,
                    $project->id,
                    $label,
                    $result['prefix'],
                    $result['total'],
                    $result['created'],
                    $result['updated'],
                    $result['skipped'],
                ));

                $job->increment('processed_folders');
            } catch (Throwable $exception) {
                $failed = $job->failed_folder_details ?? [];
                $failed[$label] = $exception->getMessage();

                $job->forceFill([
                    'failed_folder_details' => $failed,
                ])->save();
                $job->increment('failed_folders');

                $this->appendLog($job, "Erreur {$label}: {$exception->getMessage()}".PHP_EOL);
            }
        }

        $job->refresh();
        $job->forceFill([
            'status' => $job->failed_folders > 0 ? 'failed' : 'completed',
            'current_folder' => null,
            'finished_at' => now(),
        ])->save();

        $totals = $this->totals($job);
        $this->appendLog($job, sprintf(
            PHP_EOL.'Synchro terminée: %d projet(s), %d image(s) bucket, %d créée(s), %d mise(s) à jour, %d ignorée(s).'.PHP_EOL,
            $job->processed_folders,
            $totals['bucket_images'],
            $totals['created'],
            $totals['updated'],
            $totals['skipped'],
        ));
    }

    private function prepare(AssetTransferJob $job): void
    {
        File::ensureDirectoryExists(storage_path('app/asset-transfers'));

        $totalProjects = Project::query()->count();

        $job->forceFill([
            'status' => 'running',
            'total_folders' => $totalProjects,
            'started_at' => $job->started_at ?? now(),
            'log_file' => $job->log_file ?: storage_path("app/asset-transfers/bucket-db-sync-{$job->id}.log"),
        ])->save();

        $this->appendLog($job, sprintf(
            'Démarrage resynchro DB depuis bucket #%d - %d projet(s).'.PHP_EOL,
            $job->id,
            $totalProjects,
        ));
    }

    /**
     * @param  array{prefix: string, total: int, created: int, updated: int, skipped: int}  $result
     */
    private function storeProjectResult(AssetTransferJob $job, int $projectId, string $label, array $result): void
    {
        $metadata = $job->metadata ?? [];
        $metadata['bucket_db_sync']['projects'][$projectId] = [
            'label' => $label,
            ...$result,
        ];

        $metadata['bucket_db_sync']['totals'] = [
            'bucket_images' => ($metadata['bucket_db_sync']['totals']['bucket_images'] ?? 0) + $result['total'],
            'created' => ($metadata['bucket_db_sync']['totals']['created'] ?? 0) + $result['created'],
            'updated' => ($metadata['bucket_db_sync']['totals']['updated'] ?? 0) + $result['updated'],
            'skipped' => ($metadata['bucket_db_sync']['totals']['skipped'] ?? 0) + $result['skipped'],
        ];

        $job->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * @return array{bucket_images: int, created: int, updated: int, skipped: int}
     */
    private function totals(AssetTransferJob $job): array
    {
        $totals = ($job->metadata ?? [])['bucket_db_sync']['totals'] ?? [];

        return [
            'bucket_images' => (int) ($totals['bucket_images'] ?? 0),
            'created' => (int) ($totals['created'] ?? 0),
            'updated' => (int) ($totals['updated'] ?? 0),
            'skipped' => (int) ($totals['skipped'] ?? 0),
        ];
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

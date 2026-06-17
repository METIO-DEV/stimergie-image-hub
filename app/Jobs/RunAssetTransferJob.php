<?php

namespace App\Jobs;

use App\Models\AssetTransferJob;
use App\Models\Project;
use App\Support\ProjectBucketImageSynchronizer;
use App\Support\ProjectFolderMatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use Throwable;

class RunAssetTransferJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(public readonly int $transferJobId) {}

    public function handle(ProjectBucketImageSynchronizer $synchronizer, ProjectFolderMatcher $folderMatcher): void
    {
        $transfer = AssetTransferJob::find($this->transferJobId);

        if (! $transfer || ! $transfer->isActive()) {
            return;
        }

        $this->prepare($transfer);

        foreach ($transfer->folders ?? [] as $folder) {
            $transfer->refresh();

            if ($transfer->cancel_requested_at) {
                $this->cancel($transfer, 'Arrêt demandé avant le dossier suivant.');

                return;
            }

            $this->runFolder($transfer, (string) $folder, $synchronizer, $folderMatcher);
        }

        $transfer->refresh();

        if ($transfer->cancel_requested_at) {
            $this->cancel($transfer, 'Arrêt demandé.');

            return;
        }

        $transfer->forceFill([
            'status' => $transfer->failed_folders > 0 ? 'failed' : 'completed',
            'current_folder' => null,
            'process_id' => null,
            'finished_at' => now(),
        ])->save();

        $this->appendLog($transfer, sprintf(
            "\nTerminé: %d dossier(s) copiés, %d en erreur.\n",
            $transfer->processed_folders,
            $transfer->failed_folders,
        ));
    }

    private function prepare(AssetTransferJob $transfer): void
    {
        File::ensureDirectoryExists(storage_path('app/asset-transfers'));

        $transfer->forceFill([
            'status' => 'running',
            'started_at' => $transfer->started_at ?? now(),
            'log_file' => $transfer->log_file ?: storage_path("app/asset-transfers/transfer-{$transfer->id}.log"),
        ])->save();

        $this->appendLog($transfer, sprintf(
            "Démarrage du transfert #%d - %d dossier(s).\n",
            $transfer->id,
            $transfer->total_folders,
        ));
    }

    private function runFolder(
        AssetTransferJob $transfer,
        string $folder,
        ProjectBucketImageSynchronizer $synchronizer,
        ProjectFolderMatcher $folderMatcher,
    ): void {
        $transfer->forceFill([
            'current_folder' => $folder,
            'process_id' => null,
        ])->save();

        $this->appendLog($transfer, "\n--- {$folder} ---\n");

        $batchFile = storage_path("app/asset-transfers/transfer-{$transfer->id}-batch.txt");
        File::put($batchFile, $folder.PHP_EOL);

        $process = new Process(
            ['bash', base_path('scripts/o2switch-rclone-transfer.sh')],
            base_path(),
            [
                'RCLONE_BIN' => 'rclone',
                'MODE' => 'batch-copy',
                'BATCH_FILE' => $batchFile,
                'LOG_FILE' => (string) $transfer->log_file,
            ],
            null,
            null,
        );
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        try {
            $process->start();
            $transfer->forceFill(['process_id' => $process->getPid()])->save();

            while ($process->isRunning()) {
                $this->flushProcessOutput($transfer, $process);

                $transfer->refresh();

                if ($transfer->cancel_requested_at) {
                    $this->appendLog($transfer, "\nArrêt demandé, signal envoyé au transfert en cours.\n");
                    $process->signal(15);
                    sleep(2);

                    if ($process->isRunning()) {
                        $process->signal(9);
                    }
                }

                usleep(500_000);
            }

            $this->flushProcessOutput($transfer, $process);
            $exitCode = $process->getExitCode();
        } catch (ProcessSignaledException $exception) {
            $exitCode = $exception->getSignal();
            $this->appendLog($transfer, "\nProcess arrêté par signal {$exitCode}.\n");
        } catch (Throwable $exception) {
            $exitCode = 1;
            $this->appendLog($transfer, "\nErreur process: {$exception->getMessage()}\n");
        } finally {
            $transfer->forceFill(['process_id' => null])->save();
            File::delete($batchFile);
        }

        $transfer->refresh();

        if ($transfer->cancel_requested_at) {
            $this->cancel($transfer, "Transfert stoppé pendant {$folder}.");

            return;
        }

        if ($exitCode !== 0) {
            $this->markFolderFailed($transfer, $folder, "rclone a retourné le code {$exitCode}.");

            return;
        }

        $this->markFolderCompleted($transfer, $folder);
        $this->syncDatabaseForFolder($transfer, $folder, $synchronizer, $folderMatcher);
    }

    private function syncDatabaseForFolder(
        AssetTransferJob $transfer,
        string $folder,
        ProjectBucketImageSynchronizer $synchronizer,
        ProjectFolderMatcher $folderMatcher,
    ): void {
        $project = $this->projectForFolder($folder, $folderMatcher);

        if (! $project) {
            $this->appendLog($transfer, "Synchro DB ignorée: aucun projet ne correspond à {$folder}.\n");

            return;
        }

        try {
            $result = $synchronizer->sync($project, $transfer->started_by);
            $metadata = $transfer->metadata ?? [];
            $metadata['db_sync'][$folder] = [
                'project_id' => $project->id,
                'project_name' => $project->name,
                ...$result,
            ];

            $transfer->forceFill(['metadata' => $metadata])->save();
            $this->appendLog($transfer, sprintf(
                "Synchro DB: %s, %d créée(s), %d mise(s) à jour, %d ignorée(s).\n",
                $project->name,
                $result['created'],
                $result['updated'],
                $result['skipped'],
            ));
        } catch (Throwable $exception) {
            $this->markFolderFailed($transfer, $folder, 'Synchro DB: '.$exception->getMessage());
        }
    }

    private function projectForFolder(string $folder, ProjectFolderMatcher $folderMatcher): ?Project
    {
        $mapped = $folderMatcher->mappedProject($folder);

        if ($mapped) {
            return $mapped;
        }

        $projects = Project::query()->get();
        $exact = $folderMatcher->exactProject($folder, $projects);

        if ($exact) {
            return $exact;
        }

        $best = $folderMatcher->bestProject($folder, $projects);

        return $best['score'] >= ProjectFolderMatcher::AUTO_MATCH_SCORE ? $best['project'] : null;
    }

    private function markFolderCompleted(AssetTransferJob $transfer, string $folder): void
    {
        $completed = $transfer->completed_folders ?? [];
        $completed[] = $folder;

        $transfer->forceFill([
            'processed_folders' => count(array_unique($completed)),
            'completed_folders' => array_values(array_unique($completed)),
        ])->save();
    }

    private function markFolderFailed(AssetTransferJob $transfer, string $folder, string $error): void
    {
        $failed = $transfer->failed_folder_details ?? [];
        $failed[$folder] = $error;

        $transfer->forceFill([
            'failed_folders' => count($failed),
            'failed_folder_details' => $failed,
        ])->save();

        $this->appendLog($transfer, "Erreur {$folder}: {$error}\n");
    }

    private function cancel(AssetTransferJob $transfer, string $reason): void
    {
        $transfer->forceFill([
            'status' => 'cancelled',
            'current_folder' => null,
            'process_id' => null,
            'finished_at' => now(),
        ])->save();

        $this->appendLog($transfer, "\nAnnulé: {$reason}\n");
    }

    private function flushProcessOutput(AssetTransferJob $transfer, Process $process): void
    {
        $output = $process->getIncrementalOutput().$process->getIncrementalErrorOutput();

        if ($output !== '') {
            $this->appendLog($transfer, $output);
        }
    }

    private function appendLog(AssetTransferJob $transfer, string $message): void
    {
        if (! $transfer->log_file) {
            return;
        }

        File::append($transfer->log_file, $message);
    }
}

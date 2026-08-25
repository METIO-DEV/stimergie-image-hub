<?php

use App\Jobs\PrepareDownloadArchive;
use App\Models\DownloadJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('downloads:cleanup-expired', function () {
    $jobs = DownloadJob::query()
        ->whereNotNull('download_url_expires_at')
        ->where('download_url_expires_at', '<', now())
        ->whereIn('status', ['ready', 'failed'])
        ->get();

    foreach ($jobs as $job) {
        if ($job->object_key) {
            Storage::disk($job->storage_provider ?: config('filesystems.image_disk', 'scaleway'))
                ->delete($job->object_key);
        }

        $job->update([
            'status' => 'expired',
            'object_key' => null,
            'download_url' => null,
        ]);
    }

    $this->info("Archives expirees nettoyees: {$jobs->count()}");
})->purpose('Delete expired generated download archives');

Artisan::command('downloads:retry-failed {--id=* : Identifiants précis de jobs à relancer} {--stale-minutes=30 : Age minimal des jobs processing à considérer bloqués}', function () {
    $ids = collect($this->option('id'))
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values();
    $staleBefore = now()->subMinutes(max(1, (int) $this->option('stale-minutes')));

    $jobs = DownloadJob::query()
        ->when(
            $ids->isNotEmpty(),
            fn ($query) => $query->whereIn('id', $ids),
            fn ($query) => $query->where(function ($query) use ($staleBefore): void {
                $query
                    ->where('status', 'failed')
                    ->orWhere(function ($query) use ($staleBefore): void {
                        $query
                            ->where('status', 'processing')
                            ->where('updated_at', '<=', $staleBefore);
                    });
            }),
        )
        ->get();

    foreach ($jobs as $job) {
        if ($job->object_key) {
            Storage::disk($job->storage_provider ?: config('filesystems.image_disk', 'scaleway'))
                ->delete($job->object_key);
        }

        $job->update([
            'status' => 'pending',
            'object_key' => null,
            'download_url' => null,
            'download_url_expires_at' => null,
            'processed_at' => null,
            'error_details' => null,
            'payload' => [
                ...($job->payload ?? []),
                'archive_progress' => [
                    'status' => 'pending',
                    'retried_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        PrepareDownloadArchive::dispatch($job->id);
    }

    $this->info("Archives relancees: {$jobs->count()}");
})->purpose('Retry failed or stale generated download archives');

Schedule::command('downloads:cleanup-expired')->daily();
Schedule::command('images:send-monthly-digest')->monthlyOn(1, '08:00');

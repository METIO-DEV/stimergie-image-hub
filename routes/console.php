<?php

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

Schedule::command('downloads:cleanup-expired')->daily();
Schedule::command('images:send-monthly-digest')->monthlyOn(1, '08:00');

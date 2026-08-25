<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class O2SwitchAssetBrowser
{
    /**
     * @return array<int, string>
     */
    public function ftpFolders(int $limit = 1000): array
    {
        $process = new Process(
            ['bash', base_path('scripts/o2switch-rclone-transfer.sh')],
            base_path(),
            [
                'RCLONE_BIN' => 'rclone',
                'MODE' => 'list-dirs',
                'BATCH_SIZE' => (string) $limit,
                'BATCH_OFFSET' => '0',
            ],
        );

        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Impossible de lister le FTP.');
        }

        return collect(explode("\n", $process->getOutput()))
            ->map(fn (string $folder) => trim($folder))
            ->filter()
            ->reject(fn (string $folder) => str_contains($folder, ':'))
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function bucketFolders(string $prefix = 'photos'): array
    {
        $folders = [];

        foreach (Storage::disk(config('filesystems.image_disk', 'scaleway'))->allFiles($prefix) as $file) {
            $relative = trim(substr($file, strlen(trim($prefix, '/')) + 1), '/');
            $folder = explode('/', $relative)[0] ?? '';

            if ($folder === '') {
                continue;
            }

            $folders[$folder] = ($folders[$folder] ?? 0) + 1;
        }

        ksort($folders, SORT_NATURAL | SORT_FLAG_CASE);

        return $folders;
    }
}

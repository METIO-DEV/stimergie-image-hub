<?php

namespace App\Console\Commands;

use App\Models\DownloadJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('downloads:reconcile-legacy-urls
    {--prefix=zip-downloads : Prefixe bucket des archives legacy}
    {--dry-run : Affiche les changements sans modifier la base}
    {--force : Reecrit aussi les jobs ayant deja un object_key}')]
#[Description('Reecrit les anciennes URLs de telechargement stimergie.fr vers le bucket Scaleway')]
class ReconcileLegacyDownloadUrls extends Command
{
    public function handle(): int
    {
        $prefix = trim((string) $this->option('prefix'), '/');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $disk = Storage::disk('scaleway');

        $query = DownloadJob::query()
            ->whereNotNull('download_url')
            ->where(function ($query) {
                $query
                    ->where('download_url', 'like', '%stimergie.fr%')
                    ->orWhere('download_url', 'like', '%/zip-downloads/%');
            })
            ->when(! $force, fn ($query) => $query->whereNull('object_key'))
            ->orderBy('id');

        $checked = 0;
        $rewritten = 0;
        $unmapped = 0;
        $objectsPresent = 0;
        $objectsMissing = 0;

        $query->lazyById()->each(function (DownloadJob $job) use (
            $disk,
            $prefix,
            $dryRun,
            &$checked,
            &$rewritten,
            &$unmapped,
            &$objectsPresent,
            &$objectsMissing,
        ): void {
            $checked++;
            $objectKey = $this->objectKey($job->download_url, $prefix);

            if (! $objectKey) {
                $unmapped++;
                $this->warn("Mapping impossible: download job {$job->id}");

                return;
            }

            $exists = $disk->exists($objectKey);
            $exists ? $objectsPresent++ : $objectsMissing++;
            $publicUrl = $disk->url($objectKey);

            if ($dryRun) {
                $rewritten++;
                $this->line("[dry-run] {$job->id} -> {$publicUrl}".($exists ? '' : ' (objet absent)'));

                return;
            }

            $job->update([
                'storage_provider' => 'scaleway',
                'object_key' => $objectKey,
                'download_url' => $publicUrl,
                'error_details' => $exists ? $job->error_details : 'Archive legacy absente du bucket Scaleway.',
            ]);

            $rewritten++;
        });

        $this->newLine();
        $this->components->twoColumnDetail('Jobs verifies', (string) $checked);
        $this->components->twoColumnDetail($dryRun ? 'Jobs a reecrire' : 'Jobs reecrits', (string) $rewritten);
        $this->components->twoColumnDetail('Mappings impossibles', (string) $unmapped);
        $this->components->twoColumnDetail('Archives presentes bucket', (string) $objectsPresent);
        $this->components->twoColumnDetail('Archives absentes bucket', (string) $objectsMissing);

        return self::SUCCESS;
    }

    private function objectKey(?string $url, string $prefix): ?string
    {
        $path = parse_url((string) $url, PHP_URL_PATH);

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim(rawurldecode($path), '/');
        $offset = strpos("/{$path}", "/{$prefix}/");

        if ($offset === false) {
            return null;
        }

        $objectKey = substr("/{$path}", $offset + 1);

        return $objectKey !== '' ? $objectKey : null;
    }
}

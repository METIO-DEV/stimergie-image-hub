<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Support\ObjectStoragePolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReconcileClientLogos extends Command
{
    protected $signature = 'clients:reconcile-logos
        {--force : Remplace logo_object_key meme s il existe deja}
        {--dry-run : Affiche les actions sans ecrire dans le bucket}
        {--limit= : Nombre maximal de logos a traiter}';

    protected $description = 'Recupere les logos clients legacy/Supabase et les stocke dans le bucket Scaleway.';

    public function __construct(private readonly ObjectStoragePolicy $storagePolicy)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $disk = Storage::disk((string) config('filesystems.image_disk', 'scaleway'));
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $processed = 0;

        Client::query()
            ->whereNotNull('legacy_logo_url')
            ->where('legacy_logo_url', '!=', '')
            ->when(! $force, fn ($query) => $query->whereNull('logo_object_key'))
            ->orderBy('id')
            ->each(function (Client $client) use ($disk, $dryRun, $limit, &$processed): false|null {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $url = $this->normalizedUrl($client->legacy_logo_url);
                $objectKey = $this->objectKey($client, $url);

                if ($dryRun) {
                    $this->line("[dry-run] {$client->id} {$client->name} -> {$objectKey}");
                    $processed++;

                    return null;
                }

                $response = Http::timeout(20)->get($url);

                if (! $response->successful()) {
                    $this->warn("Logo indisponible: {$client->id} {$client->name} ({$url})");

                    return null;
                }

                $disk->put($objectKey, $response->body(), $this->storagePolicy->putOptions(
                    $objectKey,
                    $response->header('Content-Type'),
                ));
                $client->forceFill(['logo_object_key' => $objectKey])->save();
                $processed++;
                $this->line("Logo migre: {$client->id} {$client->name} -> {$objectKey}");

                return null;
            });

        $this->info("Logos traites: {$processed}");

        return self::SUCCESS;
    }

    private function normalizedUrl(string $url): string
    {
        if (str_starts_with($url, '//')) {
            return "https:{$url}";
        }

        if (str_starts_with($url, '/')) {
            return rtrim((string) config('app.url'), '/').$url;
        }

        return $url;
    }

    private function objectKey(Client $client, string $url): string
    {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'png';

        return 'clients/'.$client->id.'/logo-legacy-'.Str::slug($client->slug).'.'.Str::lower($extension);
    }
}

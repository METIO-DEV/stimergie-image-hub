<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\DownloadJob;
use App\Models\Image;
use App\Models\Project;
use App\Models\ProjectAccessPeriod;
use App\Models\SharedAlbum;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('legacy:import-dump
    {dump=dumps/prod-public-data.sql : Chemin du dump de donnees Supabase}
    {--fresh : Vide les tables metier avant import}
    {--with-assets : Telecharge les images/logos et les pousse vers le disque scaleway}
    {--asset-limit= : Limite le nombre d assets uploades}
    {--asset-concurrency=8 : Nombre de telechargements paralleles par lot}
    {--skip-existing-assets : Ignore les objets deja presents dans le bucket}')]
#[Description('Importe le dump Supabase public vers le schema Laravel/Inertia')]
class ImportLegacyDump extends Command
{
    private array $tables = [];

    private array $clients = [];

    private array $users = [];

    private array $projects = [];

    private array $images = [];

    private array $albums = [];

    private int $uploadedAssets = 0;

    /**
     * @var array<int, string>
     */
    private array $importedLegacyTables = [
        'albums',
        'album_images',
        'blog_posts',
        'clients',
        'download_requests',
        'image_shared_clients',
        'images',
        'profiles',
        'project_access_periods',
        'projets',
        'user_roles',
    ];

    public function handle(): int
    {
        $dumpPath = base_path($this->argument('dump'));

        if (! is_file($dumpPath)) {
            $this->error("Dump introuvable: {$dumpPath}");

            return self::FAILURE;
        }

        $this->tables = $this->readCopyTables($dumpPath);
        $this->info('Dump lu: '.collect($this->tables)->map(fn ($rows, $table) => "{$table}=".count($rows))->implode(', '));

        if ($this->option('fresh')) {
            $this->truncateDomainTables();
        }

        DB::transaction(function (): void {
            $this->importClients();
            $this->importUsersAndMemberships();
            $this->importProjects();
            $this->importAccessPeriods();
            $this->importImages();
            $this->importImageShares();
            $this->importAlbums();
            $this->importBlogPosts();
            $this->importDownloadJobs();
        });

        if ($this->option('with-assets')) {
            $this->uploadAssets();
        }

        $this->newLine();
        $this->info('Import termine.');

        return self::SUCCESS;
    }

    private function truncateDomainTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'audit_logs',
            'blog_posts',
            'import_items',
            'imports',
            'download_jobs',
            'shared_album_images',
            'shared_albums',
            'image_client_shares',
            'image_tag',
            'tags',
            'image_variants',
            'images',
            'project_access_periods',
            'projects',
            'client_memberships',
            'clients',
            'users',
        ] as $table) {
            DB::table($table)->delete();
        }

        Schema::enableForeignKeyConstraints();
    }

    private function importClients(): void
    {
        foreach ($this->tables['clients'] ?? [] as $row) {
            $client = Client::updateOrCreate(
                ['legacy_id' => $row['id']],
                [
                    'name' => $row['nom'],
                    'slug' => $this->uniqueSlug(Client::class, $row['nom'], $row['id']),
                    'status' => 'active',
                    'legacy_logo_url' => $row['logo'] ?? null,
                    'metadata' => [
                        'contact_principal' => $row['contact_principal'] ?? null,
                        'email' => $row['email'] ?? null,
                        'telephone' => $row['telephone'] ?? null,
                    ],
                    'created_at' => $this->date($row['created_at']),
                    'updated_at' => $this->date($row['updated_at']),
                ],
            );

            $this->clients[$row['id']] = $client->id;
        }
    }

    private function importUsersAndMemberships(): void
    {
        $roles = collect($this->tables['user_roles'] ?? [])
            ->pluck('role', 'user_id')
            ->all();

        foreach ($this->tables['profiles'] ?? [] as $row) {
            $legacyRole = $roles[$row['id']] ?? $row['role'] ?? 'user';
            $name = trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')) ?: $row['email'];

            $user = User::updateOrCreate(
                ['legacy_id' => $row['id']],
                [
                    'name' => $name,
                    'email' => $row['email'],
                    'password' => Hash::make(Str::password(40)),
                    'platform_role' => $legacyRole === 'admin' ? 'super_admin' : 'user',
                    'status' => 'active',
                    'created_at' => $this->date($row['created_at']),
                    'updated_at' => $this->date($row['updated_at']),
                ],
            );

            $this->users[$row['id']] = $user->id;

            foreach ($this->profileClientIds($row) as $legacyClientId => $isDefault) {
                if (! isset($this->clients[$legacyClientId])) {
                    continue;
                }

                ClientMembership::updateOrCreate(
                    [
                        'client_id' => $this->clients[$legacyClientId],
                        'user_id' => $user->id,
                    ],
                    [
                        'role' => $this->membershipRole($legacyRole),
                        'status' => 'active',
                        'is_default' => $isDefault,
                        'created_by' => null,
                    ],
                );
            }
        }
    }

    private function importProjects(): void
    {
        foreach ($this->tables['projets'] ?? [] as $row) {
            if (! isset($this->clients[$row['id_client']])) {
                continue;
            }

            $project = Project::updateOrCreate(
                ['legacy_id' => $row['id']],
                [
                    'client_id' => $this->clients[$row['id_client']],
                    'name' => $row['nom_projet'],
                    'slug' => $this->uniqueSlug(Project::class, $row['nom_projet'], $row['id'], $this->clients[$row['id_client']]),
                    'type' => $row['type_projet'] ?? null,
                    'source_folder' => $row['nom_dossier'] ?? null,
                    'status' => 'active',
                    'created_at' => $this->date($row['created_at']),
                    'updated_at' => $this->date($row['created_at']),
                ],
            );

            $this->projects[$row['id']] = $project->id;
        }
    }

    private function importAccessPeriods(): void
    {
        foreach ($this->tables['project_access_periods'] ?? [] as $row) {
            if (! isset($this->projects[$row['project_id']], $this->clients[$row['client_id']])) {
                continue;
            }

            ProjectAccessPeriod::updateOrCreate(
                ['legacy_id' => $row['id']],
                [
                    'project_id' => $this->projects[$row['project_id']],
                    'client_id' => $this->clients[$row['client_id']],
                    'starts_at' => $this->date($row['access_start']),
                    'ends_at' => $this->date($row['access_end']),
                    'is_active' => (bool) $row['is_active'],
                    'created_by' => $this->users[$row['created_by'] ?? null] ?? null,
                    'created_at' => $this->date($row['created_at']),
                    'updated_at' => $this->date($row['updated_at']),
                ],
            );
        }
    }

    private function importImages(): void
    {
        foreach ($this->tables['images'] ?? [] as $row) {
            if (! isset($this->projects[$row['id_projet']])) {
                continue;
            }

            $project = Project::find($this->projects[$row['id_projet']]);

            if (! $project) {
                continue;
            }

            $image = Image::updateOrCreate(
                ['legacy_id' => (string) $row['id']],
                [
                    'client_id' => $project->client_id,
                    'project_id' => $project->id,
                    'created_by' => $this->users[$row['created_by'] ?? null] ?? null,
                    'title' => $row['title'],
                    'description' => $row['description'] ?? null,
                    'orientation' => $row['orientation'] ?? null,
                    'width' => $row['width'] ?? null,
                    'height' => $row['height'] ?? null,
                    'storage_provider' => 'scaleway',
                    'legacy_url' => $row['url'],
                    'legacy_thumbnail_url' => $row['url_miniature'] ?? null,
                    'status' => 'ready',
                    'metadata' => [
                        'legacy_tags' => $row['tags'] ?? null,
                    ],
                    'created_at' => $this->date($row['created_at']),
                    'updated_at' => $this->date($row['updated_at']),
                ],
            );

            $this->images[(string) $row['id']] = $image->id;
            $this->syncTags($image, $row['tags'] ?? '');
        }
    }

    private function importImageShares(): void
    {
        foreach ($this->tables['image_shared_clients'] ?? [] as $row) {
            if (! isset($this->images[(string) $row['image_id']], $this->clients[$row['client_id']])) {
                continue;
            }

            DB::table('image_client_shares')->updateOrInsert(
                [
                    'image_id' => $this->images[(string) $row['image_id']],
                    'client_id' => $this->clients[$row['client_id']],
                ],
                [
                    'created_by' => $this->users[$row['shared_by'] ?? null] ?? null,
                    'expires_at' => null,
                    'created_at' => $this->date($row['created_at'] ?? $row['shared_at']),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function importAlbums(): void
    {
        foreach ($this->tables['albums'] ?? [] as $row) {
            $album = SharedAlbum::updateOrCreate(
                ['legacy_id' => $row['id']],
                [
                    'client_id' => null,
                    'created_by' => $this->users[$row['created_by'] ?? null] ?? null,
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'share_key' => $row['share_key'],
                    'starts_at' => $this->date($row['access_from']),
                    'expires_at' => $this->date($row['access_until']),
                    'is_active' => true,
                    'metadata' => [
                        'recipients' => $this->parsePgArray($row['recipients'] ?? '{}'),
                    ],
                    'created_at' => $this->date($row['created_at']),
                    'updated_at' => $this->date($row['updated_at']),
                ],
            );

            $this->albums[$row['id']] = $album->id;
        }

        foreach ($this->tables['album_images'] ?? [] as $row) {
            if (! isset($this->albums[$row['album_id']], $this->images[(string) $row['image_id']])) {
                continue;
            }

            DB::table('shared_album_images')->updateOrInsert(
                [
                    'shared_album_id' => $this->albums[$row['album_id']],
                    'image_id' => $this->images[(string) $row['image_id']],
                ],
                [
                    'position' => 0,
                ],
            );
        }
    }

    private function importBlogPosts(): void
    {
        foreach ($this->tables['blog_posts'] ?? [] as $row) {
            if (! isset($this->users[$row['author_id']])) {
                continue;
            }

            BlogPost::updateOrCreate(
                ['legacy_id' => $row['id']],
                [
                    'client_id' => $this->clients[$row['client_id'] ?? null] ?? null,
                    'author_id' => $this->users[$row['author_id']],
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'content' => $row['content'],
                    'content_type' => $row['content_type'] ?? 'resource',
                    'category' => $row['category'] ?? null,
                    'is_published' => (bool) $row['published'],
                    'featured_image_object_key' => null,
                    'published_at' => (bool) $row['published'] ? $this->date($row['created_at']) : null,
                    'created_at' => $this->date($row['created_at']),
                    'updated_at' => $this->date($row['updated_at']),
                ],
            );
        }
    }

    private function importDownloadJobs(): void
    {
        foreach ($this->tables['download_requests'] ?? [] as $row) {
            if (! isset($this->users[$row['user_id']])) {
                continue;
            }

            $downloadObjectKey = $this->legacyDownloadObjectKey($row['download_url'] ?? null);
            $downloadUrl = $downloadObjectKey
                ? Storage::disk('scaleway')->url($downloadObjectKey)
                : null;

            DownloadJob::updateOrCreate(
                ['legacy_id' => $row['id']],
                [
                    'user_id' => $this->users[$row['user_id']],
                    'client_id' => null,
                    'title' => $row['image_title'],
                    'status' => $row['status'],
                    'is_hd' => (bool) $row['is_hd'],
                    'image_count' => 1,
                    'storage_provider' => $downloadObjectKey ? 'scaleway' : null,
                    'object_key' => $downloadObjectKey,
                    'download_url' => $downloadUrl,
                    'download_url_expires_at' => $this->date($row['expires_at']),
                    'processed_at' => $this->date($row['processed_at'] ?? null),
                    'error_details' => $row['error_details'] ?? null,
                    'payload' => [
                        'legacy_image_id' => $row['image_id'],
                        'legacy_image_src' => $row['image_src'],
                    ],
                    'created_at' => $this->date($row['created_at']),
                    'updated_at' => $this->date($row['created_at']),
                ],
            );
        }
    }

    private function legacyDownloadObjectKey(?string $url): ?string
    {
        $path = parse_url((string) $url, PHP_URL_PATH);

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim(rawurldecode($path), '/');
        $offset = strpos("/{$path}", '/zip-downloads/');

        if ($offset === false) {
            return null;
        }

        $objectKey = substr("/{$path}", $offset + 1);

        return $objectKey !== '' ? $objectKey : null;
    }

    private function uploadAssets(): void
    {
        $limit = $this->option('asset-limit') ? (int) $this->option('asset-limit') : null;
        $concurrency = max(1, (int) $this->option('asset-concurrency'));

        $clientAssets = Client::query()
            ->whereNotNull('legacy_logo_url')
            ->where('legacy_logo_url', '!=', '')
            ->whereNull('logo_object_key')
            ->get()
            ->map(fn (Client $client) => [
                'model' => Client::class,
                'id' => $client->id,
                'column' => 'logo_object_key',
                'url' => $client->legacy_logo_url,
                'key_base' => "legacy/clients/{$client->legacy_id}/logo",
            ])
            ->all();

        $imageAssets = Image::query()
            ->whereNotNull('legacy_url')
            ->where('legacy_url', '!=', '')
            ->whereNull('object_key_original')
            ->get()
            ->map(fn (Image $image) => [
                'model' => Image::class,
                'id' => $image->id,
                'column' => 'object_key_original',
                'url' => $image->legacy_url,
                'key_base' => "legacy/images/{$image->legacy_id}/original",
            ])
            ->all();

        foreach (array_chunk([...$clientAssets, ...$imageAssets], $concurrency) as $chunk) {
            if ($this->assetLimitReached($limit)) {
                return;
            }

            $this->uploadAssetChunk($chunk, $limit);
        }
    }

    private function uploadAssetChunk(array $assets, ?int $limit): void
    {
        $disk = Storage::disk('scaleway');
        $pending = [];

        foreach ($assets as $asset) {
            if ($this->assetLimitReached($limit)) {
                break;
            }

            if (! $asset['url']) {
                continue;
            }

            $asset['url'] = $this->normalizeUrl($asset['url']);
            $extension = pathinfo(parse_url($asset['url'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'bin';
            $asset['key'] = $asset['key_base'].'.'.Str::lower($extension);

            if ($this->option('skip-existing-assets') && $disk->exists($asset['key'])) {
                $disk->setVisibility($asset['key'], 'public');
                $asset['model']::query()->whereKey($asset['id'])->update([$asset['column'] => $asset['key']]);

                continue;
            }

            $pending[$asset['key']] = $asset;
        }

        if ($pending === []) {
            return;
        }

        $responses = Http::pool(fn (Pool $pool) => collect($pending)
            ->map(fn (array $asset) => $pool
                ->as($asset['key'])
                ->timeout(90)
                ->get($asset['url']))
            ->all());

        foreach ($pending as $key => $asset) {
            try {
                /** @var Response $response */
                $response = $responses[$key];

                if ($response instanceof \Throwable) {
                    throw $response;
                }

                $response->throw();

                $disk->put($key, $response->body(), [
                    'visibility' => 'public',
                    'ContentType' => $response->header('Content-Type') ?: null,
                    'CacheControl' => 'public, max-age=31536000, immutable',
                ]);

                $asset['model']::query()->whereKey($asset['id'])->update([$asset['column'] => $key]);

                $this->uploadedAssets++;

                if ($this->uploadedAssets % 25 === 0) {
                    $this->info("Assets uploades: {$this->uploadedAssets}");
                }
            } catch (RequestException $exception) {
                $this->warn("Asset impossible a recuperer ({$exception->response->status()}): {$asset['url']}");
            } catch (\Throwable $exception) {
                $this->warn("Asset ignore: {$asset['url']} ({$exception->getMessage()})");
            }
        }
    }

    private function readCopyTables(string $path): array
    {
        $tables = [];
        $handle = fopen($path, 'rb');
        $table = null;
        $columns = [];

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            if (preg_match('/^COPY "public"\."([^"]+)" \((.+)\) FROM stdin;$/', $line, $matches)) {
                $table = $matches[1];
                $columns = collect(explode(', ', $matches[2]))
                    ->map(fn (string $column) => trim($column, '"'))
                    ->all();

                if (! in_array($table, $this->importedLegacyTables, true)) {
                    $table = '__skip__';
                    $columns = [];
                } else {
                    $tables[$table] = [];
                }

                continue;
            }

            if ($table && $line === '\.') {
                $table = null;
                $columns = [];

                continue;
            }

            if ($table && $table !== '__skip__') {
                $values = explode("\t", $line);
                $tables[$table][] = array_combine($columns, array_map($this->decodeCopyValue(...), $values));
            }
        }

        fclose($handle);

        return $tables;
    }

    private function decodeCopyValue(string $value): mixed
    {
        if ($value === '\N') {
            return null;
        }

        return strtr($value, [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\\\' => '\\',
        ]);
    }

    private function date(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    private function syncTags(Image $image, ?string $tags): void
    {
        $ids = collect(explode(',', $tags ?? ''))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique(fn (string $tag) => Str::slug($tag))
            ->map(function (string $tag): int {
                return Tag::firstOrCreate(
                    ['slug' => Str::slug($tag)],
                    ['name' => $tag],
                )->id;
            })
            ->all();

        $image->tags()->sync($ids);
    }

    private function profileClientIds(array $profile): array
    {
        $ids = [];

        if ($profile['id_client'] ?? null) {
            $ids[$profile['id_client']] = true;
        }

        foreach ($this->parsePgArray($profile['client_ids'] ?? '{}') as $id) {
            $ids[$id] = ($profile['id_client'] ?? null) === $id;
        }

        return $ids;
    }

    private function parsePgArray(?string $value): array
    {
        if (! $value || $value === '{}') {
            return [];
        }

        return collect(str_getcsv(trim($value, '{}')))
            ->map(fn (string $entry) => trim($entry, '"'))
            ->filter()
            ->values()
            ->all();
    }

    private function membershipRole(string $legacyRole): string
    {
        return match ($legacyRole) {
            'admin' => 'owner',
            'admin_client' => 'manager',
            default => 'member',
        };
    }

    private function uniqueSlug(string $modelClass, string $value, string $legacyId, ?int $clientId = null): string
    {
        $base = Str::slug($value) ?: 'legacy';
        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()
            ->where('slug', $slug)
            ->where('legacy_id', '!=', $legacyId)
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);

        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $path = collect(explode('/', $parts['path'] ?? ''))
            ->map(fn (string $segment) => rawurlencode(rawurldecode($segment)))
            ->implode('/');

        return $parts['scheme'].'://'.$parts['host'].$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function assetLimitReached(?int $limit): bool
    {
        return $limit !== null && $this->uploadedAssets >= $limit;
    }
}

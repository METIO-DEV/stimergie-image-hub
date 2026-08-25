<?php

namespace App\Console\Commands;

use App\Support\ObjectStoragePolicy;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Console\Command;

class PrivatizeImageObjects extends Command
{
    protected $signature = 'images:privatize-objects
        {--prefix=* : Prefixes to process. Defaults to photos/ and images/}
        {--limit= : Maximum number of objects to scan}
        {--execute : Actually update object ACL/cache metadata}
        {--acl-only : Only update object ACL, without rewriting Cache-Control metadata}';

    protected $description = 'Passe les objets images en prive avec un Cache-Control court, sans toucher aux logos clients publics.';

    public function handle(ObjectStoragePolicy $storagePolicy): int
    {
        $diskName = (string) config('filesystems.image_disk', 'scaleway');
        $disk = config("filesystems.disks.{$diskName}");

        if (! is_array($disk) || ($disk['driver'] ?? null) !== 's3') {
            $this->error("Le disque image [{$diskName}] n est pas un disque S3.");

            return self::FAILURE;
        }

        $bucket = (string) ($disk['bucket'] ?? '');

        if ($bucket === '') {
            $this->error("Le bucket du disque [{$diskName}] n est pas configure.");

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $aclOnly = (bool) $this->option('acl-only');
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        $prefixes = $this->prefixes();
        $client = $this->s3Client($disk);
        $scanned = 0;
        $upToDate = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        if (! $execute) {
            $this->warn('Dry-run: aucun objet ne sera modifie. Ajoutez --execute pour appliquer.');
        }

        foreach ($prefixes as $prefix) {
            $this->line("Prefixe: {$prefix}");
            $continuationToken = null;

            do {
                $result = $client->listObjectsV2(array_filter([
                    'Bucket' => $bucket,
                    'Prefix' => $prefix,
                    'ContinuationToken' => $continuationToken,
                ]));

                foreach ($result['Contents'] ?? [] as $object) {
                    if ($limit !== null && $scanned >= $limit) {
                        break 2;
                    }

                    $key = (string) ($object['Key'] ?? '');

                    if ($key === '' || str_ends_with($key, '/') || str_starts_with($key, 'clients/')) {
                        continue;
                    }

                    $scanned++;

                    try {
                        if ($execute) {
                            $state = $this->inspectObject($client, $bucket, $key, $storagePolicy);

                            if ($state['isPrivate'] && ($aclOnly || $state['hasExpectedCacheControl'])) {
                                $upToDate++;
                                $skipped++;
                                $this->line("[skip] {$key} ({$state['summary']})");

                                continue;
                            }

                            $this->line("[update] {$key} ({$state['summary']})");
                            $this->privatizeObject($client, $bucket, $key, $aclOnly, $storagePolicy);
                            $updated++;
                            $this->info("[done] {$key}");
                        } else {
                            $state = $this->inspectObject($client, $bucket, $key, $storagePolicy);

                            if ($state['isPrivate'] && ($aclOnly || $state['hasExpectedCacheControl'])) {
                                $upToDate++;
                                $this->line("[dry-run] {$key} -> deja conforme ({$state['summary']})");
                            } else {
                                $this->line("[dry-run] {$key} -> a modifier ({$state['summary']}) => ACL private".($aclOnly ? '' : ', Cache-Control '.ObjectStoragePolicy::IMAGE_CACHE_CONTROL));
                            }
                        }
                    } catch (AwsException $exception) {
                        $errors++;
                        $this->warn("Erreur S3 {$key}: {$exception->getAwsErrorMessage()}");
                    } catch (\Throwable $exception) {
                        $errors++;
                        $this->warn("Erreur {$key}: {$exception->getMessage()}");
                    }
                }

                $continuationToken = $result['NextContinuationToken'] ?? null;
            } while ($continuationToken !== null);
        }

        $this->info("Objets analyses: {$scanned}");
        $this->info("Objets deja conformes: {$upToDate}");
        $this->info("Objets ignores: {$skipped}");
        $this->info("Objets modifies: {$updated}");

        if ($errors > 0) {
            $this->warn("Erreurs: {$errors}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function prefixes(): array
    {
        $prefixes = $this->option('prefix');

        if (! is_array($prefixes) || $prefixes === []) {
            return ['photos/', 'images/'];
        }

        return collect($prefixes)
            ->map(fn (string $prefix): string => trim($prefix, '/').'/')
            ->filter(fn (string $prefix): bool => $prefix !== '/')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $disk
     */
    private function s3Client(array $disk): S3Client
    {
        $config = [
            'version' => 'latest',
            'region' => $disk['region'] ?? 'fr-par',
            'use_path_style_endpoint' => (bool) ($disk['use_path_style_endpoint'] ?? false),
        ];

        if (! empty($disk['endpoint'])) {
            $config['endpoint'] = $disk['endpoint'];
        }

        if (! empty($disk['key']) && ! empty($disk['secret'])) {
            $config['credentials'] = [
                'key' => $disk['key'],
                'secret' => $disk['secret'],
            ];
        }

        return new S3Client($config);
    }

    private function privatizeObject(
        S3Client $client,
        string $bucket,
        string $key,
        bool $aclOnly,
        ObjectStoragePolicy $storagePolicy,
    ): void {
        $client->putObjectAcl([
            'Bucket' => $bucket,
            'Key' => $key,
            'ACL' => 'private',
        ]);

        if (! $aclOnly) {
            $head = $client->headObject([
                'Bucket' => $bucket,
                'Key' => $key,
            ]);

            $copyParams = [
                'Bucket' => $bucket,
                'Key' => $key,
                'CopySource' => $this->copySource($bucket, $key),
                'MetadataDirective' => 'REPLACE',
                'ContentType' => (string) ($head['ContentType'] ?? 'application/octet-stream'),
                'CacheControl' => $storagePolicy->cacheControl($key),
            ];

            foreach (['ContentDisposition', 'ContentEncoding', 'ContentLanguage'] as $header) {
                if (isset($head[$header]) && is_string($head[$header]) && $head[$header] !== '') {
                    $copyParams[$header] = $head[$header];
                }
            }

            if (isset($head['Metadata']) && is_array($head['Metadata']) && $head['Metadata'] !== []) {
                $copyParams['Metadata'] = $head['Metadata'];
            }

            $client->copyObject($copyParams);

            $client->putObjectAcl([
                'Bucket' => $bucket,
                'Key' => $key,
                'ACL' => 'private',
            ]);
        }
    }

    /**
     * @return array{isPrivate: bool, hasExpectedCacheControl: bool, summary: string}
     */
    private function inspectObject(
        S3Client $client,
        string $bucket,
        string $key,
        ObjectStoragePolicy $storagePolicy,
    ): array {
        $head = $client->headObject([
            'Bucket' => $bucket,
            'Key' => $key,
        ]);
        $acl = $client->getObjectAcl([
            'Bucket' => $bucket,
            'Key' => $key,
        ]);

        $cacheControl = (string) ($head['CacheControl'] ?? 'absent');
        $publicGrants = collect($acl['Grants'] ?? [])
            ->filter(fn (array $grant): bool => $this->isPublicGrant($grant))
            ->pluck('Permission')
            ->values()
            ->all();
        $isPrivate = $publicGrants === [];
        $expectedCacheControl = $storagePolicy->cacheControl($key);
        $hasExpectedCacheControl = $cacheControl === $expectedCacheControl;
        $aclSummary = $isPrivate ? 'ACL private' : 'ACL public '.implode('/', $publicGrants);

        return [
            'isPrivate' => $isPrivate,
            'hasExpectedCacheControl' => $hasExpectedCacheControl,
            'summary' => "{$aclSummary}, Cache-Control {$cacheControl}",
        ];
    }

    /**
     * @param  array<string, mixed>  $grant
     */
    private function isPublicGrant(array $grant): bool
    {
        $grantee = $grant['Grantee'] ?? [];

        return is_array($grantee)
            && str_contains((string) ($grantee['URI'] ?? ''), 'AllUsers')
            && in_array((string) ($grant['Permission'] ?? ''), ['READ', 'READ_ACP', 'FULL_CONTROL'], true);
    }

    private function copySource(string $bucket, string $key): string
    {
        return $bucket.'/'.str_replace('%2F', '/', rawurlencode($key));
    }
}

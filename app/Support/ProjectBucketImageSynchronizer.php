<?php

namespace App\Support;

use App\Models\Image;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProjectBucketImageSynchronizer
{
    /**
     * @var array<int, string>|null
     */
    private ?array $cachedBucketPrefixes = null;

    public function __construct(
        private readonly ProjectImageStoragePath $storagePath,
        private readonly ImageVariantGenerator $imageVariants,
        private readonly ProjectFolderMatcher $folderMatcher,
    ) {}

    /**
     * @return array{prefix: string, total: int, created: int, updated: int, skipped: int}
     */
    public function sync(Project $project, ?int $createdBy = null, bool $dryRun = false): array
    {
        $diskName = (string) config('filesystems.image_disk', 'scaleway');
        $disk = Storage::disk($diskName);
        $prefixes = $this->prefixesForProject($disk, $project);
        $total = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($prefixes as $prefix) {
            $pairs = $this->bucketPairs($disk->allFiles($prefix), $prefix);
            $total += count($pairs);

            foreach ($pairs as $pair) {
                $existing = $this->existingImage($project, $pair['original'], $pair['web']);

                if ($existing instanceof Image) {
                    if ($dryRun) {
                        $skipped++;
                    } elseif ($this->attachMissingWebVariant($existing, $pair['web'])) {
                        $updated++;
                    } else {
                        $skipped++;
                    }

                    continue;
                }

                if ($dryRun) {
                    $created++;

                    continue;
                }

                $fileData = $this->inspectObject($diskName, $pair['original']);

                if ($this->duplicateByChecksumData($project, $fileData['checksum'])) {
                    $skipped++;

                    continue;
                }

                DB::transaction(function () use ($createdBy, $diskName, $fileData, $pair, $prefix, $project): void {
                    $image = Image::create([
                        'client_id' => $project->client_id,
                        'project_id' => $project->id,
                        'created_by' => $createdBy,
                        'title' => $this->titleFromObjectKey($pair['original']),
                        'orientation' => $this->orientation($fileData['width'], $fileData['height']),
                        'width' => $fileData['width'],
                        'height' => $fileData['height'],
                        'mime_type' => $fileData['mime_type'],
                        'size_bytes' => $fileData['size_bytes'],
                        'checksum' => $fileData['checksum'],
                        'storage_provider' => $diskName,
                        'object_key_original' => $pair['original'],
                        'object_key_web' => $pair['web'] ?? $pair['original'],
                        'object_key_thumb' => null,
                        'object_key_hd' => $pair['original'],
                        'legacy_url' => null,
                        'legacy_thumbnail_url' => null,
                        'status' => 'ready',
                        'processed_at' => now(),
                        'metadata' => [
                            'source' => 'bucket_sync',
                            'bucket_prefix' => $prefix,
                        ],
                    ]);

                    $this->imageVariants->syncImageVariants($image, $this->variants($fileData, $pair));
                });

                $created++;
            }
        }

        return [
            'prefix' => implode(', ', $prefixes),
            'total' => $total,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function prefixesForProject($disk, Project $project): array
    {
        $prefixes = [];

        foreach ($this->folderMatcher->mappedFoldersForProject($project) as $folder) {
            $prefix = 'photos/'.trim($folder, '/');

            if ($disk->allFiles($prefix) !== []) {
                $prefixes[] = $prefix;
            }
        }

        $expectedPrefix = $this->storagePath->prefix($project);
        $resolvedPrefix = $this->resolveExistingPrefix($disk, $expectedPrefix) ?? $expectedPrefix;
        $prefixes[] = $resolvedPrefix;

        return collect($prefixes)->unique()->values()->all();
    }

    private function resolveExistingPrefix($disk, string $expectedPrefix): ?string
    {
        if ($disk->allFiles($expectedPrefix) !== []) {
            return $expectedPrefix;
        }

        $candidates = $this->cachedBucketPrefixes ??= $this->candidatePrefixes($disk->allFiles('photos'));

        if ($candidates === []) {
            return null;
        }

        $expected = $this->normalizePath($expectedPrefix);
        $best = null;
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $score = $this->pathScore($expected, $this->normalizePath($candidate));

            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $bestScore >= ProjectFolderMatcher::AUTO_MATCH_SCORE ? $best : null;
    }

    /**
     * @param  array<int, string>  $files
     * @return array<int, string>
     */
    private function candidatePrefixes(array $files): array
    {
        $prefixes = [];

        foreach ($files as $file) {
            $directory = trim(dirname($file), '/');

            if ($directory === '.' || $directory === '') {
                continue;
            }

            $prefixes[$directory] = true;

            if (Str::lower(basename($directory)) === 'jpg') {
                $parent = trim(dirname($directory), '/');

                if ($parent !== '.' && $parent !== '') {
                    $prefixes[$parent] = true;
                }
            }
        }

        return array_keys($prefixes);
    }

    /**
     * @param  array<int, string>  $files
     * @return array<int, array{original: string, web: string|null}>
     */
    private function bucketPairs(array $files, string $prefix): array
    {
        $prefix = trim($prefix, '/');
        $originals = [];
        $webByIndex = [];

        foreach ($files as $file) {
            $key = trim($file, '/');

            if (! $this->isImageObject($key)) {
                continue;
            }

            $index = $this->pairIndex($key, $prefix);

            if ($this->isWebVariantObject($key, $prefix)) {
                $webByIndex[$index] = $key;

                continue;
            }

            $originals[$index] = $key;
        }

        $pairs = [];

        foreach ($originals as $index => $original) {
            $pairs[] = [
                'original' => $original,
                'web' => $webByIndex[$index] ?? null,
            ];

            unset($webByIndex[$index]);
        }

        foreach ($webByIndex as $web) {
            $pairs[] = [
                'original' => $web,
                'web' => $web,
            ];
        }

        return $pairs;
    }

    private function isImageObject(string $key): bool
    {
        $filename = basename($key);

        if ($filename === '' || Str::startsWith($filename, '.')) {
            return false;
        }

        return in_array(Str::lower(pathinfo($key, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    private function pairIndex(string $key, string $prefix): string
    {
        $segments = explode('/', $this->relativePath($key, $prefix));
        $filename = array_pop($segments) ?: '';
        $segments = array_values(array_filter(
            $segments,
            fn (string $segment) => Str::lower($segment) !== 'jpg',
        ));
        $path = trim(implode('/', [...$segments, pathinfo($filename, PATHINFO_FILENAME)]), '/');

        return Str::lower($path);
    }

    private function isWebVariantObject(string $key, string $prefix): bool
    {
        foreach (explode('/', $this->relativePath($key, $prefix)) as $segment) {
            if (Str::lower($segment) === 'jpg') {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $key, string $prefix): string
    {
        $prefix = trim($prefix, '/');

        if ($prefix !== '' && Str::startsWith($key, "{$prefix}/")) {
            return Str::after($key, "{$prefix}/");
        }

        return $key;
    }

    private function existingImage(Project $project, string $original, ?string $web): ?Image
    {
        return Image::query()
            ->where('project_id', $project->id)
            ->where(function ($query) use ($original, $web): void {
                $query->where('object_key_original', $original)
                    ->orWhere('object_key_web', $original)
                    ->orWhere('object_key_hd', $original);

                if ($web) {
                    $query->orWhere('object_key_original', $web)
                        ->orWhere('object_key_web', $web)
                        ->orWhere('object_key_hd', $web);
                }
            })
            ->first();
    }

    private function attachMissingWebVariant(Image $image, ?string $web): bool
    {
        if (! $web || $image->object_key_web) {
            return false;
        }

        $image->forceFill([
            'object_key_web' => $web,
        ])->save();

        $image->variants()->updateOrCreate(
            ['kind' => 'web'],
            [
                'object_key' => $web,
                'mime_type' => $image->mime_type,
                'width' => $image->width,
                'height' => $image->height,
                'size_bytes' => null,
            ],
        );

        return true;
    }

    /**
     * @return array{width: int|null, height: int|null, mime_type: string|null, size_bytes: int|null, checksum: string}
     */
    private function inspectObject(string $diskName, string $objectKey): array
    {
        $stream = Storage::disk($diskName)->readStream($objectKey);

        if ($stream === false) {
            throw new RuntimeException("Impossible de lire {$objectKey}.");
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'stimergie-sync-');

        if ($tempPath === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw new RuntimeException('Impossible de créer un fichier temporaire.');
        }

        $target = fopen($tempPath, 'w');

        if ($target === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            @unlink($tempPath);

            throw new RuntimeException('Impossible de copier l image en local.');
        }

        stream_copy_to_stream($stream, $target);

        if (is_resource($stream)) {
            fclose($stream);
        }

        fclose($target);

        try {
            $size = @getimagesize($tempPath);
            $mimeType = $size['mime'] ?? (function_exists('mime_content_type') ? mime_content_type($tempPath) : null);

            return [
                'width' => $size ? $size[0] : null,
                'height' => $size ? $size[1] : null,
                'mime_type' => is_string($mimeType) ? $mimeType : null,
                'size_bytes' => filesize($tempPath) ?: null,
                'checksum' => hash_file('sha256', $tempPath),
            ];
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * @param  array{width: int|null, height: int|null, mime_type: string|null, size_bytes: int|null, checksum: string}  $fileData
     * @param  array{original: string, web: string|null}  $pair
     * @return array<string, array{object_key: string, mime_type: string|null, width: int|null, height: int|null, size_bytes: int|null}>
     */
    private function variants(array $fileData, array $pair): array
    {
        return [
            'original' => [
                'object_key' => $pair['original'],
                'mime_type' => $fileData['mime_type'],
                'width' => $fileData['width'],
                'height' => $fileData['height'],
                'size_bytes' => $fileData['size_bytes'],
            ],
            'web' => [
                'object_key' => $pair['web'] ?? $pair['original'],
                'mime_type' => $fileData['mime_type'],
                'width' => $fileData['width'],
                'height' => $fileData['height'],
                'size_bytes' => null,
            ],
            'hd' => [
                'object_key' => $pair['original'],
                'mime_type' => $fileData['mime_type'],
                'width' => $fileData['width'],
                'height' => $fileData['height'],
                'size_bytes' => $fileData['size_bytes'],
            ],
        ];
    }

    private function duplicateByChecksumData(Project $project, string $checksum): bool
    {
        return Image::query()
            ->where('project_id', $project->id)
            ->where('checksum', $checksum)
            ->exists();
    }

    private function titleFromObjectKey(string $objectKey): string
    {
        $title = pathinfo($objectKey, PATHINFO_FILENAME);

        return Str::of($title)
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title()
            ->toString() ?: 'Image synchronisée';
    }

    private function orientation(?int $width, ?int $height): ?string
    {
        if (! $width || ! $height) {
            return null;
        }

        if ($width === $height) {
            return 'square';
        }

        return $width > $height ? 'landscape' : 'portrait';
    }

    private function normalizePath(string $path): string
    {
        $path = rawurldecode($path);
        $path = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $path) ?: $path;
        $path = strtoupper($path);
        $path = preg_replace('/[^A-Z0-9]+/', ' ', $path);

        return trim(preg_replace('/\s+/', ' ', (string) $path));
    }

    private function pathScore(string $expected, string $candidate): int
    {
        if ($expected === $candidate) {
            return 100;
        }

        $expectedTokens = array_unique(explode(' ', $expected));
        $candidateTokens = array_unique(explode(' ', $candidate));
        $commonTokens = array_intersect($expectedTokens, $candidateTokens);
        $tokenScore = count($commonTokens) * 12;

        similar_text($expected, $candidate, $similarity);

        return $tokenScore + (int) round($similarity / 4);
    }
}

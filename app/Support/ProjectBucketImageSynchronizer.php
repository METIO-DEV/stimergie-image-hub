<?php

namespace App\Support;

use App\Models\Image;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectBucketImageSynchronizer
{
    /**
     * @var array<int, string>|null
     */
    private ?array $cachedBucketPrefixes = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $cachedBucketFiles = null;

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
            $pairs = $this->bucketPairs($this->filesForPrefix($disk, $prefix), $prefix);
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
                        'object_key_web' => $pair['web'],
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

        $expectedPrefix = $this->storagePath->prefix($project);
        $resolvedPrefix = $this->resolveExistingPrefix($disk, $expectedPrefix) ?? $expectedPrefix;
        $prefixes[] = $resolvedPrefix;

        return collect($prefixes)->unique()->values()->all();
    }

    private function resolveExistingPrefix($disk, string $expectedPrefix): ?string
    {
        if ($this->filesForPrefix($disk, $expectedPrefix) !== []) {
            return $expectedPrefix;
        }

        $candidates = $this->cachedBucketPrefixes ??= $this->candidatePrefixes($this->allPhotoFiles($disk));

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
     * @return array<int, string>
     */
    private function filesForPrefix($disk, string $prefix): array
    {
        $prefix = trim($prefix, '/');

        if ($prefix === 'photos') {
            return $this->allPhotoFiles($disk);
        }

        if ($this->cachedBucketFiles !== null && Str::startsWith($prefix, 'photos/')) {
            return array_values(array_filter(
                $this->cachedBucketFiles,
                fn (string $file): bool => Str::startsWith($file, "{$prefix}/"),
            ));
        }

        return $disk->allFiles($prefix);
    }

    /**
     * @return array<int, string>
     */
    private function allPhotoFiles($disk): array
    {
        return $this->cachedBucketFiles ??= $disk->allFiles('photos');
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

            if (in_array(Str::lower(basename($directory)), ['jpg', ImageVariantGenerator::WEB_VARIANT_DIRECTORY], true)) {
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
            fn (string $segment) => ! in_array(Str::lower($segment), ['jpg', ImageVariantGenerator::WEB_VARIANT_DIRECTORY], true),
        ));
        $path = trim(implode('/', [...$segments, pathinfo($filename, PATHINFO_FILENAME)]), '/');

        return Str::lower($path);
    }

    private function isWebVariantObject(string $key, string $prefix): bool
    {
        foreach (explode('/', $this->relativePath($key, $prefix)) as $segment) {
            if (in_array(Str::lower($segment), ['jpg', ImageVariantGenerator::WEB_VARIANT_DIRECTORY], true)) {
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
     * @return array{width: int|null, height: int|null, mime_type: string|null, size_bytes: int|null, checksum: string|null}
     */
    private function inspectObject(string $diskName, string $objectKey): array
    {
        $dimensions = $this->localImageDimensions($diskName, $objectKey);
        $sizeBytes = null;

        try {
            $sizeBytes = Storage::disk($diskName)->size($objectKey);
        } catch (\Throwable) {
            $sizeBytes = null;
        }

        return [
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'mime_type' => $this->mimeTypeFromObjectKey($objectKey),
            'size_bytes' => $sizeBytes,
            'checksum' => null,
        ];
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    private function localImageDimensions(string $diskName, string $objectKey): array
    {
        try {
            $path = Storage::disk($diskName)->path($objectKey);
        } catch (\Throwable) {
            return ['width' => null, 'height' => null];
        }

        if (! is_file($path)) {
            return ['width' => null, 'height' => null];
        }

        $size = @getimagesize($path);

        return [
            'width' => is_array($size) ? ($size[0] ?? null) : null,
            'height' => is_array($size) ? ($size[1] ?? null) : null,
        ];
    }

    /**
     * @param  array{width: int|null, height: int|null, mime_type: string|null, size_bytes: int|null, checksum: string|null}  $fileData
     * @param  array{original: string, web: string|null}  $pair
     * @return array<string, array{object_key: string, mime_type: string|null, width: int|null, height: int|null, size_bytes: int|null}>
     */
    private function variants(array $fileData, array $pair): array
    {
        $variants = [
            'original' => [
                'object_key' => $pair['original'],
                'mime_type' => $fileData['mime_type'],
                'width' => $fileData['width'],
                'height' => $fileData['height'],
                'size_bytes' => $fileData['size_bytes'],
            ],
            'hd' => [
                'object_key' => $pair['original'],
                'mime_type' => $fileData['mime_type'],
                'width' => $fileData['width'],
                'height' => $fileData['height'],
                'size_bytes' => $fileData['size_bytes'],
            ],
        ];

        if ($pair['web']) {
            $variants['web'] = [
                'object_key' => $pair['web'],
                'mime_type' => $fileData['mime_type'],
                'width' => $fileData['width'],
                'height' => $fileData['height'],
                'size_bytes' => null,
            ];
        }

        return $variants;
    }

    private function duplicateByChecksumData(Project $project, ?string $checksum): bool
    {
        if (! $checksum) {
            return false;
        }

        return Image::query()
            ->where('project_id', $project->id)
            ->where('checksum', $checksum)
            ->exists();
    }

    private function mimeTypeFromObjectKey(string $objectKey): ?string
    {
        return match (Str::lower(pathinfo($objectKey, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
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
        $path = preg_replace('/\b(\d{2})(\d{2})(\d{2})\b/', '${1}${2}20${3}', $path);
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

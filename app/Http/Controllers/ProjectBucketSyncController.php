<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Project;
use App\Support\ImageVariantGenerator;
use App\Support\ProjectImageStoragePath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProjectBucketSyncController extends Controller
{
    public function __construct(
        private readonly ProjectImageStoragePath $storagePath,
        private readonly ImageVariantGenerator $imageVariants,
    ) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        $project->loadMissing('client');
        $this->authorizeProjectSync($request, $project);

        $diskName = (string) config('filesystems.image_disk', 'scaleway');
        $disk = Storage::disk($diskName);
        $prefix = $this->storagePath->prefix($project);
        $pairs = $this->bucketPairs($disk->allFiles($prefix), $prefix);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($pairs as $pair) {
            $existing = $this->existingImage($project, $pair['original'], $pair['web']);

            if ($existing instanceof Image) {
                if ($this->attachMissingWebVariant($existing, $pair['web'])) {
                    $updated++;
                } else {
                    $skipped++;
                }

                continue;
            }

            $fileData = $this->inspectObject($diskName, $pair['original']);

            $image = DB::transaction(function () use ($diskName, $fileData, $pair, $project, $request): Image {
                $image = Image::create([
                    'client_id' => $project->client_id,
                    'project_id' => $project->id,
                    'created_by' => $request->user()?->id,
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
                        'bucket_prefix' => $this->storagePath->prefix($project),
                    ],
                ]);

                $this->imageVariants->syncImageVariants($image, $this->variants($fileData, $pair));

                return $image;
            });

            if ($this->duplicateByChecksum($project, $image)) {
                $image->delete();
                $skipped++;

                continue;
            }

            $created++;
        }

        return response()->json([
            'prefix' => $prefix,
            'total' => count($pairs),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    private function authorizeProjectSync(Request $request, Project $project): void
    {
        $user = $request->user();

        abort_unless($user?->isSuperAdmin()
            || ($project->client && $user?->hasClientRole($project->client, ['owner', 'manager'])), 403);
    }

    /**
     * @param  array<int, string>  $files
     * @return array<int, array{original: string, web: string|null}>
     */
    private function bucketPairs(array $files, string $prefix): array
    {
        $prefix = trim($prefix, '/');
        $webPrefix = "{$prefix}/JPG/";
        $originals = [];
        $webByBasename = [];

        foreach ($files as $file) {
            $key = trim($file, '/');

            if (! $this->isImageObject($key)) {
                continue;
            }

            if (Str::startsWith($key, $webPrefix)) {
                $webByBasename[$this->basenameIndex($key)] = $key;

                continue;
            }

            $originals[$this->basenameIndex($key)] = $key;
        }

        $pairs = [];

        foreach ($originals as $index => $original) {
            $pairs[] = [
                'original' => $original,
                'web' => $webByBasename[$index] ?? null,
            ];

            unset($webByBasename[$index]);
        }

        foreach ($webByBasename as $web) {
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

    private function basenameIndex(string $key): string
    {
        return Str::lower(pathinfo($key, PATHINFO_FILENAME));
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

    private function duplicateByChecksum(Project $project, Image $image): bool
    {
        if (! $image->checksum) {
            return false;
        }

        return Image::query()
            ->where('project_id', $project->id)
            ->where('checksum', $image->checksum)
            ->whereKeyNot($image->id)
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
}

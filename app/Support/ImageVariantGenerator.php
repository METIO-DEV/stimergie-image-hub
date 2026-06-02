<?php

namespace App\Support;

use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageVariantGenerator
{
    /**
     * @return array{disk: string, original: string, web: string, thumb: string, hd: string, url: string, width: int|null, height: int|null, orientation: string|null, mime_type: string|null, size_bytes: int|null, checksum: string, variants: array<string, array{object_key: string, mime_type: string|null, width: int|null, height: int|null, size_bytes: int|null}>}
     */
    public function store(UploadedFile $file): array
    {
        $disk = (string) config('filesystems.image_disk', 'scaleway');
        $mimeType = $file->getMimeType();
        $extension = $this->extension($file);
        $baseName = (string) Str::uuid();
        $sourcePath = $file->getRealPath();

        if (! $sourcePath) {
            throw new RuntimeException('Image upload failed.');
        }

        $size = @getimagesize($sourcePath);
        $width = $size ? $size[0] : null;
        $height = $size ? $size[1] : null;
        $originalKey = "images/originals/{$baseName}.{$extension}";

        $this->putFile($disk, $originalKey, $sourcePath, $mimeType);

        $variants = [
            'original' => [
                'object_key' => $originalKey,
                'mime_type' => $mimeType,
                'width' => $width,
                'height' => $height,
                'size_bytes' => $file->getSize(),
            ],
        ];

        foreach ([
            'web' => ['directory' => 'images/web', 'max' => 1600],
            'thumb' => ['directory' => 'images/thumbs', 'max' => 480],
            'hd' => ['directory' => 'images/hd', 'max' => 3200],
        ] as $kind => $config) {
            $key = "{$config['directory']}/{$baseName}.{$extension}";
            $variant = $this->putResizedVariant($disk, $key, $sourcePath, $mimeType, $config['max']);
            $variants[$kind] = $variant;
        }

        return [
            'disk' => $disk,
            'original' => $variants['original']['object_key'],
            'web' => $variants['web']['object_key'],
            'thumb' => $variants['thumb']['object_key'],
            'hd' => $variants['hd']['object_key'],
            'url' => Storage::disk($disk)->url($variants['web']['object_key']),
            'width' => $width,
            'height' => $height,
            'orientation' => $this->orientation($width, $height),
            'mime_type' => $mimeType,
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $sourcePath),
            'variants' => $variants,
        ];
    }

    /**
     * @return array{disk: string, original: string, web: string, thumb: string, hd: string, width: int|null, height: int|null, orientation: string|null, mime_type: string|null, size_bytes: int|null, checksum: string, variants: array<string, array{object_key: string, mime_type: string|null, width: int|null, height: int|null, size_bytes: int|null}>}
     */
    public function generateFromOriginal(Image $image, string $targetPrefix = 'images'): array
    {
        if (! $image->object_key_original) {
            throw new RuntimeException("Image {$image->id} sans object_key_original.");
        }

        $disk = $this->disk($image->storage_provider);
        $sourceKey = $image->object_key_original;
        $sourceStream = Storage::disk($disk)->readStream($sourceKey);

        if ($sourceStream === false) {
            throw new RuntimeException("Impossible de lire l original {$sourceKey}.");
        }

        $sourcePath = tempnam(sys_get_temp_dir(), 'stimergie-original-');

        if ($sourcePath === false) {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }

            throw new RuntimeException('Impossible de creer un fichier temporaire original.');
        }

        $targetStream = fopen($sourcePath, 'w');

        if ($targetStream === false) {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }
            @unlink($sourcePath);

            throw new RuntimeException('Impossible de copier l original en local.');
        }

        stream_copy_to_stream($sourceStream, $targetStream);

        if (is_resource($sourceStream)) {
            fclose($sourceStream);
        }

        fclose($targetStream);

        try {
            $mimeType = $image->mime_type ?: $this->detectMimeType($sourcePath);
            $extension = $this->extensionFromKey($sourceKey, $mimeType);
            $size = @getimagesize($sourcePath);
            $width = $size ? $size[0] : null;
            $height = $size ? $size[1] : null;
            $targetPrefix = trim($targetPrefix, '/');

            $variants = [
                'original' => [
                    'object_key' => $sourceKey,
                    'mime_type' => $mimeType,
                    'width' => $width,
                    'height' => $height,
                    'size_bytes' => filesize($sourcePath) ?: null,
                ],
            ];

            foreach ([
                'web' => ['directory' => "{$targetPrefix}/web", 'max' => 1600],
                'thumb' => ['directory' => "{$targetPrefix}/thumbs", 'max' => 480],
                'hd' => ['directory' => "{$targetPrefix}/hd", 'max' => 3200],
            ] as $kind => $config) {
                $key = "{$config['directory']}/{$image->id}.{$extension}";
                $variants[$kind] = $this->putResizedVariant($disk, $key, $sourcePath, $mimeType, $config['max']);
            }

            return [
                'disk' => $disk,
                'original' => $variants['original']['object_key'],
                'web' => $variants['web']['object_key'],
                'thumb' => $variants['thumb']['object_key'],
                'hd' => $variants['hd']['object_key'],
                'width' => $width,
                'height' => $height,
                'orientation' => $this->orientation($width, $height),
                'mime_type' => $mimeType,
                'size_bytes' => filesize($sourcePath) ?: null,
                'checksum' => hash_file('sha256', $sourcePath),
                'variants' => $variants,
            ];
        } finally {
            @unlink($sourcePath);
        }
    }

    /**
     * @param  array<string, array{object_key: string, mime_type: string|null, width: int|null, height: int|null, size_bytes: int|null}>  $variants
     */
    public function syncImageVariants(Image $image, array $variants): void
    {
        foreach ($variants as $kind => $variant) {
            $image->variants()->updateOrCreate(
                ['kind' => $kind],
                [
                    'object_key' => $variant['object_key'],
                    'mime_type' => $variant['mime_type'],
                    'width' => $variant['width'],
                    'height' => $variant['height'],
                    'size_bytes' => $variant['size_bytes'],
                ],
            );
        }
    }

    private function putResizedVariant(string $disk, string $key, string $sourcePath, ?string $mimeType, int $maxSize): array
    {
        $source = $this->createImageResource($sourcePath, $mimeType);

        if (! $source) {
            $this->putFile($disk, $key, $sourcePath, $mimeType);
            $size = @getimagesize($sourcePath);

            return [
                'object_key' => $key,
                'mime_type' => $mimeType,
                'width' => $size ? $size[0] : null,
                'height' => $size ? $size[1] : null,
                'size_bytes' => filesize($sourcePath) ?: null,
            ];
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $ratio = min(1, $maxSize / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $ratio));
        $targetHeight = max(1, (int) round($sourceHeight * $ratio));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if (in_array($mimeType, ['image/png', 'image/webp'], true)) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        $tempPath = tempnam(sys_get_temp_dir(), 'stimergie-variant-');

        if ($tempPath === false) {
            imagedestroy($source);
            imagedestroy($target);
            throw new RuntimeException('Impossible de creer une variante image.');
        }

        $this->writeImageResource($target, $tempPath, $mimeType);
        $this->putFile($disk, $key, $tempPath, $mimeType);
        $sizeBytes = filesize($tempPath) ?: null;

        @unlink($tempPath);
        imagedestroy($source);
        imagedestroy($target);

        return [
            'object_key' => $key,
            'mime_type' => $mimeType,
            'width' => $targetWidth,
            'height' => $targetHeight,
            'size_bytes' => $sizeBytes,
        ];
    }

    private function putFile(string $disk, string $key, string $path, ?string $mimeType): void
    {
        $stream = fopen($path, 'r');

        if ($stream === false) {
            throw new RuntimeException('Impossible de lire le fichier image.');
        }

        Storage::disk($disk)->put($key, $stream, [
            'visibility' => 'public',
            'ContentType' => $mimeType ?: 'application/octet-stream',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    private function createImageResource(string $sourcePath, ?string $mimeType): \GdImage|false
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
    }

    private function writeImageResource(\GdImage $image, string $targetPath, ?string $mimeType): void
    {
        match ($mimeType) {
            'image/png' => imagepng($image, $targetPath, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $targetPath, 82) : imagejpeg($image, $targetPath, 82),
            default => imagejpeg($image, $targetPath, 82),
        };
    }

    private function extension(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => $file->extension() ?: 'jpg',
        };
    }

    private function extensionFromKey(string $key, ?string $mimeType): string
    {
        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));

        if ($extension !== '') {
            return $extension === 'jpeg' ? 'jpg' : $extension;
        }

        return match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function detectMimeType(string $path): ?string
    {
        $mimeType = function_exists('mime_content_type') ? mime_content_type($path) : false;

        return is_string($mimeType) ? $mimeType : null;
    }

    private function disk(?string $provider): string
    {
        return match ($provider) {
            'public' => 'public',
            'local' => 'local',
            default => 'scaleway',
        };
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

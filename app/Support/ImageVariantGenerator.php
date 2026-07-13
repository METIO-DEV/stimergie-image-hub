<?php

namespace App\Support;

use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageVariantGenerator
{
    public const WEB_VARIANT_DIRECTORY = 'web';

    public const THUMBNAIL_VARIANT_DIRECTORY = 'miniatures';

    public const HD_VARIANT_DIRECTORY = 'hd';

    private const WEB_MAX_SIZE = 1600;

    private const WEB_QUALITY = 82;

    public const THUMBNAIL_MAX_SIZE = 640;

    private const THUMBNAIL_QUALITY = 76;

    public function __construct(private readonly ObjectStoragePolicy $storagePolicy) {}

    /**
     * @return array{disk: string, original: string, web: string, thumb: string|null, hd: string, url: string, width: int|null, height: int|null, orientation: string|null, mime_type: string|null, size_bytes: int|null, checksum: string, variants: array<string, array{object_key: string, mime_type: string|null, width: int|null, height: int|null, size_bytes: int|null}>}
     */
    public function store(UploadedFile $file, string $targetPrefix = 'images'): array
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
        $targetPrefix = trim($targetPrefix, '/') ?: 'images';
        $originalKey = "{$targetPrefix}/".self::HD_VARIANT_DIRECTORY."/{$baseName}.{$extension}";

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

        $variants['thumb'] = $this->putResizedVariant($disk, "{$targetPrefix}/".self::THUMBNAIL_VARIANT_DIRECTORY."/{$baseName}.{$extension}", $sourcePath, $mimeType, self::THUMBNAIL_MAX_SIZE, self::THUMBNAIL_QUALITY);
        $variants['web'] = $this->putResizedVariant($disk, "{$targetPrefix}/".self::WEB_VARIANT_DIRECTORY."/{$baseName}.{$extension}", $sourcePath, $mimeType, self::WEB_MAX_SIZE, self::WEB_QUALITY);
        $variants['hd'] = $variants['original'];

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
     * @return array{disk: string, original: string, web: string, thumb: string|null, hd: string, width: int|null, height: int|null, orientation: string|null, mime_type: string|null, size_bytes: int|null, checksum: string, variants: array<string, array{object_key: string, mime_type: string|null, width: int|null, height: int|null, size_bytes: int|null}>}
     */
    public function generateFromOriginal(
        Image $image,
        string $targetPrefix = 'images',
        bool $generateWeb = true,
        bool $generateThumb = true,
        bool $generateHd = true,
        ?string $webTargetKey = null,
        ?string $thumbTargetKey = null,
        ?string $hdTargetKey = null,
    ): array {
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

            $originalVariant = [
                'object_key' => $sourceKey,
                'mime_type' => $mimeType,
                'width' => $width,
                'height' => $height,
                'size_bytes' => filesize($sourcePath) ?: null,
            ];

            $variants = [
                'original' => $originalVariant,
            ];

            if ($generateThumb) {
                $variants['thumb'] = $this->putResizedVariant(
                    $disk,
                    $thumbTargetKey ?: "{$targetPrefix}/".self::THUMBNAIL_VARIANT_DIRECTORY."/{$image->id}.{$extension}",
                    $sourcePath,
                    $mimeType,
                    self::THUMBNAIL_MAX_SIZE,
                    self::THUMBNAIL_QUALITY,
                );
            } elseif ($image->object_key_thumb) {
                $variants['thumb'] = $this->existingVariantData($image->object_key_thumb, $mimeType);
            }

            if ($generateWeb) {
                $variants['web'] = $this->putResizedVariant(
                    $disk,
                    $webTargetKey ?: "{$targetPrefix}/".self::WEB_VARIANT_DIRECTORY."/{$image->id}.{$extension}",
                    $sourcePath,
                    $mimeType,
                    self::WEB_MAX_SIZE,
                    self::WEB_QUALITY,
                );
            } elseif ($image->object_key_web) {
                $variants['web'] = $this->existingVariantData($image->object_key_web, $mimeType, $image->width, $image->height);
            }

            if ($generateHd) {
                $hdKey = $hdTargetKey ?: "{$targetPrefix}/".self::HD_VARIANT_DIRECTORY."/{$image->id}.{$extension}";
                $this->putFile($disk, $hdKey, $sourcePath, $mimeType);

                $variants['hd'] = [
                    ...$originalVariant,
                    'object_key' => $hdKey,
                ];
                $variants['original'] = $variants['hd'];
            } else {
                $variants['hd'] = $image->object_key_hd
                    ? $this->existingVariantData($image->object_key_hd, $mimeType, $width, $height)
                    : $variants['original'];
            }

            return [
                'disk' => $disk,
                'original' => $variants['original']['object_key'],
                'web' => $variants['web']['object_key'] ?? $image->object_key_web,
                'thumb' => $variants['thumb']['object_key'] ?? $image->object_key_thumb,
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

    private function putResizedVariant(string $disk, string $key, string $sourcePath, ?string $mimeType, int $maxSize, int $quality): array
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

        $this->writeImageResource($target, $tempPath, $mimeType, $quality);
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

    private function existingVariantData(
        string $objectKey,
        ?string $mimeType,
        ?int $width = null,
        ?int $height = null,
    ): array {
        return [
            'object_key' => $objectKey,
            'mime_type' => $mimeType,
            'width' => $width,
            'height' => $height,
            'size_bytes' => null,
        ];
    }

    private function putFile(string $disk, string $key, string $path, ?string $mimeType): void
    {
        $stream = fopen($path, 'r');

        if ($stream === false) {
            throw new RuntimeException('Impossible de lire le fichier image.');
        }

        Storage::disk($disk)->put($key, $stream, $this->storagePolicy->putOptions($key, $mimeType));

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

    private function writeImageResource(\GdImage $image, string $targetPath, ?string $mimeType, int $quality): void
    {
        match ($mimeType) {
            'image/png' => imagepng($image, $targetPath, $quality <= self::THUMBNAIL_QUALITY ? 8 : 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $targetPath, $quality) : imagejpeg($image, $targetPath, $quality),
            default => imagejpeg($image, $targetPath, $quality),
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

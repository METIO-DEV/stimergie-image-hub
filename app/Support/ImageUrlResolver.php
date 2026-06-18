<?php

namespace App\Support;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

class ImageUrlResolver
{
    public const TEMPORARY_ASSET_URL_TTL_MINUTES = 10;

    public function thumbnailUrl(Image $image): ?string
    {
        $thumbnailKey = $this->variantObjectKey($image, 'thumb') ?: $image->object_key_thumb;

        if ($thumbnailKey) {
            return $this->url($image->storage_provider, $thumbnailKey);
        }

        $webKey = $this->variantObjectKey($image, 'web') ?: $image->object_key_web;

        if ($webKey && ! $this->isOriginalKey($image, $webKey)) {
            return $this->url($image->storage_provider, $webKey);
        }

        return null;
    }

    public function displayUrl(Image $image): ?string
    {
        $webKey = $this->variantObjectKey($image, 'web') ?: $image->object_key_web;

        return $this->url(
            $image->storage_provider,
            $webKey ?: $image->object_key_original ?: $this->variantObjectKey($image, 'thumb') ?: $image->object_key_thumb,
        );
    }

    public function temporaryThumbnailUrl(Image $image): ?string
    {
        return $this->temporaryAssetUrl($image, 'thumb');
    }

    public function temporaryDisplayUrl(Image $image): ?string
    {
        return $this->temporaryAssetUrl($image, 'display');
    }

    public function temporarySharedAlbumThumbnailUrl(string $shareKey, Image $image): ?string
    {
        return $this->temporarySharedAlbumAssetUrl($shareKey, $image, 'thumb');
    }

    public function temporarySharedAlbumDisplayUrl(string $shareKey, Image $image): ?string
    {
        return $this->temporarySharedAlbumAssetUrl($shareKey, $image, 'display');
    }

    /**
     * @return array{provider: string|null, disk: string, objectKey: string|null}
     */
    public function assetSource(Image $image, string $variant): array
    {
        $objectKey = match ($variant) {
            'thumb' => $this->thumbnailObjectKey($image),
            'web', 'display' => $this->displayObjectKey($image),
            default => null,
        };

        return [
            'provider' => $image->storage_provider,
            'disk' => $this->disk($image->storage_provider),
            'objectKey' => $objectKey,
        ];
    }

    private function variantObjectKey(Image $image, string $kind): ?string
    {
        if (! $image->relationLoaded('variants')) {
            return null;
        }

        $objectKey = $image->variants
            ->firstWhere('kind', $kind)
            ?->object_key;

        return is_string($objectKey) && $objectKey !== '' ? $objectKey : null;
    }

    private function isOriginalKey(Image $image, string $objectKey): bool
    {
        return in_array($objectKey, array_filter([
            $image->object_key_original,
            $image->object_key_hd,
        ]), true);
    }

    private function temporaryAssetUrl(Image $image, string $variant): ?string
    {
        if (! $this->assetSource($image, $variant)['objectKey']) {
            return null;
        }

        return URL::temporarySignedRoute(
            'images.asset',
            now()->addMinutes(self::TEMPORARY_ASSET_URL_TTL_MINUTES),
            ['image' => $image, 'variant' => $variant],
        );
    }

    private function temporarySharedAlbumAssetUrl(string $shareKey, Image $image, string $variant): ?string
    {
        if (! $this->assetSource($image, $variant)['objectKey']) {
            return null;
        }

        return URL::temporarySignedRoute(
            'shared-albums.images.asset',
            now()->addMinutes(self::TEMPORARY_ASSET_URL_TTL_MINUTES),
            ['shareKey' => $shareKey, 'image' => $image, 'variant' => $variant],
        );
    }

    private function thumbnailObjectKey(Image $image): ?string
    {
        $thumbnailKey = $this->variantObjectKey($image, 'thumb') ?: $image->object_key_thumb;

        if ($thumbnailKey) {
            return $thumbnailKey;
        }

        $webKey = $this->variantObjectKey($image, 'web') ?: $image->object_key_web;

        return $webKey && ! $this->isOriginalKey($image, $webKey) ? $webKey : null;
    }

    private function displayObjectKey(Image $image): ?string
    {
        $webKey = $this->variantObjectKey($image, 'web') ?: $image->object_key_web;

        return $webKey ?: $image->object_key_original ?: $this->variantObjectKey($image, 'thumb') ?: $image->object_key_thumb;
    }

    public function downloadUrl(Image $image): ?string
    {
        return $this->downloadUrlForVariant($image, 'hd');
    }

    public function downloadUrlForVariant(Image $image, string $variant): ?string
    {
        $source = $this->downloadSource($image, $variant);

        return $this->url(
            $source['provider'],
            $source['objectKey'],
        );
    }

    /**
     * @return array{provider: string|null, disk: string, objectKey: string|null}
     */
    public function downloadSource(Image $image, string $variant): array
    {
        $objectKey = match ($variant) {
            'web', 'sd' => $image->object_key_web ?: $image->object_key_original ?: $image->object_key_thumb,
            default => $image->object_key_hd ?: $image->object_key_original ?: $image->object_key_web,
        };

        return [
            'provider' => $image->storage_provider,
            'disk' => $this->disk($image->storage_provider),
            'objectKey' => $objectKey,
        ];
    }

    private function url(?string $provider, ?string $objectKey): ?string
    {
        if (! $objectKey) {
            return null;
        }

        try {
            return Storage::disk($this->disk($provider))->url($objectKey);
        } catch (Throwable) {
            return null;
        }
    }

    public function disk(?string $provider): string
    {
        return match ($provider) {
            'public' => 'public',
            'local' => 'local',
            default => 'scaleway',
        };
    }
}

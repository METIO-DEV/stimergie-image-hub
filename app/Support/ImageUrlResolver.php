<?php

namespace App\Support;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImageUrlResolver
{
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

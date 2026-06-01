<?php

namespace App\Support;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImageUrlResolver
{
    public function thumbnailUrl(Image $image): ?string
    {
        return $this->url(
            $image->storage_provider,
            $image->object_key_thumb ?: $image->object_key_web ?: $image->object_key_original,
            $image->legacy_thumbnail_url ?: $image->legacy_url,
        );
    }

    public function displayUrl(Image $image): ?string
    {
        return $this->url(
            $image->storage_provider,
            $image->object_key_web ?: $image->object_key_original ?: $image->object_key_thumb,
            $image->legacy_url ?: $image->legacy_thumbnail_url,
        );
    }

    public function downloadUrl(Image $image): ?string
    {
        return $this->url(
            $image->storage_provider,
            $image->object_key_hd ?: $image->object_key_original ?: $image->object_key_web,
            $image->legacy_url ?: $image->legacy_thumbnail_url,
        );
    }

    private function url(?string $provider, ?string $objectKey, ?string $fallback): ?string
    {
        if (! $objectKey) {
            return $fallback;
        }

        try {
            return Storage::disk($this->disk($provider))->url($objectKey);
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function disk(?string $provider): string
    {
        return match ($provider) {
            'public' => 'public',
            'local' => 'local',
            default => 'scaleway',
        };
    }
}

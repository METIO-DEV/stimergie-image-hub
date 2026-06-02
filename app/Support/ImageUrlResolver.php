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
        );
    }

    public function displayUrl(Image $image): ?string
    {
        return $this->url(
            $image->storage_provider,
            $image->object_key_web ?: $image->object_key_original ?: $image->object_key_thumb,
        );
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

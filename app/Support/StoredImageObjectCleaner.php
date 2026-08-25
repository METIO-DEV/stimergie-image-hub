<?php

namespace App\Support;

use App\Models\Image;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class StoredImageObjectCleaner
{
    /**
     * @param  Collection<int, Image>  $images
     */
    public function deleteImageObjects(Collection $images): void
    {
        $images
            ->groupBy(fn (Image $image) => $this->disk($image->storage_provider))
            ->each(function (Collection $diskImages, string $disk): void {
                $keys = $diskImages
                    ->flatMap(fn (Image $image) => [
                        $image->object_key_original,
                        $image->object_key_web,
                        $image->object_key_thumb,
                        $image->object_key_hd,
                    ])
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($keys !== []) {
                    Storage::disk($disk)->delete($keys);
                }
            });
    }

    private function disk(?string $provider): string
    {
        return match ($provider) {
            'public' => 'public',
            'local' => 'local',
            default => (string) config('filesystems.image_disk', 'scaleway'),
        };
    }
}

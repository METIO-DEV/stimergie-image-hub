<?php

namespace App\Support;

use App\Models\Image;
use App\Models\Tag;
use Illuminate\Support\Str;

class ImageTagSyncer
{
    public function __construct(private readonly ImageTagNormalizer $normalizer) {}

    /**
     * @param  array<int, mixed>  $tags
     */
    public function syncArray(Image $image, array $tags, int $limit = 50): void
    {
        $this->syncNormalized($image, $this->normalizer->normalizeArray($tags, $limit));
    }

    public function syncString(Image $image, ?string $tags, int $limit = 50): void
    {
        $this->syncNormalized($image, $this->normalizer->normalizeString($tags, $limit));
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function syncNormalized(Image $image, array $tags): void
    {
        $tagIds = collect($tags)
            ->map(function (string $tag) {
                return Tag::query()->firstOrCreate(
                    ['slug' => Str::slug($tag)],
                    ['name' => $tag],
                )->id;
            });

        $image->tags()->sync($tagIds);
    }
}

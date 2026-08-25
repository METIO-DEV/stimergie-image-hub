<?php

namespace App\Support;

use Illuminate\Support\Str;

class ImageTagNormalizer
{
    /**
     * @param  array<int, mixed>  $tags
     * @return array<int, string>
     */
    public function normalizeArray(array $tags, int $limit = 12): array
    {
        return collect($tags)
            ->map(fn (mixed $tag) => $this->normalizeTag((string) $tag))
            ->filter()
            ->unique(fn (string $tag) => Str::lower($tag))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function normalizeString(?string $tags, int $limit = 50): array
    {
        return $this->normalizeArray(
            preg_split('/[,;\n]+/', $tags ?? '') ?: [],
            $limit,
        );
    }

    private function normalizeTag(string $tag): string
    {
        return Str::of($tag)
            ->replaceMatches('/^[#"\'+\s•-]+|[#"\'+\s•-]+$/u', '')
            ->replaceMatches('/\s+/u', ' ')
            ->squish()
            ->lower()
            ->toString();
    }
}

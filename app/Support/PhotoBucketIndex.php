<?php

namespace App\Support;

class PhotoBucketIndex
{
    /**
     * @var array<string, true>
     */
    private array $exactKeys;

    /**
     * @var array<string, list<string>>
     */
    private array $keysByBasename = [];

    /**
     * @param  list<string>  $keys
     */
    public function __construct(array $keys)
    {
        $keys = array_values(array_filter($keys, fn (string $key) => ! str_starts_with(basename($key), '.')));

        $this->exactKeys = array_fill_keys($keys, true);

        foreach ($keys as $key) {
            $basename = $this->normalizeBasename($key);

            if ($basename === '') {
                continue;
            }

            $this->keysByBasename[$basename][] = $key;
        }
    }

    public function has(string $key): bool
    {
        return $this->resolve($key) !== null;
    }

    public function resolve(string $key): ?string
    {
        if (isset($this->exactKeys[$key])) {
            return $key;
        }

        $candidates = $this->keysByBasename[$this->normalizeBasename($key)] ?? [];

        if ($candidates === []) {
            return null;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $requestedFolder = $this->normalizeFolder($key);
        $requestedHasJpgFolder = str_contains($this->folderPath($key), '/JPG/');
        $best = null;
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $score = $this->folderScore($requestedFolder, $this->normalizeFolder($candidate));

            if ($requestedHasJpgFolder === str_contains($this->folderPath($candidate), '/JPG/')) {
                $score += 30;
            }

            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $bestScore >= 40 ? $best : null;
    }

    public function resolveVariant(string $key, string $variant): ?string
    {
        $candidates = $this->keysByBasename[$this->normalizeBasename($key)] ?? [];

        if ($candidates === []) {
            return null;
        }

        $preferJpgFolder = $variant === 'web';
        $requestedFolder = $this->normalizeFolder($key);
        $best = null;
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $candidateHasJpgFolder = str_contains($this->folderPath($candidate), '/JPG/');
            $score = $this->folderScore($requestedFolder, $this->normalizeFolder($candidate));
            $score += $candidateHasJpgFolder === $preferJpgFolder ? 40 : 0;

            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $bestScore >= 40 ? $best : null;
    }

    private function normalizeBasename(string $key): string
    {
        return $this->normalize(pathinfo($key, PATHINFO_FILENAME)).'.'.strtolower(pathinfo($key, PATHINFO_EXTENSION));
    }

    private function normalizeFolder(string $key): string
    {
        return $this->normalize($this->folderPath($key));
    }

    private function folderPath(string $key): string
    {
        return '/'.trim(dirname($key), '/').'/';
    }

    private function normalize(string $value): string
    {
        $value = rawurldecode($value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }

    private function folderScore(string $requested, string $candidate): int
    {
        if ($requested === $candidate) {
            return 100;
        }

        $requestedTokens = array_unique(explode(' ', $requested));
        $candidateTokens = array_unique(explode(' ', $candidate));
        $commonTokens = array_intersect($requestedTokens, $candidateTokens);
        $tokenScore = count($commonTokens) * 12;

        similar_text($requested, $candidate, $similarity);

        return $tokenScore + (int) round($similarity / 4);
    }
}

<?php

namespace App\Support;

use App\Models\Image;

class LegacyPhotoObjectKeyResolver
{
    public function resolve(Image $image, string $prefix = 'photos', string $strategy = 'legacy-url', bool $allowBasenameFallback = false): ?string
    {
        if ($strategy === 'legacy-id') {
            return $image->legacy_id ? $this->normalizeKey("{$prefix}/{$image->legacy_id}/original.jpg") : null;
        }

        return $this->resolveFromLegacyUrl($image->legacy_url, $prefix, $allowBasenameFallback);
    }

    public function resolveFromLegacyUrl(?string $legacyUrl, string $prefix = 'photos', bool $allowBasenameFallback = false): ?string
    {
        $path = $this->urlPath($legacyUrl);

        if ($path === null) {
            return null;
        }

        $path = $this->normalizeKey($path);
        $prefix = $this->normalizeKey($prefix);

        if ($path === null || $prefix === null) {
            return null;
        }

        if (str_starts_with($path, "{$prefix}/")) {
            return $path;
        }

        $offset = strpos("/{$path}", "/{$prefix}/");

        if ($offset !== false) {
            return substr("/{$path}", $offset + 1);
        }

        if (! $allowBasenameFallback) {
            return null;
        }

        $basename = trim(basename($path));

        return $basename !== '' ? "{$prefix}/{$basename}" : null;
    }

    private function urlPath(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);

        if (! is_string($path) || trim($path) === '') {
            return $value;
        }

        return $path;
    }

    private function normalizeKey(string $key): ?string
    {
        $key = rawurldecode($key);
        $key = str_replace('\\', '/', $key);
        $key = preg_replace('#/+#', '/', $key);
        $key = trim((string) $key, "/ \t\n\r\0\x0B");

        return $key !== '' ? $key : null;
    }
}

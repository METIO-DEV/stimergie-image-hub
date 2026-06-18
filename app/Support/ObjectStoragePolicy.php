<?php

namespace App\Support;

class ObjectStoragePolicy
{
    public const IMAGE_CACHE_CONTROL = 'private, max-age=600';

    public const PUBLIC_ASSET_CACHE_CONTROL = 'public, max-age=31536000, immutable';

    /**
     * @return array{visibility: string, ContentType: string, CacheControl: string}
     */
    public function putOptions(string $objectKey, ?string $mimeType): array
    {
        return [
            'visibility' => $this->visibility($objectKey),
            'ContentType' => $mimeType ?: 'application/octet-stream',
            'CacheControl' => $this->cacheControl($objectKey),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function temporaryResponseOptions(?string $contentType = null, ?string $contentDisposition = null): array
    {
        return array_filter([
            'ResponseCacheControl' => self::IMAGE_CACHE_CONTROL,
            'ResponseContentType' => $contentType,
            'ResponseContentDisposition' => $contentDisposition,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    public function visibility(string $objectKey): string
    {
        return str_starts_with($objectKey, 'clients/') ? 'public' : 'private';
    }

    public function cacheControl(string $objectKey): string
    {
        return str_starts_with($objectKey, 'clients/')
            ? self::PUBLIC_ASSET_CACHE_CONTROL
            : self::IMAGE_CACHE_CONTROL;
    }
}

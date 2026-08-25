<?php

namespace Tests\Unit;

use App\Support\ObjectStoragePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ObjectStoragePolicyTest extends TestCase
{
    public function test_keeps_client_logos_public_with_long_cache(): void
    {
        $policy = new ObjectStoragePolicy;

        $this->assertSame([
            'visibility' => 'public',
            'ContentType' => 'image/svg+xml',
            'CacheControl' => ObjectStoragePolicy::PUBLIC_ASSET_CACHE_CONTROL,
        ], $policy->putOptions('clients/12/logo.svg', 'image/svg+xml'));
    }

    #[DataProvider('imageObjectKeys')]
    public function test_stores_image_objects_privately_with_short_cache(string $key): void
    {
        $policy = new ObjectStoragePolicy;

        $this->assertSame([
            'visibility' => 'private',
            'ContentType' => 'image/jpeg',
            'CacheControl' => ObjectStoragePolicy::IMAGE_CACHE_CONTROL,
        ], $policy->putOptions($key, 'image/jpeg'));
    }

    public function test_adds_short_private_cache_to_temporary_response_options(): void
    {
        $policy = new ObjectStoragePolicy;

        $this->assertSame([
            'ResponseCacheControl' => ObjectStoragePolicy::IMAGE_CACHE_CONTROL,
            'ResponseContentType' => 'application/zip',
        ], $policy->temporaryResponseOptions('application/zip'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function imageObjectKeys(): array
    {
        return [
            'project photos' => ['photos/client/project/image.jpg'],
            'generated variants' => ['images/JPG/image.jpg'],
        ];
    }
}

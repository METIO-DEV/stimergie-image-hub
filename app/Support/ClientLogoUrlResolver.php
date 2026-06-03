<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ClientLogoUrlResolver
{
    /**
     * @var array<string, string>|null
     */
    private ?array $legacyLogoObjectKeysBySlug = null;

    public function url(Client $client): ?string
    {
        $objectKey = $client->logo_object_key ?: $this->legacyLogoObjectKey($client);

        if (! $objectKey) {
            return null;
        }

        return $this->objectUrl($objectKey);
    }

    private function objectUrl(string $objectKey): ?string
    {
        try {
            return Storage::disk((string) config('filesystems.image_disk', 'scaleway'))
                ->url($objectKey);
        } catch (Throwable) {
            return null;
        }
    }

    private function legacyLogoObjectKey(Client $client): ?string
    {
        $slug = Str::slug($client->slug ?: $client->name);

        if ($slug === '') {
            return null;
        }

        return $this->legacyLogoObjectKeysBySlug()[$slug] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function legacyLogoObjectKeysBySlug(): array
    {
        if ($this->legacyLogoObjectKeysBySlug !== null) {
            return $this->legacyLogoObjectKeysBySlug;
        }

        try {
            $files = Storage::disk((string) config('filesystems.image_disk', 'scaleway'))->allFiles('clients');
        } catch (Throwable) {
            return $this->legacyLogoObjectKeysBySlug = [];
        }

        $logos = [];

        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);

            if (! Str::startsWith($filename, 'logo-legacy-')) {
                continue;
            }

            $slug = Str::after($filename, 'logo-legacy-');

            if ($slug !== '') {
                $logos[$slug] = $file;
            }
        }

        return $this->legacyLogoObjectKeysBySlug = $logos;
    }
}

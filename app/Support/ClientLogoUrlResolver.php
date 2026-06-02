<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ClientLogoUrlResolver
{
    public function url(Client $client): ?string
    {
        if (! $client->logo_object_key) {
            return null;
        }

        try {
            return Storage::disk((string) config('filesystems.image_disk', 'scaleway'))
                ->url($client->logo_object_key);
        } catch (Throwable) {
            return null;
        }
    }
}

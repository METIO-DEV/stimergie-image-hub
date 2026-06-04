<?php

namespace App\Support;

use App\Models\SharedAlbum;

class SharedAlbumInvitationMailer
{
    public function __construct(private readonly BrevoTemplateMailer $brevo) {}

    public function send(SharedAlbum $album, string $recipient): bool
    {
        return $this->brevo->send('shared_album_invitation', [
            ['email' => $recipient],
        ], [
            'album_name' => $album->name,
            'album_description' => $album->description,
            'message' => $album->metadata['message'] ?? null,
            'share_url' => route('shared-albums.show', $album->share_key),
            'starts_at' => $album->starts_at?->toDateString(),
            'expires_at' => $album->expires_at?->toDateString(),
        ]);
    }
}

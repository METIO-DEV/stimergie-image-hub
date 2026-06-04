<?php

namespace App\Mail;

use App\Models\SharedAlbum;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SharedAlbumInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SharedAlbum $album) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Album photo partagé : {$this->album->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shared-album-invitation',
            with: [
                'album' => $this->album,
                'shareUrl' => route('shared-albums.show', $this->album->share_key),
                'message' => $this->album->metadata['message'] ?? null,
            ],
        );
    }
}

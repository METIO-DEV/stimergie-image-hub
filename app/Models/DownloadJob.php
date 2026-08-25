<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadJob extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_hd' => 'boolean',
            'download_url_expires_at' => 'datetime',
            'processed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

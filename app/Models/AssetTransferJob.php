<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTransferJob extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'folders' => 'array',
            'completed_folders' => 'array',
            'failed_folder_details' => 'array',
            'metadata' => 'array',
            'cancel_requested_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'running', 'cancelling'], true);
    }
}

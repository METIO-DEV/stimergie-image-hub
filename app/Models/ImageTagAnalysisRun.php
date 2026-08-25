<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageTagAnalysisRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'image_ids' => 'array',
            'metadata' => 'array',
        ];
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function currentImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'current_image_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Image extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ImageVariant::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function sharedClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'image_client_shares')
            ->withPivot(['created_by', 'expires_at'])
            ->withTimestamps();
    }
}

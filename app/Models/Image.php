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
            'rights_starts_at' => 'date',
            'rights_ends_at' => 'date',
            'rights_extension_requested_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function rightsStatus(): string
    {
        if (! $this->rights_ends_at) {
            return 'unlimited';
        }

        if ($this->rights_ends_at->lt(today())) {
            return 'expired';
        }

        if ($this->rights_ends_at->lte(now()->addDays(30))) {
            return 'expiring_soon';
        }

        return 'active';
    }

    public function rightsAreExpired(): bool
    {
        return $this->rightsStatus() === 'expired';
    }

    public function canRequestRightsExtension(): bool
    {
        return in_array($this->rightsStatus(), ['expired', 'expiring_soon'], true)
            && $this->rights_extension_requested_at === null;
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

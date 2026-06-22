<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        if (! in_array($this->rightsStatus(), ['expired', 'expiring_soon'], true)) {
            return false;
        }

        $request = $this->currentRightsExtensionRequest();

        if ($request instanceof ImageRightsExtensionRequest) {
            return $request->isClosed() && $request->status === ImageRightsExtensionRequest::STATUS_REFUSED;
        }

        return $this->rights_extension_requested_at === null;
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

    public function rightsExtensionRequests(): HasMany
    {
        return $this->hasMany(ImageRightsExtensionRequest::class);
    }

    public function latestRightsExtensionRequest(): HasOne
    {
        return $this->hasOne(ImageRightsExtensionRequest::class)->latestOfMany();
    }

    public function rightsExtensionRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rights_extension_requested_by');
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

    private function currentRightsExtensionRequest(): ?ImageRightsExtensionRequest
    {
        if ($this->relationLoaded('latestRightsExtensionRequest')) {
            return $this->latestRightsExtensionRequest;
        }

        return $this->latestRightsExtensionRequest()->first();
    }
}

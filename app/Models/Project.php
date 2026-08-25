<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function accessPeriods(): HasMany
    {
        return $this->hasMany(ProjectAccessPeriod::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClientMembership::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageVariant extends Model
{
    protected $guarded = [];

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageRightsExtensionRequest extends Model
{
    public const STATUS_REQUESTED = 'demande';

    public const STATUS_IN_PROGRESS = 'en_cours';

    public const STATUS_ACCEPTED = 'accepte';

    public const STATUS_REFUSED = 'refuse';

    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_ACCEPTED,
        self::STATUS_REFUSED,
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rights_ends_at' => 'date',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_ACCEPTED => 'Acceptée',
            self::STATUS_REFUSED => 'Refusée',
            default => 'Demandée',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_REFUSED], true);
    }
}

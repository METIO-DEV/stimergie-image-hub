<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function refreshProgress(): void
    {
        $items = $this->items()
            ->selectRaw("
                count(*) as uploaded,
                sum(case when status in ('done', 'duplicate') then 1 else 0 end) as processed,
                sum(case when status = 'failed' then 1 else 0 end) as failed,
                sum(case when status = 'duplicate' then 1 else 0 end) as duplicates
            ")
            ->first();

        $uploaded = (int) ($items?->uploaded ?? 0);
        $processed = (int) ($items?->processed ?? 0);
        $failed = (int) ($items?->failed ?? 0);
        $duplicates = (int) ($items?->duplicates ?? 0);
        $terminal = $processed + $failed;
        $total = max((int) $this->total_items, $uploaded);

        $status = 'processing';
        $finishedAt = null;

        if ($total > 0 && $terminal >= $total) {
            $status = $failed > 0 ? 'failed' : 'completed';
            $finishedAt = now();
        }

        $this->forceFill([
            'status' => $status,
            'uploaded_items' => $uploaded,
            'processed_items' => $processed,
            'failed_items' => $failed,
            'duplicate_items' => $duplicates,
            'finished_at' => $finishedAt,
        ])->save();
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ImportItem::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Clearance extends Model
{
    public const TYPES = ['EC', 'CTE', 'CTO'];

    protected $guarded = ['id'];

    protected $casts = [
        'applied_on' => 'date',
        'received_on' => 'date',
        'valid_until' => 'date',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, User $user): \Illuminate\Database\Eloquent\Builder
    {
        $ids = $user->visibleBlockIds();

        return $ids === null ? $query : $query->whereIn('block_id', $ids);
    }

    /**
     * Label shown in the portal: Valid / Expired / Pending / Not applied / NA / Closed.
     */
    public function displayStatus(): string
    {
        return match (true) {
            $this->status === 'received' && $this->isSiteClosed() => 'Closed',
            $this->status === 'received' && $this->valid_until?->lt(today()) => 'Expired',
            $this->status === 'received' => 'Valid',
            $this->status === 'applied' => 'Pending',
            $this->status === 'not_applied' => 'Not applied',
            default => 'NA',
        };
    }

    /** Site marked abandoned or relinquished in the Excel remarks. */
    public function isSiteClosed(): bool
    {
        return (bool) preg_match('/abandon|relinquish/i', (string) $this->remarks);
    }
}

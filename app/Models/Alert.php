<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    public const TYPE_UPCOMING = 'upcoming';
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_OVERDUE = 'overdue';

    protected $guarded = ['id'];

    protected $casts = [
        'triggered_on' => 'date',
        'read_at' => 'datetime',
        'emailed_at' => 'datetime',
    ];

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}

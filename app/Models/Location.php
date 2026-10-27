<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'target_depth_m' => 'float',
        'edpmb_date' => 'date',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function clearances(): HasMany
    {
        return $this->hasMany(Clearance::class);
    }
}

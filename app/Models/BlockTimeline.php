<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockTimeline extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'phase1_start_date' => 'date',
        'phase1_validity_contractual' => 'date',
        'phase1_validity_current' => 'date',
        'phase2_start_date' => 'date',
        'phase2_validity_contractual' => 'date',
        'phase2_validity_revised' => 'date',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}

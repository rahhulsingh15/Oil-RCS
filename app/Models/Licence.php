<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Licence extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'initial_grant_date' => 'date',
        'effective_date' => 'date',
        'validity_date' => 'date',
        'fee_rate' => 'decimal:2',
        'fee_amount' => 'decimal:2',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}

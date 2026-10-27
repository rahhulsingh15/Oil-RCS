<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'ld_total_musd' => 'float',
        'ld_total_cr_inr' => 'float',
        'paid_extension_usd' => 'float',
        'total_outgo_inr' => 'float',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}

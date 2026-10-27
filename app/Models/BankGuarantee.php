<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankGuarantee extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount_usd' => 'float',
        'amount_inr' => 'float',
        'validity_date' => 'date',
        'claim_date' => 'date',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}

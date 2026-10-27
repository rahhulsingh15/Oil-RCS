<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplianceItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'alert_days_before' => 'integer',
    ];

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }
}

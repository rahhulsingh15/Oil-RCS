<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McMeeting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'meeting_date' => 'date',
    ];

    /** Uploaded minutes of this meeting (newest first). */
    public function minutes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Document::class)->latest('id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}

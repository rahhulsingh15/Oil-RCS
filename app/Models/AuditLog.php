<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Log what changed on a model after save(): only the changed fields, old and new.
     * Pass the model's attributes from before the change.
     */
    public static function recordChanges(Model $model, array $before, string $description): void
    {
        $changes = collect($model->getChanges())->except(['updated_at', 'created_at', 'updated_by']);

        if ($model->wasRecentlyCreated) {
            static::record('created', $model, [], collect($model->getAttributes())->except(['id', 'updated_at', 'created_at', 'updated_by'])->filter(fn ($v) => $v !== null)->all(), $description);

            return;
        }
        if ($changes->isEmpty()) {
            return;
        }

        $format = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d.m.Y') : $v;
        static::record('updated', $model,
            $changes->keys()->mapWithKeys(fn ($k) => [$k => $format($before[$k] ?? null)])->all(),
            $changes->map($format)->all(),
            $description);
    }

    /** Write one audit entry. Picks up the logged-in user and request details when available. */
    public static function record(string $event, ?Model $subject = null, array $old = [], array $new = [], ?string $description = null): self
    {
        $request = app()->runningInConsole() ? null : request();

        return static::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}

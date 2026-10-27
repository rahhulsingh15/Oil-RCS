<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Notifications\DeadlineAlert;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

class Milestone extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_IN_PROGRESS];

    protected $guarded = ['id'];

    protected $casts = [
        'reference_date' => 'date',
        'due_date' => 'date',
        'manual_due_date' => 'boolean',
        'completed_at' => 'datetime',
        'alert_days_before' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updated(function (Milestone $m) {
            // New due date: old alerts no longer apply; the daily check notifies again for the new date
            if ($m->wasChanged('due_date')) {
                $m->forgetAlerts();

                return;
            }
            // Completed / Not Applicable: its notifications are done
            if ($m->wasChanged('status') && ! in_array($m->status, self::OPEN_STATUSES, true)) {
                DatabaseNotification::where('type', DeadlineAlert::class)
                    ->where('data->milestone_id', $m->id)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }
        });

        static::deleting(fn (Milestone $m) => $m->forgetAlerts());
    }

    /** Remove this milestone's alerts and everyone's notifications about it. */
    public function forgetAlerts(): void
    {
        Alert::where('milestone_id', $this->id)->delete();
        DatabaseNotification::where('type', DeadlineAlert::class)->where('data->milestone_id', $this->id)->delete();
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function complianceItem(): BelongsTo
    {
        return $this->belongsTo(ComplianceItem::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', '<', today());
    }

    /** Only milestones of blocks the given user is allowed to see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->visibleBlockIds();

        return $ids === null ? $query : $query->whereIn('block_id', $ids);
    }

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_NOT_APPLICABLE => 'Not Applicable',
    ];

    /** Data for the edit popup (data-milestone attribute). */
    public function editJson(): string
    {
        return json_encode([
            'url' => route('milestones.update', $this),
            'block' => $this->block->code,
            'item' => $this->shortLabel().($this->location_name ? ' · '.$this->location_name : '').' - '.$this->title,
            'due' => $this->due_date->format('Y-m-d'),
            'status' => $this->status,
            'remarks' => $this->remarks,
            'last' => $this->updated_by ? $this->updated_at?->format('d.m.Y H:i').' by '.($this->updatedBy?->name ?? 'a user') : null,
        ]);
    }

    /** Status as shown in the design: Overdue is labelled "At Risk". */
    public function statusLabel(): string
    {
        $status = $this->complianceStatus();

        return $status === 'Overdue' ? 'At Risk' : $status;
    }

    /** Short item name for tables, e.g. "BG Renewal", "CTO Validity". */
    public function shortLabel(): string
    {
        return match (strtok($this->source_key, ':')) {
            'pel' => 'PEL/PL Validity',
            'phase' => 'Phase Validity',
            'bg' => 'BG Renewal',
            'ec' => 'EC Validity',
            'cte' => 'CTE Validity',
            'cto' => 'CTO Validity',
            'mc' => 'MC Meeting',
            default => $this->category,
        };
    }

    /** Category names as shown in the portal. */
    public const CATEGORY_LABELS = [
        'Licence' => 'PEL/PL Validity',
        'Bank Guarantee' => 'Bank Guarantees',
        'Exploration Phase' => 'Exploration Phase',
        'Statutory Clearance' => 'Statutory Clearances',
        'MC Meeting' => 'MC Meetings',
    ];

    public function daysLeft(): int
    {
        return (int) today()->diffInDays($this->due_date, false);
    }

    /** Start date of the initial notification window. */
    public function alertDate(): Carbon
    {
        return $this->due_date->copy()->subDays($this->alert_days_before);
    }

    /** Half of the alert window (e.g. 15 for 30, 90 for 180). Second in-portal reminder triggers at or below this. */
    public function halfAlertDays(): int
    {
        return (int) floor($this->alert_days_before / 2);
    }

    /** Whether the milestone has entered its second-stage reminder window. */
    public function isHalfAlertWindow(): bool
    {
        return $this->daysLeft() <= $this->halfAlertDays() && $this->daysLeft() >= 0;
    }

    /** Compliant / Due Soon / Overdue / Completed / Not Applicable. Used by the dashboard. */
    public function complianceStatus(): string
    {
        return match (true) {
            $this->status === self::STATUS_COMPLETED => 'Completed',
            $this->status === self::STATUS_NOT_APPLICABLE => 'Not Applicable',
            $this->daysLeft() < 0 => 'Overdue',
            $this->daysLeft() <= $this->alert_days_before => 'Due Soon',
            default => 'Compliant',
        };
    }
}


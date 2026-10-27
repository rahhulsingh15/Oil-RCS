<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Milestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Milestone update from the edit popup (Compliance Monitor / Block Detail) */
class MilestoneController extends Controller
{
    public function update(Request $request, Milestone $milestone)
    {
        $user = $request->user();

        // Only milestones of the user's own blocks
        abort_unless($user->canAccessBlock($milestone->block_id), 403, 'This milestone belongs to a block you are not assigned to.');

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Milestone::STATUS_LABELS))],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
        ]);

        // Changing the due date needs its own permission
        $newDate = $request->filled('due_date') ? $request->date('due_date')->startOfDay() : null;
        $dateChanged = $newDate && ! $newDate->isSameDay($milestone->due_date);
        abort_if($dateChanged && ! $user->can('milestones.edit_dates'), 403, 'You are not allowed to change due dates.');

        $old = $this->auditValues($milestone);

        DB::transaction(function () use ($milestone, $data, $newDate, $dateChanged, $user, $old) {
            $milestone->status = $data['status'];
            $milestone->remarks = $data['remarks'] ?? null;
            $milestone->completed_at = $data['status'] === Milestone::STATUS_COMPLETED ? ($milestone->completed_at ?? now()) : null;
            if ($dateChanged) {
                $milestone->due_date = $newDate;
                $milestone->manual_due_date = true;
            }
            $milestone->updated_by = $user->id;
            $milestone->save();

            // Closed items stop alerting
            if (! in_array($milestone->status, Milestone::OPEN_STATUSES, true)) {
                Alert::where('milestone_id', $milestone->id)->whereNull('read_at')->update(['read_at' => now()]);
            }

            $new = $this->auditValues($milestone);
            $changedKeys = array_keys(array_filter($new, fn ($value, $key) => $old[$key] !== $value, ARRAY_FILTER_USE_BOTH));

            if ($changedKeys) {
                AuditLog::record('updated', $milestone,
                    array_intersect_key($old, array_flip($changedKeys)),
                    array_intersect_key($new, array_flip($changedKeys)),
                    "Milestone updated: {$milestone->block->code} · {$milestone->shortLabel()}");
            }
        });

        return back()->with('success', "{$milestone->block->code} · {$milestone->shortLabel()} updated.");
    }

    private function auditValues(Milestone $m): array
    {
        return [
            'status' => Milestone::STATUS_LABELS[$m->status] ?? $m->status,
            'due_date' => $m->due_date->format('d.m.Y'),
            'remarks' => $m->remarks,
        ];
    }
}

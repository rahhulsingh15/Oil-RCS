<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Milestone figures for one user, limited to the blocks they may see
 * (and optionally to a filtered list of block IDs).
 * Data volume is small (tens of milestones), so status is worked out in PHP.
 */
class ComplianceSummary
{
    /** @var array<string, Collection<int, Milestone>> */
    private array $cache = [];

    /** Open milestones (pending / in progress), ordered by due date. */
    public function openMilestones(User $user, ?array $blockIds = null): Collection
    {
        $key = $user->id.'|'.($blockIds === null ? '*' : implode(',', $blockIds));

        return $this->cache[$key] ??= Milestone::query()
            ->visibleTo($user)
            ->when($blockIds !== null, fn ($q) => $q->whereIn('block_id', $blockIds))
            ->open()
            ->with('block:id,code,basin')
            ->orderBy('due_date')
            ->get();
    }

    /** Overdue first, then due soon, each by date. */
    public function needsAttention(User $user, ?array $blockIds = null): Collection
    {
        return $this->openMilestones($user, $blockIds)
            ->filter(fn (Milestone $m) => in_array($m->complianceStatus(), ['Overdue', 'Due Soon'], true))
            ->sortBy(fn (Milestone $m) => [$m->complianceStatus() === 'Overdue' ? 0 : 1, $m->due_date->timestamp])
            ->values();
    }

    /**
     * Worst open status per block: Overdue > Due Soon > Compliant.
     *
     * @return array<int, string> block id => status
     */
    public function blockStatuses(User $user, ?array $blockIds = null): array
    {
        $rank = ['Compliant' => 0, 'Due Soon' => 1, 'Overdue' => 2];
        $result = [];

        foreach ($this->openMilestones($user, $blockIds) as $milestone) {
            $status = $milestone->complianceStatus();
            $current = $result[$milestone->block_id] ?? 'Compliant';
            $result[$milestone->block_id] = $rank[$status] > $rank[$current] ? $status : $current;
        }

        return $result;
    }
}

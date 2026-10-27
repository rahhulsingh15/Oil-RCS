<?php

namespace App\Services\Import;

use App\Models\Block;
use App\Models\ComplianceItem;
use App\Models\Milestone;
use Illuminate\Support\Carbon;

/**
 * Turns the imported block data into dated milestones, each linked to an RSC compliance rule
 * (which gives the "alert days before"). Only dates that exist in the Excel are used.
 *
 * Re-running keeps user changes (status, remarks). If the due date itself changed in the Excel
 * (for example a renewed BG), the milestone is reopened as pending.
 */
class MilestoneGenerator
{
    private const DEFAULT_ALERT_DAYS = 30;

    /** @var array<string, ComplianceItem> */
    private array $items = [];

    /** @var list<string> source keys written in this run */
    private array $produced = [];

    public function __construct(private ImportReport $report)
    {
    }

    public function generate(): void
    {
        $this->items = ComplianceItem::all()->keyBy('code')->all();

        $blocks = Block::with(['licence', 'timeline', 'bankGuarantee', 'mcMeetings', 'clearances'])->get();

        foreach ($blocks as $block) {
            if ($block->isRelinquished()) {
                continue;
            }

            $this->licence($block);
            $this->explorationPhase($block);
            $this->bankGuarantee($block);
            $this->clearances($block);
            $this->mcMeeting($block);
        }
    }

    /**
     * Rebuild all milestones of one block after its data was edited in the portal.
     * Milestones whose source no longer exists (e.g. a deleted clearance) are removed.
     */
    public function refreshBlock(Block $block): void
    {
        $this->items = ComplianceItem::all()->keyBy('code')->all();
        $this->produced = [];
        $block->load(['licence', 'timeline', 'bankGuarantee', 'mcMeetings', 'clearances']);

        if (! $block->isRelinquished()) {
            $this->licence($block);
            $this->explorationPhase($block);
            $this->bankGuarantee($block);
            $this->clearances($block);
            $this->mcMeeting($block);
        }

        // one by one so each milestone's notifications are removed too
        Milestone::where('block_id', $block->id)->whereNotIn('source_key', $this->produced ?: [''])->get()->each->delete();
    }

    /** Recalculate the "next MC meeting" milestone of one block (after a meeting is added in the portal). */
    public function refreshMcMeeting(Block $block): void
    {
        if ($block->isRelinquished()) {
            return;
        }

        $this->items = ComplianceItem::all()->keyBy('code')->all();
        $this->mcMeeting($block->load('mcMeetings'));
    }

    private function licence(Block $block): void
    {
        if ($date = $block->licence?->validity_date) {
            $this->upsert($block, "pel:{$block->code}", 'PL-01', 'Licence',
                'PEL/PL validity expiry: apply for extension', $date, $date);
        }
    }

    private function explorationPhase(Block $block): void
    {
        $timeline = $block->timeline;
        $date = $timeline?->phase2_validity_revised
            ?? $timeline?->phase2_validity_contractual
            ?? $timeline?->phase1_validity_current;

        if ($date) {
            $phase = $timeline->current_phase ?: 'Exploration phase';
            $this->upsert($block, "phase:{$block->code}", 'EP-02', 'Exploration Phase',
                "{$phase} validity expiry: extension or next-phase decision", $date, $date);
        }
    }

    /** BG must be renewed N days before expiry (Excel: "10 days prior to expiry"). */
    private function bankGuarantee(Block $block): void
    {
        $bg = $block->bankGuarantee;
        if (! $bg?->validity_date) {
            return;
        }

        $daysBefore = $bg->renewal_days_before_expiry ?? 0;
        $due = $bg->validity_date->copy()->subDays($daysBefore);
        $title = $daysBefore
            ? "Bank Guarantee renewal ({$daysBefore} days before expiry on {$bg->validity_date->format('d.m.Y')})"
            : 'Bank Guarantee renewal before expiry';

        $this->upsert($block, "bg:{$block->code}", 'CA-01', 'Bank Guarantee', $title, $bg->validity_date, $due);
    }

    /** EC is project-wide (same dates on every row), so one EC milestone per block. CTE/CTO per location. */
    private function clearances(Block $block): void
    {
        $ecDone = [];

        foreach ($block->clearances as $clearance) {
            if (! $clearance->valid_until || $clearance->isSiteClosed()) {
                continue;
            }

            if ($clearance->type === 'EC') {
                $key = $clearance->valid_until->toDateString();
                if (isset($ecDone[$key])) {
                    continue;
                }
                $ecDone[$key] = true;

                $this->upsert($block, "ec:{$block->code}:{$key}", 'ENV-01', 'Statutory Clearance',
                    'Environmental Clearance (EC) validity expiry', $clearance->valid_until, $clearance->valid_until);

                continue;
            }

            $name = $clearance->type === 'CTE' ? 'Consent to Establish (CTE)' : 'Consent to Operate (CTO)';

            $this->upsert($block, strtolower($clearance->type).":{$block->code}:{$clearance->location_name}", 'ENV-01',
                'Statutory Clearance', "{$name} renewal: {$clearance->location_name}",
                $clearance->valid_until, $clearance->valid_until, $clearance->location_name);
        }
    }

    /** MC meetings are half-yearly (MC-01), so the next one is due 6 months after the last. */
    private function mcMeeting(Block $block): void
    {
        $last = $block->mcMeetings->whereNotNull('meeting_date')->sortBy('meeting_date')->last();
        if (! $last) {
            return;
        }

        $due = $last->meeting_date->copy()->addMonthsNoOverflow(6);
        $this->upsert($block, "mc:{$block->code}", 'MC-01', 'MC Meeting',
            'Next Management Committee meeting (half-yearly)', $last->meeting_date, $due);
    }

    private function upsert(Block $block, string $key, string $itemCode, string $category, string $title,
        ?Carbon $reference, Carbon $due, ?string $location = null): void
    {
        $this->produced[] = $key;
        $item = $this->items[$itemCode] ?? null;
        if (! $item) {
            $this->report->warn("Compliance rule {$itemCode} not found in the register; milestone \"{$key}\" uses the default alert days.");
        }

        $values = [
            'block_id' => $block->id,
            'compliance_item_id' => $item?->id,
            'category' => $category,
            'title' => $title,
            'location_name' => $location,
            'reference_date' => $reference,
            'due_date' => $due,
            'alert_days_before' => $item?->alert_days_before ?? self::DEFAULT_ALERT_DAYS,
        ];

        $milestone = Milestone::firstWhere('source_key', $key);

        if (! $milestone) {
            Milestone::create($values + ['source_key' => $key, 'status' => Milestone::STATUS_PENDING]);
            $this->report->count('milestones');

            return;
        }

        if ($milestone->manual_due_date) {
            // A user set this due date in the portal: keep their date and status
            unset($values['due_date']);
        } elseif (! $milestone->due_date->isSameDay($due)) {
            $values += ['status' => Milestone::STATUS_PENDING, 'completed_at' => null];
        }

        $milestone->update($values);
        $this->report->count('milestones');
    }
}

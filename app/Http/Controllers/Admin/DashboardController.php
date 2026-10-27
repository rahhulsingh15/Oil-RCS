<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Clearance;
use App\Models\Location;
use App\Models\Milestone;
use App\Services\ComplianceSummary;
use Illuminate\Http\Request;

/** Screen 1: Executive Dashboard */
class DashboardController extends Controller
{
    public function __invoke(Request $request, ComplianceSummary $summary)
    {
        $user = $request->user();
        $today = today();

        $blocks = $this->filteredBlocks($request)
            ->with(['timeline', 'bankGuarantee', 'licence', 'workProgramme'])
            ->orderBy('code')
            ->get();
        $ids = $blocks->pluck('id')->all();
        $active = $blocks->reject->isRelinquished();

        // Days left until each BG / PEL expiry (active blocks, not yet expired)
        $daysUntil = fn ($dates) => $dates->filter()
            ->map(fn ($date) => (int) $today->diffInDays($date, false))
            ->filter(fn ($d) => $d >= 0);
        $bgDays = $daysUntil($active->map(fn (Block $b) => $b->bankGuarantee?->validity_date));
        $pelDays = $daysUntil($active->map(fn (Block $b) => $b->licence?->validity_date));

        $pendingClearances = Clearance::visibleTo($user)
            ->whereIn('block_id', $ids)
            ->whereIn('status', ['applied', 'not_applied'])
            ->get()
            ->reject->isSiteClosed()
            ->countBy('type');

        // CWP progress per part (2D / 3D / Wells): average over active blocks that committed to that part
        $cwpParts = [];
        foreach (array_keys(\App\Models\WorkProgramme::PARTS) as $part) {
            $values = $active->map(fn (Block $b) => $b->workProgramme?->partPct($part))->filter(fn ($p) => $p !== null);
            $cwpParts[$part] = $values->isNotEmpty() ? round($values->avg()) : null;
        }

        // Donut: each active block counted once, by its worst open item
        $blockStatus = $summary->blockStatuses($user, $ids);
        $donut = ['Compliant' => 0, 'Due Soon' => 0, 'At Risk' => 0];
        foreach ($active as $block) {
            $status = $blockStatus[$block->id] ?? 'Compliant';
            $donut[$status === 'Overdue' ? 'At Risk' : $status]++;
        }

        // Main chart: active blocks Onland vs Offshore (Excel "Category").
        // Client (26 Oct): "Combination (Onland + Offshore)" counts as Onland; relinquished blocks are left out.
        $blockTypes = ['Onland' => [], 'Offshore' => []];
        foreach ($active as $block) {
            $type = str_contains(strtolower((string) $block->category), 'onland') ? 'Onland' : 'Offshore';
            $blockTypes[$type][] = $block->code;
        }

        // Wells per block from the Location inventory: drilled / under drilling / planned
        $wells = Location::whereIn('block_id', $ids)->with('block:id,code')->get()
            ->groupBy(fn (Location $l) => $l->block->code)
            ->sortKeys()
            ->map(fn ($locations) => [
                'drilled' => $locations->where('drilling_status', 'Drilled')->count(),
                'drilling' => $locations->where('drilling_status', 'Under drilling')->count(),
                'planned' => $locations->where('drilling_status', 'Not drilled')->count(),
            ]);

        // Blocks per OALP round, every round from I up to the latest (zeros included)
        $roman = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6, 'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10, 'XI' => 11, 'XII' => 12];
        $roundCounts = $blocks->countBy(fn (Block $b) => $roman[strtoupper(trim((string) $b->bid_round))] ?? 0);
        $lastRound = max(9, $roundCounts->keys()->max() ?? 0);
        $rounds = [];
        foreach (array_slice($roman, 0, $lastRound, true) as $label => $number) {
            $rounds["OALP-{$label}"] = $roundCounts[$number] ?? 0;
        }

        // Overdue first, then anything due in the next 90 days
        $deadlines = $summary->openMilestones($user, $ids)
            ->filter(fn (Milestone $m) => $m->daysLeft() <= 90)
            ->sortBy(fn (Milestone $m) => $m->due_date->timestamp)
            ->take(6);

        // Latest milestone changes made in the portal (from the audit log), for the filtered blocks
        $latestUpdates = AuditLog::query()
            ->with(['user:id,name', 'auditable.block:id,code'])
            ->where('auditable_type', (new Milestone)->getMorphClass())
            ->whereIn('auditable_id', Milestone::whereIn('block_id', $ids)->select('id'))
            ->latest('id')
            ->take(5)
            ->get();

        return view('admin.dashboard', [
            'latestUpdates' => $latestUpdates,
            // In-portal notifications of this user (no email)
            'notificationsUnread' => $user->unreadNotifications()->where('type', \App\Notifications\DeadlineAlert::class)->count(),
            'notificationsLatest' => $user->unreadNotifications()->where('type', \App\Notifications\DeadlineAlert::class)->latest()->take(4)->get(),
            'blocks' => $blocks,
            'activeCount' => $active->count(),
            'bg90' => $bgDays->filter(fn ($d) => $d <= 90)->count(),
            'bg30' => $bgDays->filter(fn ($d) => $d <= 30)->count(),
            'pel365' => $pelDays->filter(fn ($d) => $d <= 365)->count(),
            'pel180' => $pelDays->filter(fn ($d) => $d <= 180)->count(),
            'pendingClearances' => $pendingClearances,
            'cwpParts' => $cwpParts,
            'donut' => $donut,
            'blockTypes' => $blockTypes,
            'wells' => $wells,
            'rounds' => $rounds,
            'deadlines' => $deadlines,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Milestone;
use App\Services\ComplianceSummary;
use Illuminate\Http\Request;

/** Screen 6: Reports, Alerts & Downloads */
class ReportController extends Controller
{
    public const WINDOWS = [30, 90, 180, 365];

    public function index(Request $request, ComplianceSummary $summary)
    {
        $window = in_array($request->integer('days'), self::WINDOWS, true) ? $request->integer('days') : 90;
        $open = $summary->openMilestones($request->user());

        $filtered = $open->when($request->filled('category'), fn ($c) => $c->where('category', $request->input('category')));

        return view('admin.reports', [
            // Everything overdue plus what falls due inside the window
            'actions' => $filtered->filter(fn (Milestone $m) => $m->daysLeft() <= $window)->values(),
            'window' => $window,
            'categories' => $open->pluck('category')->unique()->sort()->values(),
            'byCategory' => $filtered->groupBy('category')->map(fn ($group) => [
                'overdue' => $group->filter(fn (Milestone $m) => $m->daysLeft() < 0)->count(),
                'dueSoon' => $group->filter(fn (Milestone $m) => $m->complianceStatus() === 'Due Soon')->count(),
                'total' => $group->count(),
            ])->sortKeys(),
        ]);
    }

    /** CSV download of the obligations the user can see (Compliance Monitor filters apply). */
    public function export(Request $request, ComplianceController $compliance)
    {
        $rows = $compliance->milestones($request);

        AuditLog::record('exported', null, [], ['rows' => $rows->count(), 'filters' => $request->query()], 'Compliance CSV downloaded');

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 correctly
            fputcsv($out, ['Block', 'Basin', 'Compliance Area', 'Item', 'Details', 'Location', 'Reference Date', 'Due Date', 'Days Left', 'Alert Days', 'Status', 'Remarks']);

            foreach ($rows as $m) {
                fputcsv($out, [
                    $m->block->code, $m->block->basin, $m->category, $m->shortLabel(), $m->title, $m->location_name,
                    $m->reference_date?->format('d.m.Y'), $m->due_date->format('d.m.Y'), $m->daysLeft(),
                    $m->alert_days_before, $m->complianceStatus(), $m->remarks,
                ]);
            }
            fclose($out);
        }, 'OIL_RSC_Compliance_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

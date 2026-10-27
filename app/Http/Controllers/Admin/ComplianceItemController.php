<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ComplianceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplianceItemController extends Controller
{
    public function index(Request $request)
    {
        $items = ComplianceItem::query()
            ->withCount('milestones')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->input('q').'%';
                $query->where(fn ($q) => $q->where('code', 'like', $term)
                    ->orWhere('obligation', 'like', $term)
                    ->orWhere('head', 'like', $term));
            })
            ->orderBy('id')
            ->get();

        return view('admin.compliance-register', [
            'items' => $items->groupBy('head'),
            'missingOwner' => ComplianceItem::whereNull('nodal_officer_email')->count(),
        ]);
    }

    public function edit(ComplianceItem $complianceItem)
    {
        return view('admin.compliance-register-edit', ['item' => $complianceItem]);
    }

    public function update(Request $request, ComplianceItem $complianceItem)
    {
        $data = $request->validate([
            'responsible_department' => ['nullable', 'string', 'max:100'],
            'nodal_officer' => ['nullable', 'string', 'max:100'],
            'nodal_officer_email' => ['nullable', 'email', 'max:255'],
            'approving_authority' => ['nullable', 'string', 'max:100'],
            'alert_days_before' => ['nullable', 'integer', 'min:0', 'max:730'],
            'supporting_documents' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($complianceItem, $data) {
            $old = $complianceItem->only(array_keys($data));
            $complianceItem->update($data);
            $changed = $complianceItem->getChanges();
            unset($changed['updated_at']);

            // Milestones copy the alert window, keep them in step with the rule
            if (array_key_exists('alert_days_before', $changed) && $complianceItem->alert_days_before !== null) {
                $complianceItem->milestones()->update(['alert_days_before' => $complianceItem->alert_days_before]);
            }

            if ($changed) {
                AuditLog::record('updated', $complianceItem, array_intersect_key($old, $changed), $changed,
                    "Compliance rule {$complianceItem->code} updated");
            }
        });

        return redirect()->route('admin.compliance-items.index')->with('success', "Compliance rule {$complianceItem->code} updated.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** Screen 3: Compliance Monitor */
class ComplianceController extends Controller
{
    /** ?status= values => compliance statuses they include */
    public const STATUS_FILTERS = [
        'attention' => ['Overdue', 'Due Soon'],
        'Overdue' => ['Overdue'],
        'Due Soon' => ['Due Soon'],
        'Compliant' => ['Compliant'],
        'Completed' => ['Completed'],
    ];

    public function index(Request $request)
    {
        $all = $this->milestones($request);

        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $milestones = new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(), $all->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Pills: categories present in the user's blocks
        $categories = Milestone::visibleTo($request->user())->distinct()->orderBy('category')->pluck('category');

        return view('admin.compliance', [
            'milestones' => $milestones,
            'categories' => $categories,
        ]);
    }

    /** Milestones of the filtered blocks with category / status / search applied. Also used for the CSV. */
    public function milestones(Request $request): Collection
    {
        $statuses = self::STATUS_FILTERS[$request->input('status')] ?? null;

        return Milestone::query()
            ->visibleTo($request->user())
            ->whereIn('block_id', $this->filteredBlockIds($request))
            ->with(['block:id,code,basin', 'complianceItem:id,code', 'updatedBy:id,name'])
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->input('q').'%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)
                    ->orWhere('location_name', 'like', $term)
                    ->orWhereHas('block', fn ($b) => $b->where('code', 'like', $term)));
            })
            ->orderBy('due_date')
            ->get()
            ->when($statuses, fn ($c) => $c->filter(fn (Milestone $m) => in_array($m->complianceStatus(), $statuses, true)))
            ->values();
    }
}

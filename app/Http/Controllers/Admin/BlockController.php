<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Location;
use App\Services\ComplianceSummary;
use Illuminate\Http\Request;

/** Screen 2 (Block Overview), Screen 4 (Block Detail), plus CWP, Investment and Location Inventory lists */
class BlockController extends Controller
{
    public function index(Request $request, ComplianceSummary $summary)
    {
        $blocks = $this->filteredBlocks($request)
            ->with('timeline')
            ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', '%'.$request->input('q').'%'))
            ->orderBy('code')
            ->get();

        return view('admin.blocks', [
            'blocks' => $blocks,
            'statuses' => $summary->blockStatuses($request->user(), $blocks->pluck('id')->all()),
            'basins' => $blocks->pluck('basin')->unique()->values(),
        ]);
    }

    public function show(Request $request, Block $block, ComplianceSummary $summary)
    {
        $block->load([
            'licence', 'timeline', 'workProgramme', 'bankGuarantee', 'investment',
            'mcMeetings.minutes', 'locations', 'clearances', 'documents.uploader:id,name', 'users:id,name,email,phone,is_active,is_nodal_officer',
            'milestones' => fn ($q) => $q->with('updatedBy:id,name')->orderBy('due_date'),
        ]);

        $block->milestones->each->setRelation('block', $block);

        return view('admin.block-detail', [
            'block' => $block,
            'allBlocks' => Block::visibleTo($request->user())->orderBy('code')->get(['id', 'code']),
        ]);
    }

    public function cwp(Request $request)
    {
        $blocks = $this->filteredBlocks($request)
            ->with(['workProgramme', 'timeline'])
            ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', '%'.$request->input('q').'%'))
            ->orderBy('code')
            ->get();

        return view('admin.cwp', ['blocks' => $blocks]);
    }

    public function investment(Request $request)
    {
        $blocks = $this->filteredBlocks($request)
            ->with(['investment', 'bankGuarantee', 'timeline'])
            ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', '%'.$request->input('q').'%'))
            ->orderBy('code')
            ->get();

        return view('admin.investment', ['blocks' => $blocks]);
    }

    public function locations(Request $request)
    {
        $locations = Location::query()
            ->with('block:id,code')
            ->whereIn('block_id', $this->filteredBlockIds($request))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
            ->when($request->filled('drilling'), fn ($q) => $q->where('drilling_status', $request->input('drilling')))
            ->orderBy('block_id')->orderBy('name')
            ->get();

        return view('admin.locations', ['locations' => $locations]);
    }
}

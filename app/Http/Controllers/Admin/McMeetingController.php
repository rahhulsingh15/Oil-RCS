<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Document;
use App\Models\McMeeting;
use App\Services\Import\ImportReport;
use App\Services\Import\MilestoneGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** MC Meetings: list per block, add a meeting, upload minutes */
class McMeetingController extends Controller
{
    public function index(Request $request)
    {
        $blocks = $this->filteredBlocks($request)
            ->with(['mcMeetings.minutes.uploader:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->where('code', 'like', '%'.$request->input('q').'%'))
            ->orderBy('code')
            ->get();

        return view('admin.mc-meetings', [
            'blocks' => $blocks,
            'uploadBlocks' => Block::visibleTo($request->user())->active()->orderBy('code')->get(['id', 'code']),
        ]);
    }

    /** Add a new MC meeting (optionally with its minutes). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'meeting_date' => ['required', 'date', 'before_or_equal:today'],
            'file' => Document::fileRules(false),
        ]);

        $block = Block::findOrFail($data['block_id']);
        abort_unless($request->user()->canAccessBlock($block), 403, 'You can only add meetings for your assigned blocks.');

        $meeting = DB::transaction(function () use ($block, $data, $request) {
            $meeting = McMeeting::create([
                'block_id' => $block->id,
                'meeting_no' => (int) $block->mcMeetings()->max('meeting_no') + 1,
                'meeting_date' => $data['meeting_date'],
            ]);

            AuditLog::record('created', $meeting, [], [
                'block' => $block->code, 'meeting' => '#'.$meeting->meeting_no, 'date' => $meeting->meeting_date->format('d.m.Y'),
            ], "MC meeting #{$meeting->meeting_no} added for {$block->code}");

            if ($request->hasFile('file')) {
                $this->saveMinutes($request, $meeting);
            }

            // Next MC meeting is due 6 months after this one
            (new MilestoneGenerator(new ImportReport))->refreshMcMeeting($block);

            return $meeting;
        });

        return back()->with('success', "MC meeting #{$meeting->meeting_no} added for {$block->code}.");
    }

    /** Upload minutes for an existing meeting. */
    public function uploadMinutes(Request $request, McMeeting $mcMeeting)
    {
        abort_unless($request->user()->canAccessBlock($mcMeeting->block_id), 403);
        $request->validate(['file' => Document::fileRules()]);

        $this->saveMinutes($request, $mcMeeting);

        return back()->with('success', "Minutes uploaded for MC meeting #{$mcMeeting->meeting_no} ({$mcMeeting->block->code}).");
    }

    private function saveMinutes(Request $request, McMeeting $meeting): void
    {
        Document::storeUpload($request->file('file'), $meeting->block, [
            'mc_meeting_id' => $meeting->id,
            'type' => 'MC Minutes',
            'title' => "Minutes of MC meeting #{$meeting->meeting_no}",
            'document_date' => $meeting->meeting_date,
        ], $request->user());
    }
}

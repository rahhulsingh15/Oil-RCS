<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Clearance;
use App\Models\Location;
use App\Services\Import\ImportReport;
use App\Services\Import\MilestoneGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Screen 5: Statutory Clearances */
class ClearanceController extends Controller
{
    public function index(Request $request)
    {
        $clearances = Clearance::query()
            ->with('block:id,code')
            ->whereIn('block_id', $this->filteredBlockIds($request))
            ->when($request->filled('q'), fn ($q) => $q->where('location_name', 'like', '%'.$request->input('q').'%'))
            ->get();

        // One row per block + location with EC / CTE / CTO side by side
        $rows = $clearances
            ->groupBy(fn (Clearance $c) => $c->block->code.'|'.$c->location_name)
            ->sortKeys()
            ->map(fn ($group) => (object) [
                'block' => $group->first()->block,
                'location' => $group->first()->location_name,
                'remarks' => $group->first()->remarks,
                'types' => $group->keyBy('type'),
            ])
            ->when($request->filled('status'), fn ($rows) => $rows->filter(
                fn ($row) => $row->types->contains(fn (Clearance $c) => $c->displayStatus() === $request->input('status'))
            ))
            ->values();

        $total = $rows->count();
        $with = fn (string $type) => $rows->filter(fn ($row) => $row->types->get($type)?->status === 'received')->count();
        $pending = $rows->filter(fn ($row) => $row->types->contains(fn (Clearance $c) => $c->status !== 'received'))->count();

        return view('admin.clearances', [
            'rows' => $rows,
            'total' => $total,
            'withEc' => $with('EC'),
            'withCte' => $with('CTE'),
            'withCto' => $with('CTO'),
            'pending' => $pending,
            'pct' => fn (int $n) => $total ? round($n / $total * 100) : 0,
        ]);
    }

    /** Status options in the form */
    public const STATUSES = [
        'received' => 'Received',
        'applied' => 'Applied / Pending',
        'not_applied' => 'Not applied',
        'not_applicable' => 'Not applicable (NA)',
    ];

    public function create(Request $request)
    {
        return view('admin.clearance-form', [
            'blocks' => Block::visibleTo($request->user())->orderBy('code')->get(['id', 'code']),
            'blockId' => $request->integer('block') ?: null,
            'location' => null,
            'types' => collect(),
            'remarks' => null,
            'project' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $block = $this->accessibleBlock($request, $data['block_id']);

        if (Clearance::where('block_id', $block->id)->where('location_name', $data['location_name'])->exists()) {
            throw ValidationException::withMessages(['location_name' => "Clearances for {$data['location_name']} already exist in {$block->code}. Edit that row instead."]);
        }

        $this->saveLocation($request, $block, $data, null);

        return redirect()->route('clearances.index')->with('success', "Clearances added for {$data['location_name']} ({$block->code}).");
    }

    public function edit(Request $request)
    {
        $block = $this->accessibleBlock($request, $request->integer('block'));
        $types = Clearance::where('block_id', $block->id)->where('location_name', $request->input('location'))->get()->keyBy('type');
        abort_if($types->isEmpty(), 404);

        return view('admin.clearance-form', [
            'blocks' => Block::visibleTo($request->user())->orderBy('code')->get(['id', 'code']),
            'blockId' => $block->id,
            'location' => $request->input('location'),
            'types' => $types,
            'remarks' => $types->first()->remarks,
            'project' => $types->first()->project,
        ]);
    }

    public function update(Request $request)
    {
        $data = $this->validated($request);
        $block = $this->accessibleBlock($request, $request->integer('original_block'));
        $original = (string) $request->input('original_location');
        abort_if($block->id !== (int) $data['block_id'], 422, 'The block of a clearance cannot be changed. Delete it and add it again under the other block.');

        $this->saveLocation($request, $block, $data, $original);

        return redirect()->route('clearances.index')->with('success', "Clearances updated for {$data['location_name']} ({$block->code}).");
    }

    public function destroy(Request $request)
    {
        $block = $this->accessibleBlock($request, $request->integer('block'));
        $location = (string) $request->input('location');
        $rows = Clearance::where('block_id', $block->id)->where('location_name', $location)->get();

        AuditLog::record('deleted', $rows->first(), ['block' => $block->code, 'location' => $location,
            'clearances' => $rows->map(fn ($c) => "{$c->type}: {$c->displayStatus()}")->all()], [],
            "Clearances deleted: {$location} ({$block->code})");
        Clearance::whereIn('id', $rows->pluck('id'))->delete();

        (new MilestoneGenerator(new ImportReport))->refreshBlock($block);

        return redirect()->route('clearances.index')->with('success', "Clearances deleted for {$location} ({$block->code}).");
    }

    /** Create or update the EC / CTE / CTO rows of one location. */
    private function saveLocation(Request $request, Block $block, array $data, ?string $originalLocation): void
    {
        DB::transaction(function () use ($request, $block, $data, $originalLocation) {
            $location = Location::where('block_id', $block->id)->where('name', $data['location_name'])->first();

            foreach (Clearance::TYPES as $type) {
                $t = strtolower($type);
                $clearance = Clearance::firstOrNew([
                    'block_id' => $block->id,
                    'location_name' => $originalLocation ?? $data['location_name'],
                    'type' => $type,
                ]);
                $before = $clearance->getAttributes();

                $clearance->fill([
                    'location_name' => $data['location_name'],
                    'location_id' => $location?->id,
                    'project' => $data['project'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                    'status' => $data["{$t}_status"],
                    'applied_on' => $data["{$t}_applied_on"] ?? null,
                    'received_on' => $data["{$t}_received_on"] ?? null,
                    'valid_until' => $data["{$t}_valid_until"] ?? null,
                    'note' => $data["{$t}_note"] ?? null,
                ]);

                if ($clearance->isDirty() || ! $clearance->exists) {
                    $clearance->updated_by = $request->user()->id;
                    $clearance->save();
                    AuditLog::recordChanges($clearance, $before, "{$type} clearance saved: {$data['location_name']} ({$block->code})");
                }
            }

            // Validity dates feed the renewal deadlines
            (new MilestoneGenerator(new ImportReport))->refreshBlock($block);
        });
    }

    private function validated(Request $request): array
    {
        $rules = [
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'location_name' => ['required', 'string', 'max:100'],
            'project' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
        foreach (Clearance::TYPES as $type) {
            $t = strtolower($type);
            $rules["{$t}_status"] = ['required', Rule::in(array_keys(self::STATUSES))];
            $rules["{$t}_applied_on"] = ['nullable', 'date'];
            $rules["{$t}_received_on"] = ['nullable', 'date'];
            $rules["{$t}_valid_until"] = ['nullable', 'date'];
            $rules["{$t}_note"] = ['nullable', 'string', 'max:255'];
        }

        return $request->validate($rules);
    }

    private function accessibleBlock(Request $request, ?int $id): Block
    {
        $block = Block::findOrFail($id);
        abort_unless($request->user()->canAccessBlock($block), 403, 'You can only manage clearances of your assigned blocks.');

        return $block;
    }
}

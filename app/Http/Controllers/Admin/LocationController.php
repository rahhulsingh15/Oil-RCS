<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Add / edit / delete well locations (Location Inventory) */
class LocationController extends Controller
{
    public const DRILLING_STATUSES = ['Not drilled', 'Under drilling', 'Drilled'];

    public function create(Request $request)
    {
        return view('admin.location-form', [
            'location' => new Location(['block_id' => $request->integer('block') ?: null, 'drilling_status' => 'Not drilled']),
            'blocks' => Block::visibleTo($request->user())->orderBy('code')->get(['id', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $block = $this->accessibleBlock($request, $data['block_id']);

        $location = new Location($data + ['updated_by' => $request->user()->id]);
        $location->save();
        AuditLog::recordChanges($location, [], "Location added: {$location->name} ({$block->code})");

        return redirect()->route('locations.index')->with('success', "Location {$location->name} added to {$block->code}.");
    }

    public function edit(Request $request, Location $location)
    {
        $this->accessibleBlock($request, $location->block_id);

        return view('admin.location-form', [
            'location' => $location,
            'blocks' => Block::visibleTo($request->user())->orderBy('code')->get(['id', 'code']),
        ]);
    }

    public function update(Request $request, Location $location)
    {
        $this->accessibleBlock($request, $location->block_id);
        $data = $this->validated($request, $location);
        $block = $this->accessibleBlock($request, $data['block_id']);

        $before = $location->getAttributes();
        $location->fill($data);
        if ($location->isDirty()) {
            $location->updated_by = $request->user()->id;
            $location->save();
            AuditLog::recordChanges($location, $before, "Location updated: {$location->name} ({$block->code})");
        }

        return redirect()->route('locations.index')->with('success', "Location {$location->name} updated.");
    }

    public function destroy(Request $request, Location $location)
    {
        $block = $this->accessibleBlock($request, $location->block_id);

        AuditLog::record('deleted', $location, ['block' => $block->code, 'name' => $location->name, 'drilling_status' => $location->drilling_status], [],
            "Location deleted: {$location->name} ({$block->code})");
        $location->delete();

        return redirect()->route('locations.index')->with('success', "Location {$location->name} deleted.");
    }

    private function validated(Request $request, ?Location $location = null): array
    {
        $data = $request->validate([
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'name' => ['required', 'string', 'max:50', Rule::unique('locations', 'name')
                ->where('block_id', $request->integer('block_id'))->ignore($location?->id)],
            'target_depth_m' => ['nullable', 'numeric', 'min:0'],
            'targets' => ['nullable', 'string', 'max:1000'],
            'edpmb_no' => ['nullable', 'string', 'max:20'],
            'edpmb_date' => ['nullable', 'date'],
            'drilling_status' => ['required', Rule::in(self::DRILLING_STATUSES)],
            'actual_drilled_depth' => ['nullable', 'string', 'max:50'],
            'key_findings' => ['nullable', 'string', 'max:1000'],
            'studies_carried_out' => ['nullable', 'string', 'max:1000'],
            'expected_drilling_start' => ['nullable', 'string', 'max:50'],
            'planned_completion' => ['nullable', 'string', 'max:50'],
            'current_status' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['name'] = strtoupper(trim($data['name']));
        $data['edpmb_date_text'] = null; // a real date replaces the old Excel text

        return $data;
    }

    private function accessibleBlock(Request $request, ?int $id): Block
    {
        $block = Block::findOrFail($id);
        abort_unless($request->user()->canAccessBlock($block), 403, 'You can only manage locations of your assigned blocks.');

        return $block;
    }
}

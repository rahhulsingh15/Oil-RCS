<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Block;
use Illuminate\Http\Request;

/** Edit the Committed Work Programme of one block, incl. the well under drilling. */
class CwpController extends Controller
{
    /** CWP fields, same list as the CWP section of the block form. */
    private function fields(): array
    {
        return BlockFormController::sections()['Committed Work Programme (CWP)'][1];
    }

    public function edit(Request $request, Block $block)
    {
        abort_unless($request->user()->canAccessBlock($block), 403);

        return view('admin.cwp-form', [
            'block' => $block->load(['workProgramme', 'locations']),
            'fields' => $this->fields(),
        ]);
    }

    public function update(Request $request, Block $block)
    {
        abort_unless($request->user()->canAccessBlock($block), 403);

        $rules = [];
        foreach ($this->fields() as $name => [$label, $type]) {
            $rules[$name] = match ($type) {
                'int' => ['nullable', 'integer', 'min:0'],
                'number' => ['nullable', 'numeric', 'min:0'],
                default => ['nullable', 'string', 'max:100'],
            };
        }
        $data = $request->validate($rules);
        // (Completion % is calculated by the WorkProgramme model on save, never typed.)

        // No well under drilling: clear its details
        if (empty($data['wells_under_drilling'])) {
            $data['location_under_drilling'] = $data['current_drilled_depth_m'] = $data['target_depth_m'] = null;
        }

        $cwp = $block->workProgramme()->firstOrNew();
        $before = $cwp->getAttributes();
        $cwp->fill($data);

        if ($cwp->isDirty() || ! $cwp->exists) {
            $cwp->updated_by = $request->user()->id;
            $cwp->save();
            AuditLog::recordChanges($cwp, $before, "Work programme updated: {$block->code}");
        }

        return redirect()->route('cwp.index')->with('success', "Work programme of {$block->code} updated.");
    }
}

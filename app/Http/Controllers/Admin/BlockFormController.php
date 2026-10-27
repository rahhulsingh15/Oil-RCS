<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Basin;
use App\Models\Block;
use App\Services\Import\ImportReport;
use App\Services\Import\MilestoneGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Add / edit / delete a block with its licence, timeline, CWP and BG (one form). */
class BlockFormController extends Controller
{
    /**
     * Form sections: relation on Block (null = the block itself) => fields.
     * Field = [label, type, options]. Types: text, textarea, date, number, int, select.
     */
    public static function sections(): array
    {
        $phases = ['IEP', 'SEP', 'Phase-I', 'Phase-II', 'EP', 'Development', 'Relinquished'];

        return [
            'Block Details' => [null, [
                'code' => ['Block Code *', 'text'],
                'regime' => ['Regime', 'text'],
                'bid_round' => ['OALP Round (I, II, III ... IX)', 'text'],
                'category' => ['Category', 'select', ['Onland', 'Combination (Onland + Offshore)', 'Shallow Water', 'Deep Water', 'Ultra Deep Water']],
                'basin' => ['Basin *', 'select', Basin::orderBy('name')->pluck('name')->all()],
                'basin_category' => ['Basin Category', 'text'],
                'state' => ['State', 'text'],
                'districts' => ['District(s)', 'text'],
                'area_original' => ['Area Original (Sq Km)', 'number'],
                'area_revised' => ['Area Revised (Sq Km)', 'number'],
                'area_revision_date' => ['Area Revision Date', 'date'],
                'rsc_signing_date' => ['RSC Signing Date', 'date'],
                'status' => ['Status *', 'select', ['Active', 'Relinquished']],
                'current_stage' => ['Current Stage', 'select', ['Exploration', 'Development', 'Production', 'Relinquished']],
                'relinquishment_date' => ['Relinquishment Date', 'date'],
            ]],
            'PEL / PL Licence' => ['licence', [
                'granting_authority' => ['Granting Authority', 'text'],
                'initial_grant_date' => ['Initial Grant Date', 'date'],
                'effective_date' => ['Effective Date', 'date'],
                'pel_to_pl_converted' => ['PEL to PL Converted', 'select', ['Yes', 'No', 'NA']],
                'validity_date' => ['Current PEL / PL Validity', 'date'],
                'current_year' => ['Current Year of PEL / PL', 'text'],
                'fee_rate' => ['Last Year Fee Rate', 'number'],
                'fee_amount' => ['Last Year Fee Amount (INR)', 'number'],
            ]],
            'Contract Timeline' => ['timeline', [
                'contractual_structure' => ['Contractual Structure', 'text'],
                'current_phase' => ['Current Phase', 'select', $phases],
                'phase1_duration' => ['Phase-I Duration', 'text'],
                'phase1_extension_allowed' => ['Phase-I Permissible Extension', 'text'],
                'phase1_start_date' => ['Phase-I Start Date', 'date'],
                'phase1_validity_contractual' => ['Phase-I Validity (Contractual)', 'date'],
                'phase1_validity_current' => ['Phase-I Validity (Current)', 'date'],
                'policy_2025_availed' => ['Policy dated 11.11.25 Availed', 'text'],
                'phase1_extensions_granted' => ['Phase-I Extensions Granted', 'textarea'],
                'phase2_duration' => ['Phase-II Duration', 'text'],
                'phase2_extension_allowed' => ['Phase-II Permissible Extension', 'text'],
                'phase2_start_date' => ['Phase-II Start Date', 'date'],
                'phase2_validity_contractual' => ['Phase-II Validity (Contractual)', 'date'],
                'phase2_validity_revised' => ['Phase-II Validity (Revised)', 'date'],
            ]],
            'Committed Work Programme (CWP)' => ['workProgramme', [
                'committed_2d_lkm' => ['Committed 2D (LKM)', 'number'],
                'committed_3d_sqkm' => ['Committed 3D (Sq Km)', 'number'],
                'committed_wells' => ['Committed Wells', 'int'],
                'achieved_2d_lkm' => ['Achieved 2D (LKM)', 'number'],
                'achieved_3d_sqkm' => ['Achieved 3D (Sq Km)', 'number'],
                'achieved_wells' => ['Achieved Wells', 'int'],
                'additional_2d_lkm' => ['Additional 2D (LKM)', 'number'],
                'additional_3d_sqkm' => ['Additional 3D (Sq Km)', 'number'],
                'additional_wells' => ['Additional Wells', 'int'],
                'wells_under_drilling' => ['Wells Under Drilling', 'int'],
                'location_under_drilling' => ['Location Under Drilling', 'text'],
                'current_drilled_depth_m' => ['Current Drilled Depth (m)', 'number'],
                'target_depth_m' => ['Target Depth (m)', 'number'],
                // Drilling completion % is calculated from the two depths (WorkProgramme model)
            ]],
            'Bank Guarantee (BG)' => ['bankGuarantee', [
                'phase' => ['Phase', 'text'],
                'amount_usd' => ['BG Amount (USD)', 'number'],
                'amount_inr' => ['BG Amount (INR)', 'number'],
                'validity_date' => ['BG Valid Till', 'date'],
                'claim_date' => ['BG Claim Date', 'date'],
                'renewal_days_before_expiry' => ['Renew how many days before expiry', 'int'],
                'renewal_timeline' => ['Renewal Timeline (as per RSC)', 'text'],
            ]],
        ];
    }

    /**
     * Form input name of a field. Section fields get their section as prefix
     * (licence_validity_date, bankGuarantee_validity_date) because names repeat across sections.
     */
    public static function inputName(?string $relation, string $name): string
    {
        return $relation ? "{$relation}_{$name}" : $name;
    }

    public function create()
    {
        return view('admin.block-form', ['block' => new Block(['status' => 'Active']), 'sections' => self::sections()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $block = DB::transaction(function () use ($request, $data) {
            $block = $this->saveAll(new Block, $data, $request->user());

            // Someone who only sees assigned blocks gets the block they created
            if (! $request->user()->canSeeAllBlocks()) {
                $request->user()->blocks()->syncWithoutDetaching([$block->id]);
            }

            return $block;
        });

        return redirect()->route('blocks.show', $block)->with('success', "Block {$block->code} added.");
    }

    public function edit(Request $request, Block $block)
    {
        abort_unless($request->user()->canAccessBlock($block), 403);

        return view('admin.block-form', [
            'block' => $block->load(['licence', 'timeline', 'workProgramme', 'bankGuarantee']),
            'sections' => self::sections(),
        ]);
    }

    public function update(Request $request, Block $block)
    {
        abort_unless($request->user()->canAccessBlock($block), 403);
        $data = $this->validated($request, $block);

        DB::transaction(fn () => $this->saveAll($block, $data, $request->user()));

        return redirect()->route('blocks.show', $block)->with('success', "Block {$block->code} updated.");
    }

    public function destroy(Request $request, Block $block)
    {
        abort_unless($request->user()->canAccessBlock($block), 403);

        DB::transaction(function () use ($block) {
            AuditLog::record('deleted', $block, ['code' => $block->code, 'basin' => $block->basin, 'status' => $block->status], [],
                "Block {$block->code} deleted with all its data");

            // Uploaded files are not removed by the database cascade
            foreach ($block->documents as $document) {
                Storage::disk('local')->delete($document->file_path);
            }
            $block->delete();
        });

        return redirect()->route('blocks.index')->with('success', "Block {$block->code} deleted.");
    }

    /** Save the block and each section that has data; log changes; rebuild its deadlines. */
    private function saveAll(Block $block, array $data, $user): Block
    {
        foreach (self::sections() as $title => [$relation, $fields]) {
            $values = [];
            foreach (array_keys($fields) as $name) {
                if (array_key_exists(self::inputName($relation, $name), $data)) {
                    $values[$name] = $data[self::inputName($relation, $name)];
                }
            }

            if ($relation === null) {
                $before = $block->getAttributes();
                $block->fill($values + ['updated_by' => $user->id])->save();
                AuditLog::recordChanges($block, $before, ($block->wasRecentlyCreated ? 'Block added: ' : 'Block updated: ').$block->code);

                continue;
            }

            $record = $block->{$relation}()->firstOrNew();
            if (! $record->exists && array_filter($values, fn ($v) => $v !== null && $v !== '') === []) {
                continue; // nothing entered for a section that does not exist yet
            }

            $before = $record->getAttributes();
            $record->fill($values);
            if ($record->isDirty() || ! $record->exists) {
                $record->updated_by = $user->id;
                $record->save();
                AuditLog::recordChanges($record, $before, "{$title} updated: {$block->code}");
            }
        }

        (new MilestoneGenerator(new ImportReport))->refreshBlock($block->fresh());

        return $block;
    }

    private function validated(Request $request, ?Block $block = null): array
    {
        $rules = [];
        foreach (self::sections() as [$relation, $fields]) {
            foreach ($fields as $name => [$label, $type]) {
                $rules[self::inputName($relation, $name)] = match ($type) {
                    'date' => ['nullable', 'date'],
                    'number' => ['nullable', 'numeric', 'min:0'],
                    'int' => ['nullable', 'integer', 'min:0'],
                    'textarea' => ['nullable', 'string', 'max:2000'],
                    'select' => ['nullable', Rule::in($fields[$name][2])],
                    default => ['nullable', 'string', 'max:255'],
                };
            }
        }
        $rules['code'] = ['required', 'string', 'max:50', Rule::unique('blocks', 'code')->ignore($block?->id)];
        $rules['basin'] = ['required', 'string', 'max:100'];
        $rules['status'] = ['required', Rule::in(['Active', 'Relinquished'])];

        $data = $request->validate($rules);
        $data['code'] = strtoupper(trim($data['code']));

        return $data;
    }
}

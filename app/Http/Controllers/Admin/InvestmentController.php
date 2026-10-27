<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Block;
use App\Services\Import\ImportReport;
use App\Services\Import\MilestoneGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Add / edit the investment, LD, paid extension and BG figures of a block (one row per block). */
class InvestmentController extends Controller
{
    /** Investment fields => label (all numbers) */
    public const FIELDS = [
        'invested_seismic_inr' => 'Investment: Seismic (INR)',
        'invested_wells_inr' => 'Investment: Wells (INR)',
        'invested_others_inr' => 'Investment: Others (INR)',
        'invested_total_inr' => 'Investment: Total (INR)',
        'ld_seismic_musd' => 'LD Paid: Seismic (Mill USD)',
        'ld_wells_musd' => 'LD Paid: Wells (Mill USD)',
        'ld_others_musd' => 'LD Paid: Others (Mill USD)',
        'ld_total_musd' => 'LD Paid: Total (Mill USD)',
        'ld_total_cr_inr' => 'LD Paid: Total (Cr INR)',
        'paid_extension_quarters' => 'Paid Extension: Quarters',
        'paid_extension_usd' => 'Paid Extension: Amount (USD)',
        'paid_extension_inr' => 'Paid Extension: Amount (INR)',
        'total_outgo_inr' => 'Total Outgo (INR)',
    ];

    /** Bank guarantee fields shown on the same form => [label, type] */
    public const BG_FIELDS = [
        'amount_usd' => ['BG Amount (USD)', 'number'],
        'amount_inr' => ['BG Amount (INR)', 'number'],
        'validity_date' => ['BG Valid Till', 'date'],
        'claim_date' => ['BG Claim Date', 'date'],
    ];

    /** Add: pick a block, then fill its figures. */
    public function create(Request $request)
    {
        $blocks = Block::visibleTo($request->user())->with('investment:id,block_id')->orderBy('code')->get(['id', 'code']);

        return view('admin.investment-form', [
            'block' => $request->filled('block') ? $blocks->firstWhere('id', $request->integer('block')) : null,
            'blocks' => $blocks,
            'investment' => null,
            'bg' => null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['block_id' => ['required', 'integer', 'exists:blocks,id']]);
        $block = Block::findOrFail($request->integer('block_id'));

        return $this->save($request, $block, 'added');
    }

    public function edit(Request $request, Block $block)
    {
        abort_unless($request->user()->canAccessBlock($block), 403);

        return view('admin.investment-form', [
            'block' => $block,
            'blocks' => null,
            'investment' => $block->investment,
            'bg' => $block->bankGuarantee,
        ]);
    }

    public function update(Request $request, Block $block)
    {
        return $this->save($request, $block, 'updated');
    }

    private function save(Request $request, Block $block, string $action)
    {
        abort_unless($request->user()->canAccessBlock($block), 403, 'You can only manage investment of your assigned blocks.');

        $rules = array_map(fn () => ['nullable', 'numeric', 'min:0'], self::FIELDS);
        $rules['paid_extension_quarters'] = ['nullable', 'integer', 'min:0'];
        foreach (self::BG_FIELDS as $name => [$label, $type]) {
            $rules["bg_{$name}"] = $type === 'date' ? ['nullable', 'date'] : ['nullable', 'numeric', 'min:0'];
        }
        $data = $request->validate($rules);

        DB::transaction(function () use ($request, $block, $data) {
            $userId = $request->user()->id;

            $investment = $block->investment()->firstOrNew();
            $before = $investment->getAttributes();
            $investment->fill(array_intersect_key($data, self::FIELDS));
            if ($investment->isDirty() || (! $investment->exists && array_filter(array_intersect_key($data, self::FIELDS), fn ($v) => $v !== null))) {
                $investment->updated_by = $userId;
                $investment->save();
                AuditLog::recordChanges($investment, $before, "Investment saved: {$block->code}");
            }

            $bgValues = [];
            foreach (array_keys(self::BG_FIELDS) as $name) {
                $bgValues[$name] = $data["bg_{$name}"] ?? null;
            }
            $bg = $block->bankGuarantee()->firstOrNew();
            $bgBefore = $bg->getAttributes();
            $bg->fill($bgValues);
            if ($bg->isDirty() && ($bg->exists || array_filter($bgValues, fn ($v) => $v !== null))) {
                $bg->updated_by = $userId;
                $bg->save();
                AuditLog::recordChanges($bg, $bgBefore, "Bank guarantee saved: {$block->code}");

                // BG validity drives the BG renewal deadline
                (new MilestoneGenerator(new ImportReport))->refreshBlock($block);
            }
        });

        return redirect()->route('investment.index')->with('success', "Investment details {$action} for {$block->code}.");
    }
}

<?php

namespace App\Services\Import;

use App\Models\BankGuarantee;
use App\Models\Basin;
use App\Models\Block;
use App\Models\BlockTimeline;
use App\Models\Clearance;
use App\Models\Investment;
use App\Models\Licence;
use App\Models\Location;
use App\Models\McMeeting;
use App\Models\WorkProgramme;
use App\Support\Excel\Cell;
use App\Support\Excel\XlsxReader;
use RuntimeException;

/**
 * Imports Blocks_database_for_portal_v2.xlsx.
 * Safe to run again: rows are matched on natural keys (block code, location name ...) and updated.
 */
class BlocksDatabaseImporter
{
    /** Data rows start after the 2-row header in the per-block sheets. */
    private const FIRST_DATA_ROW = 4;

    /** @var array<string, int> block code => id */
    private array $blockIds = [];

    public function __construct(private ImportReport $report)
    {
    }

    public function import(XlsxReader $xlsx): void
    {
        $this->importBasins($xlsx);
        $this->importBlocks($xlsx);
        $this->importLicences($xlsx);
        $this->importTimelines($xlsx);
        $this->importWorkProgrammes($xlsx);
        $this->importBankGuarantees($xlsx);
        $this->importInvestments($xlsx);
        $this->importMcMeetings($xlsx);
        $this->importLocations($xlsx);
        $this->importClearances($xlsx);
    }

    private function importBasins(XlsxReader $xlsx): void
    {
        if (! $xlsx->hasSheet('Backup Database')) {
            return;
        }

        foreach ($xlsx->rows('Backup Database') as $rowNo => $row) {
            $name = Cell::text($row['A'] ?? null);
            if ($rowNo < 3 || $name === null) {
                continue;
            }
            Basin::updateOrCreate(['name' => $name]);
            $this->report->count('basins');
        }
    }

    private function importBlocks(XlsxReader $xlsx): void
    {
        foreach ($this->blockRows($xlsx, 'Block Details') as $code => $row) {
            $block = $this->save(Block::class, ['code' => $code], [
                'regime' => Cell::text($row['C'] ?? null),
                'bid_round' => Cell::text($row['D'] ?? null),
                'category' => Cell::text($row['E'] ?? null),
                'basin' => Cell::text($row['F'] ?? null),
                'basin_category' => Cell::text($row['G'] ?? null),
                'state' => Cell::text($row['H'] ?? null),
                'districts' => $this->districts($row['I'] ?? null),
                'area_original' => Cell::number($row['J'] ?? null),
                'area_revised' => Cell::number($row['K'] ?? null),
                'area_revision_date' => Cell::date($row['L'] ?? null),
                'rsc_signing_date' => Cell::date($row['M'] ?? null),
                'status' => Cell::text($row['N'] ?? null) ?? 'Active',
                'current_stage' => Cell::text($row['O'] ?? null),
                'relinquishment_date' => Cell::date($row['P'] ?? null),
            ]);

            $this->blockIds[$code] = $block->id;
            $this->report->count('blocks');
        }

        if (! $this->blockIds) {
            throw new RuntimeException('No blocks found in the "Block Details" sheet.');
        }
    }

    private function importLicences(XlsxReader $xlsx): void
    {
        foreach ($this->blockRows($xlsx, 'PEL _PML_PL validity') as $code => $row) {
            if (! $blockId = $this->blockId($code, 'PEL/PL validity')) {
                continue;
            }

            $this->warnIfUnreadableDate($row['G'] ?? null, "PEL/PL validity date for {$code}");

            $this->save(Licence::class, ['block_id' => $blockId], [
                'granting_authority' => Cell::text($row['C'] ?? null),
                'initial_grant_date_text' => Cell::text($row['D'] ?? null),
                'initial_grant_date' => Cell::date($row['D'] ?? null),
                'effective_date' => Cell::date($row['E'] ?? null),
                'pel_to_pl_converted' => Cell::text($row['F'] ?? null),
                'validity_date' => Cell::date($row['G'] ?? null),
                'current_year' => Cell::text($row['H'] ?? null),
                'fee_rate' => Cell::number($row['I'] ?? null),
                'fee_amount' => Cell::number($row['J'] ?? null),
            ]);
            $this->report->count('licences');
        }
    }

    private function importTimelines(XlsxReader $xlsx): void
    {
        foreach ($this->blockRows($xlsx, 'Timeline details') as $code => $row) {
            if (! $blockId = $this->blockId($code, 'Timeline details')) {
                continue;
            }

            $this->save(BlockTimeline::class, ['block_id' => $blockId], [
                'contractual_structure' => Cell::text($row['D'] ?? null),
                'phase1_duration' => Cell::text($row['E'] ?? null),
                'phase1_extension_allowed' => Cell::text($row['F'] ?? null),
                'phase2_duration' => Cell::text($row['G'] ?? null),
                'phase2_extension_allowed' => Cell::text($row['H'] ?? null),
                'current_phase' => Cell::text($row['I'] ?? null),
                'phase1_start_date' => Cell::date($row['J'] ?? null),
                'phase1_validity_contractual' => Cell::date($row['K'] ?? null),
                'phase1_extensions_granted' => Cell::multiline($row['L'] ?? null),
                'phase1_validity_current' => Cell::date($row['M'] ?? null),
                'policy_2025_availed' => Cell::text($row['N'] ?? null),
                'phase2_start_date' => Cell::date($row['O'] ?? null),
                'phase2_validity_contractual' => Cell::date($row['P'] ?? null),
                'phase2_extensions_granted' => Cell::multiline($row['Q'] ?? null),
                'phase2_validity_revised' => Cell::date($row['R'] ?? null),
            ]);
            $this->report->count('block_timelines');
        }
    }

    private function importWorkProgrammes(XlsxReader $xlsx): void
    {
        foreach ($this->blockRows($xlsx, 'CWP details') as $code => $row) {
            if (! $blockId = $this->blockId($code, 'CWP details')) {
                continue;
            }

            // Excel stores completion as a fraction (0.589), the portal uses a percentage
            $pct = Cell::number($row['P'] ?? null);

            $this->save(WorkProgramme::class, ['block_id' => $blockId], [
                'committed_2d_lkm' => Cell::number($row['C'] ?? null),
                'committed_3d_sqkm' => Cell::number($row['D'] ?? null),
                'committed_wells' => Cell::integer($row['E'] ?? null),
                'achieved_2d_lkm' => Cell::number($row['F'] ?? null),
                'achieved_3d_sqkm' => Cell::number($row['G'] ?? null),
                'achieved_wells' => Cell::integer($row['H'] ?? null),
                'additional_2d_lkm' => Cell::number($row['I'] ?? null),
                'additional_3d_sqkm' => Cell::number($row['J'] ?? null),
                'additional_wells' => Cell::integer($row['K'] ?? null),
                'wells_under_drilling' => Cell::integer($row['L'] ?? null),
                'location_under_drilling' => Cell::text($row['M'] ?? null),
                'current_drilled_depth_m' => Cell::number($row['N'] ?? null),
                'target_depth_m' => Cell::number($row['O'] ?? null),
                'drilling_completion_pct' => $pct === null ? null : round($pct <= 1 ? $pct * 100 : $pct, 2),
            ]);
            $this->report->count('work_programmes');
        }
    }

    private function importBankGuarantees(XlsxReader $xlsx): void
    {
        foreach ($this->blockRows($xlsx, 'BG details') as $code => $row) {
            if (! $blockId = $this->blockId($code, 'BG details')) {
                continue;
            }

            // Relinquished blocks have an empty BG row
            if (Cell::number($row['D'] ?? null) === null && Cell::date($row['F'] ?? null) === null) {
                continue;
            }

            $timeline = Cell::text($row['H'] ?? null);
            preg_match('/(\d+)\s*days?/i', (string) $timeline, $days);

            $this->save(BankGuarantee::class, ['block_id' => $blockId], [
                'phase' => Cell::text($row['C'] ?? null),
                'amount_usd' => Cell::number($row['D'] ?? null),
                'amount_inr' => Cell::number($row['E'] ?? null),
                'validity_date' => Cell::date($row['F'] ?? null),
                'claim_date' => Cell::date($row['G'] ?? null),
                'renewal_timeline' => $timeline,
                'renewal_days_before_expiry' => isset($days[1]) ? (int) $days[1] : null,
            ]);
            $this->report->count('bank_guarantees');
        }
    }

    private function importInvestments(XlsxReader $xlsx): void
    {
        foreach ($this->blockRows($xlsx, 'Investment details') as $code => $row) {
            if (! $blockId = $this->blockId($code, 'Investment details')) {
                continue;
            }

            $values = [
                'invested_seismic_inr' => Cell::number($row['C'] ?? null),
                'invested_wells_inr' => Cell::number($row['D'] ?? null),
                'invested_others_inr' => Cell::number($row['E'] ?? null),
                'invested_total_inr' => Cell::number($row['F'] ?? null),
                'ld_seismic_musd' => Cell::number($row['G'] ?? null),
                'ld_wells_musd' => Cell::number($row['H'] ?? null),
                'ld_others_musd' => Cell::number($row['I'] ?? null),
                'ld_total_musd' => Cell::number($row['J'] ?? null),
                'ld_total_cr_inr' => Cell::number($row['K'] ?? null),
                'paid_extension_quarters' => Cell::integer($row['L'] ?? null),
                'paid_extension_usd' => Cell::number($row['M'] ?? null),
                'paid_extension_inr' => Cell::number($row['N'] ?? null),
                'total_outgo_inr' => Cell::number($row['O'] ?? null),
            ];

            if (array_filter($values, fn ($v) => $v !== null) === []) {
                continue; // nothing filled in for this block yet
            }

            $this->save(Investment::class, ['block_id' => $blockId], $values);
            $this->report->count('investments');
        }
    }

    /** "i) 1st- 15.09.2020\nii) 2nd- 15.06.2021" => one row per meeting. */
    private function importMcMeetings(XlsxReader $xlsx): void
    {
        foreach ($this->blockRows($xlsx, 'MC meeting details') as $code => $row) {
            if (! $blockId = $this->blockId($code, 'MC meeting details')) {
                continue;
            }

            preg_match_all('/(\d+)[ \t]*(?:st|nd|rd|th)[ \t]*-[ \t]*([^\n]*)/i', (string) ($row['D'] ?? ''), $matches, PREG_SET_ORDER);

            foreach ($matches as [, $number, $dateText]) {
                $date = Cell::date($dateText);
                if ($date === null) {
                    $this->report->warn("MC meeting #{$number} of {$code} has no date in Excel.");
                }

                McMeeting::updateOrCreate(
                    ['block_id' => $blockId, 'meeting_no' => (int) $number],
                    ['meeting_date' => $date]
                );
                $this->report->count('mc_meetings');
            }

            $declared = Cell::integer($row['C'] ?? null);
            if ($declared !== null && $declared !== count($matches)) {
                $this->report->warn("{$code}: Excel says {$declared} MC meetings but lists ".count($matches).' dates.');
            }
        }
    }

    /**
     * Location inventory packs several locations into one cell per column, e.g.
     * "OBRA : 1460 m, OBRD: 1500 m". Column D gives the location codes; every other column is split on them.
     */
    private function importLocations(XlsxReader $xlsx): void
    {
        foreach ($this->blockRows($xlsx, 'Location inventory') as $code => $row) {
            if (! $blockId = $this->blockId($code, 'Location inventory')) {
                continue;
            }

            preg_match_all('/([A-Z][A-Z0-9]*(?:-[A-Z0-9]+)?)\s*:/', (string) ($row['D'] ?? ''), $m);
            $names = array_values(array_unique($m[1]));

            if (! $names) {
                continue;
            }

            $declared = Cell::integer($row['C'] ?? null);
            if ($declared !== null && $declared !== count($names)) {
                $this->report->warn("{$code}: Excel says {$declared} locations released but lists ".count($names).'.');
            }

            $columns = [];
            foreach (['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'] as $col) {
                $columns[$col] = $this->splitByLocation((string) ($row[$col] ?? ''), $names);
            }

            foreach ($names as $name) {
                $edpmbDate = $columns['G'][$name] ?? null;
                if (Cell::hasUnreadableDate($edpmbDate)) {
                    $this->report->warn("EDPMB date \"{$edpmbDate}\" for location {$name} ({$code}) is not a valid date. Kept as text.");
                }

                $status = Cell::text($columns['H'][$name] ?? null);

                $this->save(Location::class, ['block_id' => $blockId, 'name' => $name], [
                    'target_depth_m' => Cell::number(preg_replace('/[^\d.]/', '', (string) ($columns['D'][$name] ?? ''))),
                    'targets' => Cell::text($columns['E'][$name] ?? null),
                    'edpmb_no' => Cell::text($columns['F'][$name] ?? null),
                    'edpmb_date' => Cell::date($edpmbDate),
                    'edpmb_date_text' => Cell::text($edpmbDate),
                    'drilling_status' => $status,
                    'actual_drilled_depth' => Cell::text($columns['I'][$name] ?? null),
                    'key_findings' => Cell::text($columns['J'][$name] ?? null),
                    'studies_carried_out' => Cell::text($columns['K'][$name] ?? null),
                    'expected_drilling_start' => Cell::text($columns['L'][$name] ?? null),
                    'planned_completion' => Cell::text($columns['M'][$name] ?? null),
                    'current_status' => Cell::text($columns['N'][$name] ?? null),
                ]);
                $this->report->count('locations');
            }
        }
    }

    /**
     * One Excel row (location) becomes three clearance rows: EC, CTE and CTO.
     */
    private function importClearances(XlsxReader $xlsx): void
    {
        $rows = $xlsx->rows('Statutory clearances');
        $columns = ['EC' => ['D', 'E', 'F'], 'CTE' => ['G', 'H', 'I'], 'CTO' => ['J', 'K', 'L']];

        foreach ($rows as $rowNo => $row) {
            $code = Cell::text($row['B'] ?? null);
            if ($rowNo < 2 || $code === null || ! $blockId = $this->blockId($code, 'Statutory clearances')) {
                continue;
            }

            $locationName = Cell::text($row['C'] ?? null) ?? 'NIL';
            $location = Location::where('block_id', $blockId)->where('name', $locationName)->first();

            if (! $location && ! Cell::isEmpty($locationName)) {
                $this->report->warn("Statutory clearance location \"{$locationName}\" ({$code}) does not match any location in Location inventory.");
            }

            foreach ($columns as $type => [$appliedCol, $receivedCol, $validCol]) {
                $applied = $row[$appliedCol] ?? null;
                $received = $row[$receivedCol] ?? null;
                $valid = $row[$validCol] ?? null;

                // Free text written instead of a date ("Not applied", "Applied, however pending ...")
                $notes = array_filter([$applied, $received, $valid], fn ($v) => Cell::hasUnreadableDate($v));
                $note = Cell::text(implode('; ', array_unique($notes)));

                $this->save(Clearance::class, 
                    ['block_id' => $blockId, 'location_name' => $locationName, 'type' => $type],
                    [
                        'location_id' => $location?->id,
                        'project' => Cell::text($row['A'] ?? null),
                        'status' => $this->clearanceStatus($applied, $received, $valid),
                        'applied_on' => Cell::date($applied),
                        'received_on' => Cell::date($received),
                        'valid_until' => Cell::date($valid),
                        'note' => $note,
                        'remarks' => Cell::text($row['M'] ?? null),
                    ]
                );
                $this->report->count('clearances');
            }
        }
    }

    private function clearanceStatus(?string $applied, ?string $received, ?string $valid): string
    {
        if (Cell::date($received) || Cell::date($valid)) {
            return 'received';
        }
        if (stripos((string) $applied, 'not applied') !== false) {
            return 'not_applied';
        }
        if (Cell::date($applied) || stripos((string) $applied, 'applied') !== false) {
            return 'applied';
        }

        return 'not_applicable';
    }

    /**
     * updateOrCreate, except a record someone edited in the portal (updated_by set)
     * is kept as it is: the portal edit wins over the Excel.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $class
     */
    private function save(string $class, array $keys, array $values): \Illuminate\Database\Eloquent\Model
    {
        $existing = $class::where($keys)->first();

        if ($existing && $existing->updated_by) {
            $this->report->count('kept (edited in portal)');

            return $existing;
        }

        return $class::updateOrCreate($keys, $values);
    }

    /**
     * Split "OBRA: x   OBRD: y" into ['OBRA' => 'x', 'OBRD' => 'y'].
     * Text with no location label is given to every location.
     *
     * @param  list<string>  $names
     * @return array<string, string>
     */
    private function splitByLocation(string $text, array $names): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        usort($names, fn ($a, $b) => strlen($b) <=> strlen($a));
        $pattern = '/(?<![A-Z0-9-])('.implode('|', array_map('preg_quote', $names)).')\s*:/';

        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($parts) === 1) {
            return array_fill_keys($names, $text);
        }

        $result = [];
        for ($i = 1; $i < count($parts); $i += 2) {
            $result[$parts[$i]] = trim($parts[$i + 1] ?? '', " \t\n\r,.");
        }

        return $result;
    }

    /**
     * Data rows of a per-block sheet, keyed by block code.
     *
     * @return array<string, array<string, string>>
     */
    private function blockRows(XlsxReader $xlsx, string $sheet): array
    {
        if (! $xlsx->hasSheet($sheet)) {
            $this->report->warn("Sheet \"{$sheet}\" is missing, skipped.");

            return [];
        }

        $rows = $xlsx->rows($sheet);

        if (strcasecmp(trim($rows[2]['B'] ?? ''), 'Block') !== 0) {
            throw new RuntimeException("Sheet \"{$sheet}\" layout changed: expected \"Block\" in cell B2.");
        }

        $result = [];
        foreach ($rows as $rowNo => $row) {
            $code = Cell::text($row['B'] ?? null);
            if ($rowNo >= self::FIRST_DATA_ROW && $code !== null) {
                $result[$code] = $row;
            }
        }

        return $result;
    }

    private function blockId(string $code, string $sheet): ?int
    {
        if (! isset($this->blockIds[$code])) {
            $this->report->warn("Block \"{$code}\" in sheet \"{$sheet}\" is not in Block Details, skipped.");

            return null;
        }

        return $this->blockIds[$code];
    }

    private function districts(?string $value): ?string
    {
        $parts = array_filter(array_map('trim', preg_split('/[,\n]+/', (string) $value)));

        return $parts && ! Cell::isEmpty($value) ? implode(', ', $parts) : null;
    }

    private function warnIfUnreadableDate(?string $value, string $label): void
    {
        if (Cell::hasUnreadableDate($value)) {
            $this->report->warn("{$label} \"{$value}\" is not a valid date.");
        }
    }
}

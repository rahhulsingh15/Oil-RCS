<?php

namespace App\Services\Import;

use App\Models\ComplianceItem;
use App\Support\Excel\Cell;
use App\Support\Excel\XlsxReader;
use RuntimeException;

/**
 * Imports Master_RSC_Compliance_Register_Template.xlsx (the 28 RSC obligations and their alert days).
 * Department / officer / authority are only overwritten when the Excel cell has a value,
 * so details filled in later through the portal are not wiped by a re-import.
 */
class ComplianceRegisterImporter
{
    public function __construct(private ImportReport $report)
    {
    }

    public function import(XlsxReader $xlsx): void
    {
        $sheet = $xlsx->sheetNames()[0] ?? throw new RuntimeException('Compliance register has no sheets.');
        $rows = $xlsx->rows($sheet);

        if (strcasecmp(trim($rows[1]['A'] ?? ''), 'Compliance ID') !== 0) {
            throw new RuntimeException('Compliance register layout changed: expected "Compliance ID" in cell A1.');
        }

        $missingOwner = 0;

        foreach ($rows as $rowNo => $row) {
            $code = Cell::text($row['A'] ?? null);
            if ($rowNo < 2 || $code === null) {
                continue;
            }

            $values = [
                'head' => Cell::text($row['B'] ?? null) ?? '',
                'obligation' => Cell::text($row['C'] ?? null) ?? '',
                'article' => Cell::text($row['D'] ?? null),
                'trigger_event' => Cell::text($row['E'] ?? null),
                'frequency' => Cell::text($row['F'] ?? null),
                'compliance_type' => Cell::text($row['G'] ?? null),
                'alert_days_before' => Cell::integer($row['K'] ?? null),
            ];

            $optional = [
                'responsible_department' => Cell::text($row['H'] ?? null),
                'nodal_officer' => Cell::text($row['I'] ?? null),
                'approving_authority' => Cell::text($row['J'] ?? null),
                'status' => Cell::text($row['L'] ?? null),
                'supporting_documents' => Cell::text($row['M'] ?? null),
                'remarks' => Cell::text($row['N'] ?? null),
            ];

            ComplianceItem::updateOrCreate(
                ['code' => $code],
                $values + array_filter($optional, fn ($v) => $v !== null)
            );
            $this->report->count('compliance_items');

            if ($optional['nodal_officer'] === null) {
                $missingOwner++;
            }
            if ($values['alert_days_before'] === null) {
                $this->report->warn("Compliance {$code} has no \"Alert (Days Before)\" value.");
            }
        }

        if ($missingOwner) {
            $this->report->warn("{$missingOwner} compliance items have no Nodal Officer / Responsible Department, so alert emails have no named recipient yet.");
        }
    }
}

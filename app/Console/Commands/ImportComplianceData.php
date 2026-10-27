<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\Import\BlocksDatabaseImporter;
use App\Services\Import\ComplianceRegisterImporter;
use App\Services\Import\ImportReport;
use App\Services\Import\MilestoneGenerator;
use App\Support\Excel\XlsxReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportComplianceData extends Command
{
    protected $signature = 'compliance:import
        {--blocks= : Path to Blocks_database_for_portal xlsx (default: database/data/Blocks_database_for_portal_v2.xlsx)}
        {--register= : Path to Master_RSC_Compliance_Register xlsx (default: database/data/Master_RSC_Compliance_Register_Template.xlsx)}';

    protected $description = 'Import blocks, clearances and the RSC compliance register from the client Excel files, then build milestones';

    public function handle(): int
    {
        $blocksPath = $this->option('blocks') ?: database_path('data/Blocks_database_for_portal_v2.xlsx');
        $registerPath = $this->option('register') ?: database_path('data/Master_RSC_Compliance_Register_Template.xlsx');

        $report = new ImportReport;

        try {
            $blocks = new XlsxReader($blocksPath);
            $register = new XlsxReader($registerPath);

            // All or nothing: a broken file must not leave half-imported data
            DB::transaction(function () use ($blocks, $register, $report) {
                (new ComplianceRegisterImporter($report))->import($register);
                (new BlocksDatabaseImporter($report))->import($blocks);
                (new MilestoneGenerator($report))->generate();

                AuditLog::record('imported', null, [], $report->counts,
                    'Excel import: '.basename($this->option('blocks') ?: 'Blocks_database_for_portal_v2.xlsx')
                    .' + '.basename($this->option('register') ?: 'Master_RSC_Compliance_Register_Template.xlsx'));
            });
        } catch (Throwable $e) {
            $this->error('Import failed, nothing was saved: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Import completed.');
        $this->table(['Table', 'Rows imported / updated'],
            collect($report->counts)->map(fn ($n, $table) => [$table, $n])->values()->all());

        if ($report->warnings) {
            $this->newLine();
            $this->warn(count($report->warnings).' data issues found in the Excel files (please confirm with the client):');
            foreach ($report->warnings as $i => $warning) {
                $this->line('  '.($i + 1).'. '.$warning);
            }
        }

        return self::SUCCESS;
    }
}

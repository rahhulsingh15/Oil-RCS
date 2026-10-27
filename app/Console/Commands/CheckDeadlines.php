<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AlertService;
use Illuminate\Console\Command;

/** Runs every day at 08:00 (routes/console.php). Can also be run by hand or from the Notifications page. */
class CheckDeadlines extends Command
{
    protected $signature = 'alerts:check';

    protected $description = 'Check all deadlines and create in-portal notifications for upcoming and overdue items (no email)';

    public function handle(AlertService $alerts): int
    {
        $result = $alerts->run();

        AuditLog::record('alerts-check', null, [], $result,
            "Deadline check: {$result['upcoming']} upcoming, ".($result['reminder'] ?? 0)." reminder, {$result['overdue']} overdue, {$result['notified']} notifications");

        $this->info("Deadline check done: {$result['upcoming']} new due soon, ".($result['reminder'] ?? 0)." new reminder, {$result['overdue']} new overdue, {$result['notified']} notifications sent to users.");

        return self::SUCCESS;
    }
}

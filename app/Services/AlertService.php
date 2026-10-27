<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Milestone;
use App\Models\User;
use App\Notifications\DeadlineAlert;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * The daily deadline check.
 *
 * For every open milestone (Pending / In Progress) of an active block:
 *   - Stage 1: at alert_days_before (e.g. 30, 180, 90) -> in-portal "Due Soon" notification
 *   - Stage 2: at half of alert window (e.g. 15 for 30, 90 for 180) -> in-portal "Reminder" notification
 *   - Stage 3: past its due date -> in-portal "Overdue" notification
 * Each user gets each alert type only once. Notifications go to active users who
 * can see Compliance Monitor and have access to that block (e.g. the block's nodal officer).
 * Purely in-portal notifications; zero email.
 */
class AlertService
{
    /** @return array{upcoming: int, reminder: int, overdue: int, notified: int} */
    public function run(): array
    {
        $result = ['upcoming' => 0, 'reminder' => 0, 'overdue' => 0, 'notified' => 0];
        $users = $this->candidateUsers();

        $milestones = Milestone::open()
            ->with('block:id,code,status')
            ->whereHas('block', fn ($q) => $q->where('status', '!=', 'Relinquished'))
            ->get();

        foreach ($milestones as $milestone) {
            $daysLeft = $milestone->daysLeft();

            // Stage 3: Overdue (past due date)
            if ($daysLeft < 0) {
                $this->notifyAlert($milestone, Alert::TYPE_OVERDUE, $users, $result);
                continue;
            }

            // Stage 2: Reminder alert at half-days (e.g. 15 for 30, 90 for 180, 45 for 90)
            if ($milestone->isHalfAlertWindow()) {
                $this->notifyAlert($milestone, Alert::TYPE_REMINDER, $users, $result);
                continue;
            }

            // Stage 1: Initial alert at alert_days_before (e.g. 30 din, 180 din, 90 din pehle)
            if ($daysLeft <= $milestone->alert_days_before) {
                $this->notifyAlert($milestone, Alert::TYPE_UPCOMING, $users, $result);
            }
        }

        return $result;
    }

    private function notifyAlert(Milestone $milestone, string $type, Collection $users, array &$result): void
    {
        $alert = Alert::firstOrCreate(
            ['milestone_id' => $milestone->id, 'type' => $type],
            ['triggered_on' => today(), 'message' => $this->message($milestone, $type)]
        );
        if ($alert->wasRecentlyCreated) {
            $result[$type]++;
        }

        // Everyone who should know and has not been notified about this alert yet
        $alreadyNotified = DatabaseNotification::where('type', DeadlineAlert::class)
            ->where('data->alert_id', $alert->id)
            ->pluck('notifiable_id')
            ->all();

        $recipients = $users->filter(fn (User $u) => $u->canAccessBlock($milestone->block_id)
            && ! in_array($u->id, $alreadyNotified));

        foreach ($recipients as $user) {
            $user->notify(new DeadlineAlert($alert, $milestone));
            $result['notified']++;
        }
    }

    /** Active users whose role can see deadlines (Compliance Monitor). */
    private function candidateUsers(): Collection
    {
        return User::with('role')->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->hasPermission('milestones.view'))
            ->values();
    }

    private function message(Milestone $m, string $type): string
    {
        $what = $m->shortLabel().($m->location_name ? " ({$m->location_name})" : '');
        $due = $m->due_date->format('d.m.Y');
        $days = $m->daysLeft();

        return match ($type) {
            Alert::TYPE_OVERDUE => "{$what} of {$m->block->code} was due on {$due} and is overdue.",
            Alert::TYPE_REMINDER => "Reminder: {$what} of {$m->block->code} is due in {$days} days on {$due}.",
            default => "{$what} of {$m->block->code} is due on {$due}.",
        };
    }
}



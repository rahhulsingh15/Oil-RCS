<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\Milestone;
use Illuminate\Notifications\Notification;

/**
 * In-portal notification for a deadline that is near (upcoming) or missed (overdue).
 * Saved in the database only; no email is sent.
 */
class DeadlineAlert extends Notification
{
    public function __construct(private Alert $alert, private Milestone $milestone)
    {
    }

    /** Database only: shown in the bell, on the dashboard and on the Notifications page. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $m = $this->milestone;

        return [
            'alert_id' => $this->alert->id,
            'milestone_id' => $m->id,
            'block_id' => $m->block_id,
            'block_code' => $m->block->code,
            'type' => $this->alert->type,                // upcoming / overdue
            'item' => $m->shortLabel(),
            'location' => $m->location_name,
            'title' => $m->title,
            'due_date' => $m->due_date->toDateString(),
            'message' => $this->alert->message,
        ];
    }
}

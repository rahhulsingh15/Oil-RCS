<?php

use Illuminate\Support\Facades\Schedule;

/*
| Daily deadline check: creates in-portal notifications (no email).
| Needs the Laravel scheduler running:
|   - server: cron  * * * * *  php /path/to/artisan schedule:run
|   - Windows (XAMPP): Task Scheduler, every minute: php artisan schedule:run
*/
Schedule::command('alerts:check')->dailyAt('08:00')->withoutOverlapping();

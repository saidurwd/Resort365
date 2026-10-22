<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backups (Step 7.5, docs/OPERATIONS.md): the nightly database and files backup, its clean-up, and a morning check that it is fresh.
Schedule::command('backup:clean')->dailyAt('01:00')->withoutOverlapping()->onOneServer();
Schedule::command('backup:run')->dailyAt('01:15')->withoutOverlapping()->onOneServer();
Schedule::command('backup:monitor')->dailyAt('07:00')->onOneServer();

// Horizon's metrics graphs.
Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();

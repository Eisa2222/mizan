<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| SaaS scheduled tasks
|--------------------------------------------------------------------------
| Triggered by the system cron entry installed by deploy/install.sh
| (* * * * * php artisan schedule:run).
*/
Schedule::command('saas:check-trial-expiry')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('saas:send-trial-warnings')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

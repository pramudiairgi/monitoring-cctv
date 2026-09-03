<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cameras:check-status')->everyMinute()->withoutOverlapping();
Schedule::command('telemetry:prune --hours=6')->hourly()->withoutOverlapping();

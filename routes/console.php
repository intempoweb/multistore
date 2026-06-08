<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('erp:dispatch-daily-syncs')
    ->dailyAt('18:00')
    ->timezone('Europe/Rome')
    ->withoutOverlapping(240)
    ->appendOutputTo(storage_path('logs/erp-scheduler.log'));
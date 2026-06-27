<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Export ordini ERP ogni 15 minuti
Schedule::command('erp:export-orders --limit=100')
    ->everyFifteenMinutes()
    ->timezone('Europe/Rome')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/erp-orders.log'));

// Stock ERP ogni 30 minuti
Schedule::command('erp:sync-stock --ditte=1 --ditte=3')
    ->everyThirtyMinutes()
    ->timezone('Europe/Rome')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/erp-stock.log'));

// Sincronizzazione completa catalogo/clienti ogni notte
Schedule::command('erp:dispatch-daily-syncs')
    ->dailyAt('02:00')
    ->timezone('Europe/Rome')
    ->withoutOverlapping(240)
    ->appendOutputTo(storage_path('logs/erp-scheduler.log'));

// Invio report ERP e pulizia log dopo la finestra notturna
Schedule::command('erp:send-report')
    ->dailyAt('12:00')
    ->timezone('Europe/Rome')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/erp-report.log'));

// Prezzi e listini ogni lunedì mattina
Schedule::command('erp:sync-public-prices --ditte=1 --ditte=3')
    ->mondays()
    ->at('06:00')
    ->timezone('Europe/Rome')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/erp-prices.log'));

Schedule::command('erp:sync-price-tiers --ditte=1 --ditte=3')
    ->mondays()
    ->at('06:15')
    ->timezone('Europe/Rome')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/erp-price-tiers.log'));

Schedule::command('erp:sync-customer-listini --ditte=1 --ditte=3')
    ->mondays()
    ->at('06:30')
    ->timezone('Europe/Rome')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/erp-customer-listini.log'));
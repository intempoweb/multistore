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

// Sincronizzazione completa catalogo/clienti ogni notte.
// Il lunedì include anche prezzi e listini.
Schedule::command('erp:dispatch-daily-syncs')
    ->dailyAt('02:00')
    ->timezone('Europe/Rome')
    ->withoutOverlapping(240)
    ->appendOutputTo(storage_path('logs/erp-scheduler.log'));

// Pulizia zip foto prodotto ordine caricati su S3
Schedule::command('order-product-images:cleanup')
    ->dailyAt('04:30')
    ->timezone('Europe/Rome')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/order-product-images-cleanup.log'));

// Invio report ERP dopo il completamento della sincronizzazione
Schedule::command('erp:send-report')
    ->dailyAt('07:00')
    ->timezone('Europe/Rome')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/erp-report.log'));
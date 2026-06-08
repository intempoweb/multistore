<?php

namespace App\Console\Commands;

use App\Services\Erp\StockSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncStock extends Command
{
    protected $signature = 'erp:sync-stock
        {--ditte=* : (Opzionale) Filtra per ditte locali da aggiornare (products.ditta_cg18)}
        {--sites=* : (Opzionale) Filtra per siti (products.site_type)}
        {--since= : Importa SOLO record con DATAULTVAR_MG70 >= since (YYYY-MM-DD). Default=oggi}
        {--limit= : Limita righe ERP (debug)}
        {--dry : Dry-run: NON scrive su DB}';

    protected $description = 'Sincronizza stock_qty (e no_backorder) da ERP (MAGPROQTAUNICA) su prodotti SIMPLE (magazzino unico)';

    public function handle(StockSyncService $service): int
    {
        $ditte = $this->option('ditte') ?: null;
        $sites = $this->option('sites') ?: null;
        $since = $this->option('since');
        $dry   = (bool) $this->option('dry');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $this->info('Sync stock from ERP...' . ($dry ? ' (DRY RUN)' : ''));
        $this->line('ditte=' . json_encode($ditte) . ' sites=' . json_encode($sites) . ' since=' . json_encode($since) . ' limit=' . ($limit ?? 'null'));

        try {
            $stats = $service->sync($ditte, $sites, $since, $dry, $limit);

            $this->info('--- RISULTATO ---');
            $this->line("Rows ERP lette:                       {$stats['rows']}");
            $this->line("Products aggiornati (righe update):     {$stats['updated']}");
            $this->line("Skippati: prodotto locale non trovato:  {$stats['skipped_missing_product']}");
            $this->line("Skippati per data (< since):            {$stats['skipped_by_date']}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP sync stock fallito: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
<?php

namespace App\Console\Commands;

use App\Services\Erp\ProductAttributeValueSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncProductAttributeValues extends Command
{
    protected $signature = 'erp:sync-product-attribute-values
        {--ditte=* : Filtra per ditte (es: --ditte=1)}
        {--sites=* : Filtra per siti (site_type) (es: --sites=1)}
        {--since= : Importa SOLO record con DATAULTIMOAGG_WEBT01 >= since (YYYY-MM-DD). Default=oggi}
        {--limit= : Limita righe ERP (debug)}
        {--keep-old : NON cancella i vecchi valori diversi (utile per multi-valore)}
        {--dry : Dry-run: NON scrive su DB}';

    protected $description = 'Sincronizza product_attribute_values (pivot prodotto-attributi) da ERP';

    public function handle(ProductAttributeValueSyncService $service): int
    {
        $ditte    = $this->option('ditte') ?: null;
        $sites    = $this->option('sites') ?: null;
        $since    = $this->option('since');
        $limit    = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dry      = (bool) $this->option('dry');
        $keepOld  = (bool) $this->option('keep-old');

        $this->info('Sync product attribute values from ERP...' . ($dry ? ' (DRY RUN)' : ''));
        $this->line('ditte=' . json_encode($ditte) . ' sites=' . json_encode($sites) . ' since=' . json_encode($since) . ' limit=' . ($limit ?? 'null') . ' keepOld=' . ($keepOld ? 'true' : 'false'));

        try {
            $stats = $service->sync($ditte, $sites, $since, $dry, $limit, $keepOld);

            $this->info('--- RISULTATO ---');
            $this->line("Rows ERP processate:                   {$stats['rows']}");
            $this->line("Pivot upsertate:                       {$stats['upserts']}");
            $this->line("Pivot ripulite (delete vecchi valori): {$stats['deleted_old']}");
            $this->line("Skippati: prodotto locale mancante:    {$stats['skipped_missing_product']}");
            $this->line("Skippati per data (< since):           {$stats['skipped_by_date']}");
            $this->line("Skippati: attribute/value non trovati: {$stats['skipped_missing_dictionary']}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP sync pivot fallito: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
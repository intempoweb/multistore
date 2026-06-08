<?php

namespace App\Console\Commands;

use App\Services\Erp\ProductMediaSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncMedia extends Command
{
    protected $signature = 'erp:sync-media
        {--ditte=* : Filtra per ditte (es: --ditte=1 --ditte=3)}
        {--sites=* : Filtra per siti (site_type) (es: --sites=1 --sites=2)}
        {--since= : Importa SOLO record con DATAULTIMOAGG >= since (YYYY-MM-DD). Default=oggi}
        {--limit= : Limita righe ERP (debug)}
        {--dry : Dry-run: NON scrive su DB}
        {--copy : Copia fisicamente i file su storage/app/public}
        {--force : Sovrascrive sempre i file (se --copy)}';

    protected $description = 'Sincronizza media da ERP: swatch globali in storage/Colori (dedup), immagini prodotto in storage/image_product (dedup)';

    public function handle(ProductMediaSyncService $service): int
    {
        $ditte = $this->option('ditte') ?: null;
        $sites = $this->option('sites') ?: null;
        $since = $this->option('since');
        $dry   = (bool) $this->option('dry');
        $copy  = (bool) $this->option('copy');
        $force = (bool) $this->option('force');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $this->info('Sync media from ERP...' . ($dry ? ' (DRY RUN)' : ''));
        $this->line('ditte=' . json_encode($ditte) . ' sites=' . json_encode($sites) . ' since=' . json_encode($since) . ' limit=' . ($limit ?? 'null'));
        $this->line('copy=' . ($copy ? 'true' : 'false') . ' force=' . ($force ? 'true' : 'false'));

        try {
            $stats = $service->sync(
                $ditte,
                $sites,
                $since,
                $dry,
                $copy,
                $force,
                $limit
            );

            $this->info('--- RISULTATO ---');
            $this->line("Rows simple (WEBT02) processate:  {$stats['simple_rows']}");
            $this->line("Rows padri  (WEBT00) processate:  {$stats['parent_rows']}");
            $this->line("Rows skippate per data (< since): {$stats['skipped_by_date']}");
            $this->line("MediaAsset upsertati:              {$stats['assets_upserted']}");
            $this->line("File copiati:                      {$stats['files_copied']}");
            $this->line("File skippati:                     {$stats['files_skipped']}");
            $this->line("Sorgenti mancanti su disco:        {$stats['missing_source']}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP sync media fallito: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
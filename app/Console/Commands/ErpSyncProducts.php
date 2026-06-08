<?php

namespace App\Console\Commands;

use App\Services\Erp\ProductSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncProducts extends Command
{
    protected $signature = 'erp:sync-products
        {--ditte=* : Filtra per ditte (es: --ditte=1 --ditte=3)}
        {--sites=* : Filtra per siti/site_type (es: --sites=1 --sites=2)}
        {--since= : Importa solo record ERP cambiati da una data in poi (formato YYYY-MM-DD). Default=oggi}
        {--limit= : Limita righe ERP (debug)}
        {--dry : Dry-run: NON scrive su DB}';

    protected $description = 'Sincronizza prodotti ERP nel read model locale products/configurable_products';

    public function handle(ProductSyncService $service): int
    {
        $ditte = $this->normalizeArrayOption($this->option('ditte'));
        $sites = $this->normalizeArrayOption($this->option('sites'));
        $since = $this->normalizeScalarOption($this->option('since'));
        $dry = (bool) $this->option('dry');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $this->info('Sync products from ERP...' . ($dry ? ' (DRY RUN)' : ''));
        $this->line(sprintf(
            'ditte=%s sites=%s since=%s limit=%s',
            json_encode($ditte),
            json_encode($sites),
            json_encode($since),
            $limit ?? 'null'
        ));

        try {
            $stats = $service->sync($ditte, $sites, $since, $dry, $limit);

            $this->newLine();
            $this->info('--- RISULTATO ---');
            $this->line('Simple prodotti creati/aggiornati:         ' . $this->stat($stats, 'simple_upserts'));
            $this->line('Configurable prodotti creati/aggiornati:   ' . $this->stat($stats, 'configurable_upserts'));
            $this->line('Configurable meta (configurable_products): ' . $this->stat($stats, 'configurable_meta_upserts'));
            $this->line('Product translations upserts:              ' . $this->stat($stats, 'product_translations_upserts'));
            $this->line('Rows semplici processate:                  ' . $this->stat($stats, 'simple_rows'));
            $this->line('Rows padri processate:                     ' . $this->stat($stats, 'parent_rows'));
            $this->line('Rows skippate per data (< since):          ' . $this->stat($stats, 'skipped_by_date'));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('ERP sync fallito: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function normalizeArrayOption(mixed $value): ?array
    {
        if (!is_array($value) || empty($value)) {
            return null;
        }

        $out = [];

        foreach ($value as $item) {
            $item = trim((string) $item);

            if ($item !== '') {
                $out[] = $item;
            }
        }

        $out = array_values(array_unique($out));

        return $out === [] ? null : $out;
    }

    private function normalizeScalarOption(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function stat(array $stats, string $key): string
    {
        return (string) ($stats[$key] ?? 0);
    }
}
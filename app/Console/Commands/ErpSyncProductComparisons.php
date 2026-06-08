<?php

namespace App\Console\Commands;

use App\Services\Erp\ProductComparisonSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncProductComparisons extends Command
{
    protected $signature = 'erp:sync-product-comparisons
        {--ditte=* : Filtra per ditte, es: --ditte=1 --ditte=3}
        {--sites=* : Filtra per siti/site_type, es: --sites=1 --sites=2}
        {--since= : Ignorato: LASTCHANGE ERP non affidabile per i comparativi}
        {--limit= : Limita prodotti locali da analizzare, debug}
        {--dry : Dry-run: NON scrive su DB}';

    protected $description = 'Sincronizza articoli comparativi ERP nel read model locale product_comparisons';

    public function handle(ProductComparisonSyncService $service): int
    {
        $ditte = $this->normalizeArrayOption($this->option('ditte'));
        $sites = $this->normalizeArrayOption($this->option('sites'));
        $since = $this->normalizeScalarOption($this->option('since'));
        $dry = (bool) $this->option('dry');
        $limit = $this->option('limit') !== null && $this->option('limit') !== ''
            ? (int) $this->option('limit')
            : null;

        $this->info('Sync product comparisons from ERP...' . ($dry ? ' (DRY RUN)' : ''));
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
            $this->line('Prodotti locali analizzati:       ' . $this->stat($stats, 'local_products'));
            $this->line('Rows ERP trovate:                 ' . $this->stat($stats, 'erp_rows'));
            $this->line('Comparativi creati/aggiornati:    ' . $this->stat($stats, 'comparison_upserts'));
            $this->line('Comparativi eliminati:            ' . $this->stat($stats, 'comparison_deletes'));
            $this->line('Rows senza prodotto locale:       ' . $this->stat($stats, 'missing_products'));
            $this->line('Rows skippate per data (< since): ' . $this->stat($stats, 'skipped_by_date'));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('ERP sync product comparisons fallito: ' . $e->getMessage());

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
        return number_format((int) ($stats[$key] ?? 0), 0, ',', '.');
    }
}
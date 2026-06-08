<?php

namespace App\Console\Commands;

use App\Services\Erp\GroupDescriptionSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncGroupDescriptions extends Command
{
    protected $signature = 'erp:sync-group-descriptions
        {--ditte=* : Filtra per ditte}
        {--sites=* : Filtra per site_type}
        {--limit= : Limita righe ERP}
        {--dry : Dry-run: NON scrive su DB}';

    protected $description = 'Sincronizza descrizioni catalogo ERP (famiglie, sottofamiglie, gruppi)';

    public function handle(GroupDescriptionSyncService $service): int
    {
        $ditte = $this->normalizeArrayOption($this->option('ditte'));
        $sites = $this->normalizeArrayOption($this->option('sites'));
        $dry = (bool) $this->option('dry');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $this->info('Sync catalog descriptions from ERP...' . ($dry ? ' (DRY RUN)' : ''));
        $this->line(sprintf(
            'ditte=%s sites=%s limit=%s',
            json_encode($ditte),
            json_encode($sites),
            $limit ?? 'null'
        ));

        try {
            $stats = $service->sync($ditte, $sites, $dry, $limit);

            $this->newLine();
            $this->info('--- RISULTATO ---');
            $this->line('Rows lette:          ' . ($stats['rows'] ?? 0));
            $this->line('Rows upsertate:      ' . ($stats['upserts'] ?? 0));
            $this->line('Locale skippate:     ' . ($stats['skipped_locale'] ?? 0));

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
}
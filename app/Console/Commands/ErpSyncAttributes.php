<?php

namespace App\Console\Commands;

use App\Services\Erp\AttributeSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncAttributes extends Command
{
    protected $signature = 'erp:sync-attributes
        {--ditte=* : (DEPRECATO) Non serve più: attributi globali. Lasciato solo per compatibilità}
        {--dry : Non scrive su DB, fa solo preview/count}';

    protected $description = 'Sincronizza attributi e valori da ERP (GLOBALI: non più per ditta)';

    public function handle(AttributeSyncService $service): int
    {
        $dry = (bool) $this->option('dry');

        // opzione mantenuta solo per non rompere script esistenti
        $ditte = $this->option('ditte') ?: null;
        if (!empty($ditte)) {
            $this->warn('Nota: --ditte è deprecato. Gli attributi sono globali, quindi non viene applicato alcun filtro.');
        }

        $this->info('Sync attributes from ERP...' . ($dry ? ' (DRY RUN)' : ''));

        try {
            $result = $service->sync($dry);

            $this->info('--- RISULTATO ---');
            $this->line("Attributi creati/aggiornati: {$result['attributes']}");
            $this->line("Valori creati/aggiornati:    {$result['values']}");
            $this->line("Traduzioni attributi:        {$result['attr_translations']}");
            $this->line("Traduzioni valori:           {$result['value_translations']}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP sync fallito: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
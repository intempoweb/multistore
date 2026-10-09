<?php

namespace App\Console\Commands;

use App\Services\Meta\ReadyCatalogFeedService;
use Illuminate\Console\Command;
use Throwable;

class GenerateReadyMetaFeed extends Command
{
    protected $signature = 'meta:generate-ready-feed';

    protected $description = 'Genera il feed XML del catalogo Meta per Ready B2C';

    public function handle(ReadyCatalogFeedService $feed): int
    {
        try {
            $result = $feed->generate();
            $this->info("Feed Ready generato: {$result['exported']} prodotti; {$result['skipped']} esclusi.");
            $this->line($result['path']);
            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Generazione feed fallita: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}

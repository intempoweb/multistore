<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageBlock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;

class SyncStorefrontBladePages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storefront:sync-blade-pages
        {--store= : ID store specifico da sincronizzare}
        {--force-blocks : Crea gli slot mancanti anche se la pagina ha già alcuni blocchi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronizza nel DB le pagine storefront definite da Blade, mantenendo il layout nel codice e i contenuti nel BO.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $stores = Store::query()
            ->where('is_active', true)
            ->when($this->option('store'), function ($query, $storeId) {
                $query->whereKey((int) $storeId);
            })
            ->orderBy('name')
            ->get();

        if ($stores->isEmpty()) {
            $this->warn('Nessuno store attivo trovato.');

            return self::SUCCESS;
        }

        foreach ($stores as $store) {
            $this->syncStore($store);
        }

        $this->info('Sincronizzazione pagine Blade completata.');

        return self::SUCCESS;
    }

    private function syncStore(Store $store): void
    {
        $area = $store->is_b2b ? 'b2b' : 'b2c';
        $theme = trim((string) ($store->theme ?? '')) ?: 'default';

        $this->line("Store #{$store->id} {$store->name} [{$area}/{$theme}]");

        foreach ($this->bladePageDefinitions($area, $theme) as $definition) {
            $page = StorefrontPage::query()->firstOrCreate(
                [
                    'store_id' => $store->id,
                    'slug' => $definition['slug'],
                ],
                [
                    'title' => $definition['title'],
                    'description' => $definition['description'] ?? null,
                    'template' => $definition['template'],
                    'layout' => $definition['layout'] ?? null,
                    'meta_title' => $definition['meta_title'] ?? $definition['title'],
                    'meta_description' => $definition['meta_description'] ?? null,
                    'is_active' => true,
                    'sort_order' => $definition['sort_order'] ?? 0,
                ]
            );

            $created = $page->wasRecentlyCreated ? 'creata' : 'già presente';
            $this->line("  - {$definition['slug']} ({$created})");

            if (($definition['slug'] ?? null) === 'login') {
                $this->syncLoginBlocks($page);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bladePageDefinitions(string $area, string $theme): array
    {
        $definitions = [];

        if (View::exists("storefront.themes.{$area}.{$theme}.auth-layout")) {
            $definitions[] = [
                'slug' => 'login',
                'title' => 'Login clienti',
                'description' => 'Pagina accesso clienti.',
                'template' => 'login',
                'layout' => 'auth-layout',
                'meta_title' => 'Login clienti',
                'meta_description' => 'Accedi alla tua area clienti.',
                'sort_order' => 10,
            ];
        }

        if (View::exists("storefront.themes.{$area}.{$theme}.overrides.home")) {
            $definitions[] = [
                'slug' => 'home',
                'title' => 'Homepage',
                'description' => 'Homepage storefront.',
                'template' => 'home',
                'layout' => 'layout',
                'meta_title' => 'Homepage',
                'meta_description' => null,
                'sort_order' => 0,
            ];
        }

        return $definitions;
    }

    private function syncLoginBlocks(StorefrontPage $page): void
    {
        if (!$this->option('force-blocks') && $page->blocks()->exists()) {
            return;
        }

        foreach (range(1, 8) as $index) {
            StorefrontPageBlock::query()->firstOrCreate(
                [
                    'storefront_page_id' => $page->id,
                    'name' => 'login_background_' . $index,
                ],
                [
                    'type' => 'brand_grid',
                    'sort_order' => $index,
                    'is_active' => true,
                    'title' => 'Brand ' . $index,
                    'image_path' => 'https://picsum.photos/seed/intempo-login-' . $index . '/900/700',
                    'button_new_tab' => false,
                    'settings' => [],
                ]
            );
        }
    }
}

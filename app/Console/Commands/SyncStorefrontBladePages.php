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

            if (($definition['slug'] ?? null) === 'home') {
                $this->syncHomeBlocks($page);
            }

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

    private function syncHomeBlocks(StorefrontPage $page): void
    {
        $blocks = [
            [
                'name' => 'home_hero',
                'type' => 'hero',
                'sort_order' => 10,
                'title' => 'Agende e taccuini per ogni giorno',
                'subtitle' => 'CIAK Firenze',
                'content' => 'Oggetti quotidiani per scrivere, pianificare e portare con te le idee.',
                'button_label' => 'Scopri la collezione',
                'button_url' => '/catalog',
            ],
            [
                'name' => 'home_about_intro',
                'type' => 'section_intro',
                'sort_order' => 20,
                'title' => 'La nostra storia, il nostro sguardo sul futuro.',
                'subtitle' => 'Chi siamo & Vision',
                'content' => 'Ciak celebra la bellezza della carta e la trasforma in esperienze di valore. Ogni prodotto nasce da attenzione, ricerca e passione artigianale.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_about',
                'type' => 'about',
                'sort_order' => 30,
                'title' => 'Chi siamo',
                'subtitle' => 'La nostra storia',
                'content' => 'Dal cuore di Firenze, CIAK crea agende e taccuini pensati per accompagnare idee, progetti e giornate piene di dettagli.',
                'button_label' => 'Scopri chi siamo',
                'button_url' => '/about',
            ],
            [
                'name' => 'home_about_highlight_1',
                'type' => 'about_highlight',
                'sort_order' => 31,
                'title' => 'Laboratorio',
                'subtitle' => 'scissors',
                'content' => 'Non una fabbrica: ogni pezzo nasce in un distretto artigiano con una storia di generazioni.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_about_highlight_2',
                'type' => 'about_highlight',
                'sort_order' => 32,
                'title' => 'Fatto a mano',
                'subtitle' => 'hand',
                'content' => 'Materiali, copertine ed elastici passano dalle mani di artigiani che conoscono il mestiere.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_about_highlight_3',
                'type' => 'about_highlight',
                'sort_order' => 33,
                'title' => 'Responsabilità',
                'subtitle' => 'leaf',
                'content' => 'Scelte attente, materiali selezionati e cura dell’impatto ambientale.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_vision',
                'type' => 'vision',
                'sort_order' => 40,
                'title' => 'La nostra vision',
                'subtitle' => 'Vision',
                'content' => 'Crediamo in oggetti quotidiani fatti bene: essenziali, durevoli, belli da usare ogni giorno e capaci di lasciare spazio a ciò che conta.',
                'button_label' => 'Scopri la nostra vision',
                'button_url' => '/vision',
            ],
            [
                'name' => 'home_vision_highlight_1',
                'type' => 'vision_highlight',
                'sort_order' => 41,
                'title' => 'Essenzialità',
                'subtitle' => 'circle',
                'content' => 'Progetti chiari, funzionali e capaci di durare nel tempo.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_vision_highlight_2',
                'type' => 'vision_highlight',
                'sort_order' => 42,
                'title' => 'Durabilità',
                'subtitle' => 'shield-check',
                'content' => 'Oggetti pensati per accompagnare l’uso quotidiano.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_values_intro',
                'type' => 'section_intro',
                'sort_order' => 50,
                'title' => 'I nostri valori',
                'subtitle' => null,
                'content' => null,
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_value_1',
                'type' => 'value',
                'sort_order' => 51,
                'title' => 'Laboratorio',
                'subtitle' => 'scissors',
                'content' => 'La cura del fare e l’esperienza artigiana guidano ogni scelta.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_value_2',
                'type' => 'value',
                'sort_order' => 52,
                'title' => 'Artigianalità',
                'subtitle' => 'hand',
                'content' => 'Una cultura del prodotto costruita attraverso dettagli e competenze.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_value_3',
                'type' => 'value',
                'sort_order' => 53,
                'title' => 'Responsabilità',
                'subtitle' => 'leaf',
                'content' => 'Materiali e processi scelti con attenzione e consapevolezza.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_value_4',
                'type' => 'value',
                'sort_order' => 54,
                'title' => 'Made in Italy',
                'subtitle' => 'map-pin',
                'content' => 'Una filiera italiana fatta di esperienza, territorio e qualità.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_featured_intro',
                'type' => 'section_intro',
                'sort_order' => 60,
                'title' => 'Scelti per te',
                'subtitle' => 'In evidenza',
                'content' => null,
                'button_label' => 'Vedi tutti',
                'button_url' => '/catalog',
            ],
            [
                'name' => 'home_formats_intro',
                'type' => 'section_intro',
                'sort_order' => 70,
                'title' => 'Scegli come scrivere',
                'subtitle' => 'Trova quello giusto',
                'content' => 'Scegli il formato che segue il tuo modo di organizzare idee, impegni e progetti.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_format_daily',
                'type' => 'format',
                'sort_order' => 71,
                'title' => 'Agenda giornaliera',
                'subtitle' => 'Agende',
                'content' => 'Una pagina per ogni giorno: tanto spazio per programmare, annotare e avere tutto sotto controllo.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
            ],
            [
                'name' => 'home_format_weekly',
                'type' => 'format',
                'sort_order' => 72,
                'title' => 'Agenda settimanale',
                'subtitle' => 'Agende',
                'content' => 'La settimana a colpo d’occhio, per organizzare appuntamenti e priorità.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
            ],
            [
                'name' => 'home_format_dotted',
                'type' => 'format',
                'sort_order' => 73,
                'title' => 'Taccuino a punti',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'La griglia discreta ideale per bullet journal, schemi, appunti e creatività.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
            ],
            [
                'name' => 'home_format_lined',
                'type' => 'format',
                'sort_order' => 74,
                'title' => 'Taccuino a righe',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'Il formato classico per scrivere con ordine pensieri, note e progetti.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
            ],
            [
                'name' => 'home_format_blank',
                'type' => 'format',
                'sort_order' => 75,
                'title' => 'Taccuino a pagine bianche',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'Spazio libero per disegnare, progettare e lasciare correre le idee.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
            ],
            [
                'name' => 'home_story',
                'type' => 'editorial',
                'sort_order' => 80,
                'title' => 'Carta, colore e dettagli essenziali',
                'subtitle' => 'Dettagli CIAK',
                'content' => 'Usa questo slot per un’immagine ambientata caricata dal back office.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'name' => 'home_banner',
                'type' => 'editorial_banner',
                'sort_order' => 90,
                'title' => 'Pensati per accompagnare lavoro, studio e viaggio',
                'subtitle' => 'Collezioni',
                'content' => 'Uno spazio flessibile per campagne, stagionalità o nuovi lanci.',
                'button_label' => 'Vai allo shop',
                'button_url' => '/catalog',
            ],
            [
                'name' => 'home_instagram',
                'type' => 'instagram_gallery',
                'sort_order' => 100,
                'title' => 'CIAK su Instagram',
                'subtitle' => 'Social',
                'content' => 'Ispirazioni, colori e dettagli dalle ultime storie del nostro mondo.',
                'button_label' => 'Apri Instagram',
                'button_url' => 'https://www.instagram.com/ciak_firenze/',
            ],
        ];

        foreach ($blocks as $block) {
            StorefrontPageBlock::query()->firstOrCreate(
                [
                    'storefront_page_id' => $page->id,
                    'name' => $block['name'],
                ],
                [
                    'type' => $block['type'],
                    'sort_order' => $block['sort_order'],
                    'is_active' => true,
                    'title' => $block['title'],
                    'subtitle' => $block['subtitle'],
                    'content' => $block['content'],
                    'button_label' => $block['button_label'],
                    'button_url' => $block['button_url'],
                    'button_new_tab' => false,
                    'settings' => [],
                ]
            );
        }
    }
}

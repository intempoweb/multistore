<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageBlock;
use App\Models\StorefrontPageBlockTranslation;
use App\Models\StorefrontPageTranslation;
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
        $area = $store->channel();
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

            if ($store->isB2C()) {
                $this->ensurePageItalianTranslation($page, $store, $definition);
                $this->ensureProvidedPageTranslations($page, $store, $definition);
            }

            if (($definition['slug'] ?? null) === 'home') {
                $this->syncHomeBlocks($page, $store);
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

        if ($area === 'b2c' && View::exists('storefront.base.pages.brand-page')) {
            $definitions[] = [
                'slug' => 'about',
                'title' => 'Chi siamo',
                'description' => "Siamo un laboratorio, non una fabbrica. Il marchio Ciak è nato a Firenze, nel distretto dove la pelletteria toscana ha una storia di generazioni.\n\nI nostri artigiani scelgono ogni materiale, tagliano le copertine, montano ogni elastico a mano. Conoscono bene il loro mestiere e lo dimostrano in ogni pezzo che realizzano.\n\nControlliamo la filiera dall'inizio alla fine, compreso l'impatto ambientale di ogni fase produttiva. Utilizziamo materie prime selezionate, carta certificata e collanti a base naturale e senza derivati animali, per un risultato essenziale e durevole.\n\nIl Made in Italy, per noi, non è un'etichetta da esibire. È il motivo stesso per cui esistiamo.",
                'template' => 'brand-page',
                'layout' => 'layout',
                'meta_title' => 'Chi siamo',
                'meta_description' => 'Siamo un laboratorio, non una fabbrica. Il marchio Ciak è nato a Firenze.',
                'sort_order' => 20,
                'translations' => [
                    'en' => [
                        'slug' => 'about-us',
                        'title' => 'About us',
                        'description' => "We are a workshop, not a factory. The Ciak brand was born in Florence, in the district where Tuscan leather craftsmanship has generations of history behind it.\n\nOur artisans choose every material, cut every cover, assemble every elastic band by hand. They know their craft well, and it shows in every piece they make.\n\nWe control the supply chain from start to finish, including the environmental impact of every production stage. We use selected raw materials, certified paper, and natural, animal-free adhesives, for a result that is essential and built to last.\n\nMade in Italy, to us, isn't a label to display. It's the reason we exist.",
                        'meta_title' => 'About us',
                        'meta_description' => 'We are a workshop, not a factory. The Ciak brand was born in Florence.',
                    ],
                ],
            ];

            $definitions[] = [
                'slug' => 'vision',
                'title' => 'Vision',
                'description' => "Crediamo che scrivere a mano sia un atto di resistenza.\n\nUn elastico che scatta. Una copertina che si apre. Una pagina che aspetta di essere scritta. In un mondo che digita, scorre e cancella, Ciak sceglie il segno che resta nella memoria.\n\nOgni taccuino ed ogni agenda nasce a Firenze, dalle mani di chi lavora la pelle e la carta da una vita intera, non da un algoritmo. Non produciamo oggetti da consumare: costruiamo strumenti che accompagnano un pensiero.\n\nNon inseguiamo le tendenze. Ne creiamo una che possa durare nel tempo.",
                'template' => 'brand-page',
                'layout' => 'layout',
                'meta_title' => 'Vision',
                'meta_description' => 'Crediamo che scrivere a mano sia un atto di resistenza.',
                'sort_order' => 30,
                'translations' => [
                    'en' => [
                        'slug' => 'vision',
                        'title' => 'Vision',
                        'description' => "We believe handwriting is an act of resistance.\n\nAn elastic band that snaps shut. A cover that opens. A page waiting to be written. In a world that types, scrolls, and deletes, Ciak chooses the mark that stays in memory.\n\nEvery notebook is born in Florence, made by hands that have worked leather and paper for a lifetime, not by an algorithm. We don't produce objects to be consumed: we build tools that accompany a thought.\n\nWe don't chase trends. We create one that can last.",
                        'meta_title' => 'Vision',
                        'meta_description' => 'We believe handwriting is an act of resistance.',
                    ],
                ],
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
            $created = StorefrontPageBlock::query()->firstOrCreate(
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

            $this->ensureBlockItalianTranslation($created);
        }
    }

    private function syncHomeBlocks(StorefrontPage $page, Store $store): void
    {
        if (strtolower(trim((string) $store->theme)) === 'intemposhop') {
            $this->syncIntempoHomeBlocks($page);

            return;
        }

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
                'content' => 'Una pagina per ogni giorno: tanto spazio per pensare e per fermarsi a scrivere ciò che conta.',
                'legacy_content' => 'Una pagina per ogni giorno: tanto spazio per programmare, annotare e avere tutto sotto controllo.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
                'settings' => [
                    'specs' => [
                        'it' => ['Cinque lingue: EN-FR-DE-ES-IT', 'Orario', 'Ampio spazio per scrivere'],
                        'en' => ['Five languages: EN-FR-DE-ES-IT', 'Time schedule', 'Ample writing space'],
                    ],
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Daily planner',
                        'subtitle' => 'Diaries',
                        'content' => 'A page for every day: plenty of room to think, and to pause and write down what matters.',
                        'button_label' => 'Discover the selection',
                    ],
                ],
            ],
            [
                'name' => 'home_format_weekly',
                'type' => 'format',
                'sort_order' => 72,
                'title' => 'Agenda settimanale',
                'subtitle' => 'Agende',
                'content' => 'Vista di sette giorni per chi organizza la settimana prima ancora che inizi.',
                'legacy_content' => 'La settimana a colpo d’occhio, per organizzare appuntamenti e priorità.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
                'settings' => [
                    'specs' => [
                        'it' => ['Cinque lingue: EN-FR-DE-ES-IT', 'Settimana in due pagine', 'Calendario'],
                        'en' => ['Five languages: EN-FR-DE-ES-IT', 'Week on two pages', 'Calendar'],
                    ],
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Weekly planner',
                        'subtitle' => 'Diaries',
                        'content' => 'A seven-day view for planning the week before it even begins.',
                        'button_label' => 'Discover the selection',
                    ],
                ],
            ],
            [
                'name' => 'home_format_dotted',
                'type' => 'format',
                'sort_order' => 73,
                'title' => 'Pagine a puntini',
                'legacy_title' => 'Taccuino a punti',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'Una griglia leggera che guida la scrittura: perfetta per liste, schizzi e bullet journal.',
                'legacy_content' => 'La griglia discreta ideale per bullet journal, schemi, appunti e creatività.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
                'settings' => [
                    'specs' => [
                        'it' => ['Struttura flessibile', 'Libertà di scrivere', 'Per chi ama organizzarsi'],
                        'en' => ['Flexible structure', 'Freedom to write and draw', 'For the organized mind'],
                    ],
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Dotted pages',
                        'subtitle' => 'Notebooks',
                        'content' => 'A light grid that guides your writing: perfect for lists, sketches, and bullet journaling.',
                        'button_label' => 'Discover the selection',
                    ],
                ],
            ],
            [
                'name' => 'home_format_lined',
                'type' => 'format',
                'sort_order' => 74,
                'title' => 'Pagine a righe',
                'legacy_title' => 'Taccuino a righe',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'La pagina classica su cui scrivere: ordinata, familiare e simmetrica.',
                'legacy_content' => 'Il formato classico per scrivere con ordine pensieri, note e progetti.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
                'settings' => [
                    'specs' => [
                        'it' => ['Scrittura ordinata', 'Nessuna distrazione', 'Adatta a testi lunghi'],
                        'en' => ['Neat handwriting', 'No distractions', 'Great for longer notes'],
                    ],
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Lined pages',
                        'subtitle' => 'Notebooks',
                        'content' => 'The classic page for writing: neat, familiar, and evenly spaced.',
                        'button_label' => 'Discover the selection',
                    ],
                ],
            ],
            [
                'name' => 'home_format_blank',
                'type' => 'format',
                'sort_order' => 75,
                'title' => 'Pagine bianche',
                'legacy_title' => 'Taccuino a pagine bianche',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'Nessuna riga, nessun limite: solo spazio bianco per idee, disegni e pensieri che non seguono uno schema predefinito.',
                'legacy_content' => 'Spazio libero per disegnare, progettare e lasciare correre le idee.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
                'settings' => [
                    'specs' => [
                        'it' => ['Nessun limite', 'Spazio versatile', 'Massima libertà'],
                        'en' => ['No limits', 'Versatile space', 'Total freedom'],
                    ],
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Blank pages',
                        'subtitle' => 'Notebooks',
                        'content' => 'No lines, no limits: just blank space for ideas, sketches, and thoughts that don\'t follow a set pattern.',
                        'button_label' => 'Discover the selection',
                    ],
                ],
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
            $created = StorefrontPageBlock::query()->firstOrCreate(
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
                    'settings' => $block['settings'] ?? [],
                ]
            );

            if (empty($created->settings) && ! empty($block['settings'])) {
                $created->forceFill(['settings' => $block['settings']])->save();
            }

            $this->updateBlockIfLegacyDefault($created, $block);

            $this->ensureBlockItalianTranslation($created, $block);
            $this->ensureProvidedBlockTranslations($created, $block);
        }
    }

    private function syncIntempoHomeBlocks(StorefrontPage $page): void
    {
        $blocks = [
            [
                'name' => 'home_hero',
                'type' => 'hero',
                'sort_order' => 10,
                'title' => 'Agende, accessori e soluzioni per ogni giorno',
                'legacy_title' => 'Agende e taccuini per ogni giorno',
                'subtitle' => 'Intempo',
                'legacy_subtitle' => 'CIAK Firenze',
                'content' => 'Prodotti per scrivere, organizzare il tempo e rendere più funzionali casa, studio e lavoro.',
                'legacy_content' => 'Oggetti quotidiani per scrivere, pianificare e portare con te le idee.',
                'button_label' => 'Scopri la collezione',
                'button_url' => '/catalog',
                'translations' => [
                    'en' => [
                        'title' => 'Diaries, accessories and everyday solutions',
                        'subtitle' => 'Intempo',
                        'content' => 'Products for writing, organizing time and making home, study and work spaces more functional.',
                        'button_label' => 'Discover the collection',
                    ],
                ],
            ],
            [
                'name' => 'home_about',
                'type' => 'about',
                'sort_order' => 30,
                'title' => 'Chi siamo',
                'subtitle' => 'La nostra storia',
                'content' => 'Intempo crea, produce e distribuisce prodotti pensati per organizzare il tempo, accompagnare il lavoro e portare funzionalità negli spazi quotidiani.',
                'legacy_content' => 'Dal cuore di Firenze, CIAK crea agende e taccuini pensati per accompagnare idee, progetti e giornate piene di dettagli.',
                'button_label' => 'Esplora il mondo Intempo',
                'legacy_button_label' => 'Scopri chi siamo',
                'button_url' => '/catalog',
                'translations' => [
                    'en' => [
                        'title' => 'About us',
                        'subtitle' => 'Our story',
                        'content' => 'Intempo creates and distributes products designed to organize time, support work, and add functionality to everyday spaces.',
                        'button_label' => 'Explore the Intempo world',
                    ],
                ],
            ],
            [
                'name' => 'home_featured_intro',
                'type' => 'section_intro',
                'sort_order' => 60,
                'title' => 'Scelti per te',
                'subtitle' => 'In evidenza',
                'content' => null,
                'button_label' => 'Vedi tutto',
                'legacy_button_label' => 'Vedi tutti',
                'button_url' => '/catalog',
                'translations' => [
                    'en' => [
                        'title' => 'Picked for you',
                        'subtitle' => 'Featured',
                        'button_label' => 'View all',
                    ],
                ],
            ],
        ];

        foreach ($blocks as $block) {
            $created = StorefrontPageBlock::query()->firstOrCreate(
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
                    'settings' => $block['settings'] ?? [],
                ]
            );

            if (empty($created->settings) && ! empty($block['settings'])) {
                $created->forceFill(['settings' => $block['settings']])->save();
            }

            $this->updateBlockIfLegacyDefault($created, $block);

            $this->ensureBlockItalianTranslation($created, $block);
            $this->ensureProvidedBlockTranslations($created, $block);
        }
    }

    private function ensurePageItalianTranslation(StorefrontPage $page, Store $store, array $definition): void
    {
        StorefrontPageTranslation::query()->firstOrCreate(
            [
                'storefront_page_id' => $page->id,
                'locale' => 'it',
            ],
            [
                'store_id' => $store->id,
                'slug' => $definition['slug'],
                'title' => $definition['title'],
                'description' => $definition['description'] ?? null,
                'meta_title' => $definition['meta_title'] ?? $definition['title'],
                'meta_description' => $definition['meta_description'] ?? null,
            ]
        );
    }

    private function ensureProvidedPageTranslations(StorefrontPage $page, Store $store, array $definition): void
    {
        foreach (($definition['translations'] ?? []) as $locale => $translation) {
            StorefrontPageTranslation::query()->firstOrCreate(
                [
                    'storefront_page_id' => $page->id,
                    'locale' => $locale,
                ],
                [
                    'store_id' => $store->id,
                    'slug' => $translation['slug'] ?? null,
                    'title' => $translation['title'] ?? null,
                    'description' => $translation['description'] ?? null,
                    'meta_title' => $translation['meta_title'] ?? null,
                    'meta_description' => $translation['meta_description'] ?? null,
                ]
            );
        }
    }

    private function ensureBlockItalianTranslation(StorefrontPageBlock $block, ?array $definition = null): void
    {
        $translation = StorefrontPageBlockTranslation::query()->firstOrCreate(
            [
                'storefront_page_block_id' => $block->id,
                'locale' => 'it',
            ],
            [
                'title' => $definition['title'] ?? $block->title,
                'subtitle' => $definition['subtitle'] ?? $block->subtitle,
                'content' => $definition['content'] ?? $block->content,
                'button_label' => $definition['button_label'] ?? $block->button_label,
            ]
        );

        if ($definition) {
            $updates = [];

            foreach (['title', 'subtitle', 'content', 'button_label'] as $field) {
                $legacy = $definition['legacy_' . $field] ?? null;

                if ($legacy !== null && (string) $translation->{$field} === (string) $legacy) {
                    $updates[$field] = $definition[$field] ?? null;
                }
            }

            if ($updates) {
                $translation->forceFill($updates)->save();
            }
        }
    }

    private function updateBlockIfLegacyDefault(StorefrontPageBlock $block, array $definition): void
    {
        $updates = [];

        foreach (['title', 'subtitle', 'content', 'button_label'] as $field) {
            $legacy = $definition['legacy_' . $field] ?? null;

            if ($legacy !== null && (string) $block->{$field} === (string) $legacy) {
                $updates[$field] = $definition[$field] ?? null;
            }
        }

        if ($updates) {
            $block->forceFill($updates)->save();
        }
    }

    private function ensureProvidedBlockTranslations(StorefrontPageBlock $block, array $definition): void
    {
        foreach (($definition['translations'] ?? []) as $locale => $translationData) {
            $translation = StorefrontPageBlockTranslation::query()->firstOrCreate(
                [
                    'storefront_page_block_id' => $block->id,
                    'locale' => $locale,
                ],
                [
                    'title' => $translationData['title'] ?? null,
                    'subtitle' => $translationData['subtitle'] ?? null,
                    'content' => $translationData['content'] ?? null,
                    'button_label' => $translationData['button_label'] ?? null,
                ]
            );

            if ($translation->wasRecentlyCreated) {
                continue;
            }

            $updates = [];

            foreach (['title', 'subtitle', 'content', 'button_label'] as $field) {
                if (! array_key_exists($field, $translationData)) {
                    continue;
                }

                $current = (string) $translation->{$field};
                $legacy = (string) ($definition['legacy_' . $field] ?? '');
                $base = (string) ($definition[$field] ?? '');

                if ($current !== '' && ($current === $legacy || $current === $base)) {
                    $updates[$field] = $translationData[$field];
                }
            }

            if ($updates) {
                $translation->forceFill($updates)->save();
            }
        }
    }
}

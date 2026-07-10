<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageBlock;
use App\Models\StorefrontPageBlockMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontPageController extends Controller
{
    public function index(Request $request): View
    {
        $store = $this->currentAdminStore();

        $pages = StorefrontPage::query()
            ->withCount('blocks')
            ->where('store_id', $store->id)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(20);

        return view('admin.storefront-pages.index', [
            'store' => $store,
            'pages' => $pages,
        ]);
    }

    public function create(): View
    {
        return view('admin.storefront-pages.create', [
            'page' => new StorefrontPage(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $store = $this->currentAdminStore();

        $validated = $this->validatePage($request);

        $validated['store_id'] = $store->id;
        $validated['template'] = $this->templateForSlug((string) $validated['slug']);
        $validated['layout'] = null;

        StorefrontPage::create($validated);

        return redirect()
            ->route('admin.storefront-pages.index')
            ->with('status', 'Pagina storefront creata correttamente.');
    }

    public function edit(StorefrontPage $storefrontPage): View
    {
        $this->ensureSameStore($storefrontPage);
        $this->ensureDefaultBlocks($storefrontPage);

        return view('admin.storefront-pages.edit', [
            'page' => $storefrontPage->load('blocks.media'),
        ]);
    }

    public function update(Request $request, StorefrontPage $storefrontPage): RedirectResponse
    {
        $this->ensureSameStore($storefrontPage);

        $validated = $this->validatePage($request);

        $validated['template'] = $storefrontPage->template ?: $this->templateForSlug((string) $validated['slug']);
        $validated['layout'] = $storefrontPage->layout;

        $storefrontPage->update($validated);

        return redirect()
            ->route('admin.storefront-pages.edit', $storefrontPage)
            ->with('status', 'Pagina storefront aggiornata correttamente.');
    }

    public function updateBlocks(Request $request, StorefrontPage $storefrontPage): RedirectResponse
    {
        $this->ensureSameStore($storefrontPage);

        $validated = $request->validate([
            'blocks' => ['nullable', 'array'],
            'blocks.*.id' => ['nullable', 'integer', 'exists:storefront_page_blocks,id'],
            'blocks.*.type' => ['required', 'string', 'max:60'],
            'blocks.*.name' => ['nullable', 'string', 'max:190'],
            'blocks.*.title' => ['nullable', 'string', 'max:190'],
            'blocks.*.subtitle' => ['nullable', 'string', 'max:255'],
            'blocks.*.content' => ['nullable', 'string'],
            'blocks.*.image_path' => ['nullable', 'string', 'max:255'],
            'blocks.*.mobile_image_path' => ['nullable', 'string', 'max:255'],
            'blocks.*.video_path' => ['nullable', 'string', 'max:255'],
            'blocks.*.button_label' => ['nullable', 'string', 'max:120'],
            'blocks.*.button_url' => ['nullable', 'string', 'max:255'],
            'blocks.*.button_new_tab' => ['nullable', 'boolean'],
            'blocks.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'blocks.*.is_active' => ['nullable', 'boolean'],
            'blocks.*.image_file' => ['nullable', 'image', 'max:4096'],
            'blocks.*.mobile_image_file' => ['nullable', 'image', 'max:4096'],
            'blocks.*.video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:51200'],
            'blocks.*.media' => ['nullable', 'array'],
            'blocks.*.media.*.id' => ['nullable', 'integer', 'exists:storefront_page_block_media,id'],
            'blocks.*.media.*.media_type' => ['required_with:blocks.*.media', 'in:image,video'],
            'blocks.*.media.*.alt_text' => ['nullable', 'string', 'max:255'],
            'blocks.*.media.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'blocks.*.media.*.is_active' => ['nullable', 'boolean'],
            'blocks.*.media.*.delete' => ['nullable', 'boolean'],
            'blocks.*.media.*.desktop_path' => ['nullable', 'string', 'max:255'],
            'blocks.*.media.*.mobile_path' => ['nullable', 'string', 'max:255'],
            'blocks.*.media.*.poster_path' => ['nullable', 'string', 'max:255'],
            'blocks.*.media.*.desktop_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,avif,mp4,webm,mov', 'max:51200'],
            'blocks.*.media.*.mobile_file' => ['nullable', 'image', 'max:8192'],
            'blocks.*.media.*.poster_file' => ['nullable', 'image', 'max:8192'],
        ]);

        foreach (($validated['blocks'] ?? []) as $index => $blockData) {
            $block = null;

            if (!empty($blockData['id'])) {
                $block = StorefrontPageBlock::query()
                    ->where('storefront_page_id', $storefrontPage->id)
                    ->where('id', $blockData['id'])
                    ->first();
            }

            if (!$block) {
                $block = new StorefrontPageBlock([
                    'storefront_page_id' => $storefrontPage->id,
                ]);
            }

            $imagePath = $blockData['image_path'] ?? $block->image_path;
            $mobileImagePath = $blockData['mobile_image_path'] ?? $block->mobile_image_path;
            $videoPath = $blockData['video_path'] ?? $block->video_path;

            if ($request->hasFile("blocks.{$index}.image_file")) {
                $imagePath = $request->file("blocks.{$index}.image_file")
                    ->store("storefront/pages/{$storefrontPage->id}", env('MEDIA_SYNC_DISK', config('filesystems.default', 'public')));
            }

            if ($request->hasFile("blocks.{$index}.mobile_image_file")) {
                $mobileImagePath = $request->file("blocks.{$index}.mobile_image_file")
                    ->store("storefront/pages/{$storefrontPage->id}", env('MEDIA_SYNC_DISK', config('filesystems.default', 'public')));
            }

            if ($request->hasFile("blocks.{$index}.video_file")) {
                $videoPath = $request->file("blocks.{$index}.video_file")
                    ->store("storefront/pages/{$storefrontPage->id}", env('MEDIA_SYNC_DISK', config('filesystems.default', 'public')));
            }

            $block->fill([
                'type' => $blockData['type'],
                'name' => $blockData['name'] ?? null,
                'title' => $blockData['title'] ?? null,
                'subtitle' => $blockData['subtitle'] ?? null,
                'content' => $blockData['content'] ?? null,
                'image_path' => $imagePath,
                'mobile_image_path' => $mobileImagePath,
                'video_path' => $videoPath,
                'button_label' => $blockData['button_label'] ?? null,
                'button_url' => $blockData['button_url'] ?? null,
                'button_new_tab' => (bool) ($blockData['button_new_tab'] ?? false),
                'sort_order' => (int) ($blockData['sort_order'] ?? ($index + 1)),
                'is_active' => (bool) ($blockData['is_active'] ?? false),
            ]);

            $block->storefront_page_id = $storefrontPage->id;
            $block->save();

            $this->syncBlockMedia($request, $block, $blockData, $index);
        }

        return redirect()
            ->route('admin.storefront-pages.edit', $storefrontPage)
            ->with('status', 'Slot contenuto aggiornati correttamente.');
    }

    private function syncBlockMedia(Request $request, StorefrontPageBlock $block, array $blockData, int $blockIndex): void
    {
        foreach (($blockData['media'] ?? []) as $mediaIndex => $mediaData) {
            $media = null;

            if (!empty($mediaData['id'])) {
                $media = StorefrontPageBlockMedia::query()
                    ->where('storefront_page_block_id', $block->id)
                    ->whereKey((int) $mediaData['id'])
                    ->first();
            }

            if (($mediaData['delete'] ?? false) && $media) {
                $media->delete();
                continue;
            }

            $desktopFile = $request->file("blocks.{$blockIndex}.media.{$mediaIndex}.desktop_file");
            $mobileFile = $request->file("blocks.{$blockIndex}.media.{$mediaIndex}.mobile_file");
            $posterFile = $request->file("blocks.{$blockIndex}.media.{$mediaIndex}.poster_file");

            if (!$media && !$desktopFile && empty($mediaData['desktop_path'])) {
                continue;
            }

            $media ??= new StorefrontPageBlockMedia(['storefront_page_block_id' => $block->id]);
            $disk = env('MEDIA_SYNC_DISK', config('filesystems.default', 'public'));
            $directory = "storefront/pages/{$block->storefront_page_id}/blocks/{$block->id}";

            $desktopPath = $desktopFile
                ? $desktopFile->store($directory, $disk)
                : ($mediaData['desktop_path'] ?? $media->desktop_path);
            $mobilePath = $mobileFile
                ? $mobileFile->store($directory, $disk)
                : ($mediaData['mobile_path'] ?? $media->mobile_path);
            $posterPath = $posterFile
                ? $posterFile->store($directory, $disk)
                : ($mediaData['poster_path'] ?? $media->poster_path);

            $media->fill([
                'media_type' => $mediaData['media_type'] ?? 'image',
                'desktop_path' => $desktopPath,
                'mobile_path' => $mobilePath,
                'poster_path' => $posterPath,
                'alt_text' => $mediaData['alt_text'] ?? null,
                'sort_order' => (int) ($mediaData['sort_order'] ?? $mediaIndex),
                'is_active' => (bool) ($mediaData['is_active'] ?? false),
            ]);
            $media->storefront_page_block_id = $block->id;
            $media->save();
        }
    }

    public function destroy(StorefrontPage $storefrontPage): RedirectResponse
    {
        $this->ensureSameStore($storefrontPage);

        $storefrontPage->delete();

        return redirect()
            ->route('admin.storefront-pages.index')
            ->with('status', 'Pagina storefront eliminata correttamente.');
    }

    private function ensureDefaultBlocks(StorefrontPage $storefrontPage): void
    {
        if ($storefrontPage->slug === 'home') {
            $this->createHomeBlocks($storefrontPage);

            return;
        }

        if ($storefrontPage->blocks()->exists()) {
            return;
        }

        if ($storefrontPage->slug !== 'login') {
            return;
        }

        foreach (range(1, 8) as $index) {
            StorefrontPageBlock::query()->create([
                'storefront_page_id' => $storefrontPage->id,
                'type' => 'brand_grid',
                'name' => 'login_background_' . $index,
                'sort_order' => $index,
                'is_active' => true,
                'title' => 'Brand ' . $index,
                'image_path' => 'https://picsum.photos/seed/intempo-login-' . $index . '/900/700',
                'button_new_tab' => false,
            ]);
        }
    }

    private function createHomeBlocks(StorefrontPage $storefrontPage): void
    {
        $blocks = [
            [
                'type' => 'hero',
                'name' => 'home_hero',
                'sort_order' => 10,
                'title' => 'Agende e taccuini per ogni giorno',
                'subtitle' => 'CIAK Firenze',
                'content' => 'Oggetti quotidiani per scrivere, pianificare e portare con te le idee.',
                'button_label' => 'Scopri la collezione',
                'button_url' => '/catalog',
            ],
            [
                'type' => 'section_intro',
                'name' => 'home_about_intro',
                'sort_order' => 20,
                'title' => 'La nostra storia, il nostro sguardo sul futuro.',
                'subtitle' => 'Chi siamo & Vision',
                'content' => 'Ciak celebra la bellezza della carta e la trasforma in esperienze di valore. Ogni prodotto nasce da attenzione, ricerca e passione artigianale.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'about',
                'name' => 'home_about',
                'sort_order' => 30,
                'title' => 'Chi siamo',
                'subtitle' => 'La nostra storia',
                'content' => 'Dal cuore di Firenze, CIAK crea agende e taccuini pensati per accompagnare idee, progetti e giornate piene di dettagli.',
                'button_label' => 'Scopri chi siamo',
                'button_url' => '/about',
            ],
            [
                'type' => 'about_highlight',
                'name' => 'home_about_highlight_1',
                'sort_order' => 31,
                'title' => 'Laboratorio',
                'subtitle' => 'scissors',
                'content' => 'Non una fabbrica: ogni pezzo nasce in un distretto artigiano con una storia di generazioni.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'about_highlight',
                'name' => 'home_about_highlight_2',
                'sort_order' => 32,
                'title' => 'Fatto a mano',
                'subtitle' => 'hand',
                'content' => 'Materiali, copertine ed elastici passano dalle mani di artigiani che conoscono il mestiere.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'about_highlight',
                'name' => 'home_about_highlight_3',
                'sort_order' => 33,
                'title' => 'Responsabilità',
                'subtitle' => 'leaf',
                'content' => 'Scelte attente, materiali selezionati e cura dell’impatto ambientale.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'vision',
                'name' => 'home_vision',
                'sort_order' => 40,
                'title' => 'La nostra vision',
                'subtitle' => 'Vision',
                'content' => 'Crediamo in oggetti quotidiani fatti bene: essenziali, durevoli, belli da usare ogni giorno e capaci di lasciare spazio a ciò che conta.',
                'button_label' => 'Scopri la nostra vision',
                'button_url' => '/vision',
            ],
            [
                'type' => 'vision_highlight',
                'name' => 'home_vision_highlight_1',
                'sort_order' => 41,
                'title' => 'Essenzialità',
                'subtitle' => 'circle',
                'content' => 'Progetti chiari, funzionali e capaci di durare nel tempo.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'vision_highlight',
                'name' => 'home_vision_highlight_2',
                'sort_order' => 42,
                'title' => 'Durabilità',
                'subtitle' => 'shield-check',
                'content' => 'Oggetti pensati per accompagnare l’uso quotidiano.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'section_intro',
                'name' => 'home_values_intro',
                'sort_order' => 50,
                'title' => 'I nostri valori',
                'subtitle' => null,
                'content' => null,
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'value',
                'name' => 'home_value_1',
                'sort_order' => 51,
                'title' => 'Laboratorio',
                'subtitle' => 'scissors',
                'content' => 'La cura del fare e l’esperienza artigiana guidano ogni scelta.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'value',
                'name' => 'home_value_2',
                'sort_order' => 52,
                'title' => 'Artigianalità',
                'subtitle' => 'hand',
                'content' => 'Una cultura del prodotto costruita attraverso dettagli e competenze.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'value',
                'name' => 'home_value_3',
                'sort_order' => 53,
                'title' => 'Responsabilità',
                'subtitle' => 'leaf',
                'content' => 'Materiali e processi scelti con attenzione e consapevolezza.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'value',
                'name' => 'home_value_4',
                'sort_order' => 54,
                'title' => 'Made in Italy',
                'subtitle' => 'map-pin',
                'content' => 'Una filiera italiana fatta di esperienza, territorio e qualità.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'section_intro',
                'name' => 'home_featured_intro',
                'sort_order' => 60,
                'title' => 'Scelti per te',
                'subtitle' => 'In evidenza',
                'content' => null,
                'button_label' => 'Vedi tutti',
                'button_url' => '/catalog',
            ],
            [
                'type' => 'section_intro',
                'name' => 'home_formats_intro',
                'sort_order' => 70,
                'title' => 'Scegli come scrivere',
                'subtitle' => 'Trova quello giusto',
                'content' => 'Scegli il formato che segue il tuo modo di organizzare idee, impegni e progetti.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'format',
                'name' => 'home_format_daily',
                'sort_order' => 71,
                'title' => 'Agenda giornaliera',
                'subtitle' => 'Agende',
                'content' => 'Una pagina per ogni giorno: tanto spazio per programmare, annotare e avere tutto sotto controllo.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
            ],
            [
                'type' => 'format',
                'name' => 'home_format_weekly',
                'sort_order' => 72,
                'title' => 'Agenda settimanale',
                'subtitle' => 'Agende',
                'content' => 'La settimana a colpo d’occhio, per organizzare appuntamenti e priorità.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
            ],
            [
                'type' => 'format',
                'name' => 'home_format_dotted',
                'sort_order' => 73,
                'title' => 'Taccuino a punti',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'La griglia discreta ideale per bullet journal, schemi, appunti e creatività.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
            ],
            [
                'type' => 'format',
                'name' => 'home_format_lined',
                'sort_order' => 74,
                'title' => 'Taccuino a righe',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'Il formato classico per scrivere con ordine pensieri, note e progetti.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
            ],
            [
                'type' => 'format',
                'name' => 'home_format_blank',
                'sort_order' => 75,
                'title' => 'Taccuino a pagine bianche',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'Spazio libero per disegnare, progettare e lasciare correre le idee.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
            ],
            [
                'type' => 'editorial',
                'name' => 'home_story',
                'sort_order' => 80,
                'title' => 'Carta, colore e dettagli essenziali',
                'subtitle' => 'Dettagli CIAK',
                'content' => 'Usa questo slot per un’immagine ambientata caricata dal back office.',
                'button_label' => null,
                'button_url' => null,
            ],
            [
                'type' => 'editorial_banner',
                'name' => 'home_banner',
                'sort_order' => 90,
                'title' => 'Pensati per accompagnare lavoro, studio e viaggio',
                'subtitle' => 'Collezioni',
                'content' => 'Uno spazio flessibile per campagne, stagionalità o nuovi lanci.',
                'button_label' => 'Vai allo shop',
                'button_url' => '/catalog',
            ],
            [
                'type' => 'instagram_gallery',
                'name' => 'home_instagram',
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
                    'storefront_page_id' => $storefrontPage->id,
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

    private function validatePage(Request $request): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function templateForSlug(string $slug): string
    {
        $slug = trim($slug, '/ ');

        return match ($slug) {
            '', 'home' => 'home',
            'login' => 'login',
            default => 'blade',
        };
    }

    private function currentAdminStore(): Store
    {
        $adminStoreId = session('admin_store_id');

        if ($adminStoreId) {
            return Store::query()->findOrFail((int) $adminStoreId);
        }

        /** @var Store $store */
        $store = app('currentStore');

        return $store;
    }

    private function ensureSameStore(StorefrontPage $storefrontPage): void
    {
        $store = $this->currentAdminStore();

        abort_unless((int) $storefrontPage->store_id === (int) $store->id, 404);
    }
}

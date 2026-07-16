<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageBlock;
use App\Models\StorefrontPageBlockMedia;
use App\Models\StorefrontPageBlockTranslation;
use App\Models\StorefrontPageTranslation;
use App\Services\Storefront\Content\StaticPageEditorSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StorefrontPageController extends Controller
{
    public function __construct(
        private StaticPageEditorSchema $editorSchema,
    ) {}

    public function index(Request $request): View
    {
        $store = $this->currentAdminStore();

        if (! $this->storefrontEditorEnabled($store)) {
            return view('admin.storefront-pages.index', [
                'store' => $store,
                'pages' => StorefrontPage::query()->whereRaw('1 = 0')->paginate(20),
                'contentLocale' => $this->contentLocale($store),
                'usesTranslations' => false,
                'canManageStructure' => false,
                'editorAvailable' => false,
            ]);
        }

        $this->ensureStorefrontEditorPages($store);

        $pages = StorefrontPage::query()
            ->with('translations')
            ->withCount('blocks')
            ->where('store_id', $store->id)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(20);

        if ($this->usesTranslations($store)) {
            $pages->getCollection()->transform(
                fn (StorefrontPage $page) => $page->applyTranslation($this->contentLocale($store))
            );
        }

        return view('admin.storefront-pages.index', [
            'store' => $store,
            'pages' => $pages,
            'contentLocale' => $this->contentLocale($store),
            'usesTranslations' => $this->usesTranslations($store),
            'canManageStructure' => $this->canManageStructure($request),
            'editorAvailable' => true,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->canManageStructure($request), 403);

        $store = $this->currentAdminStore();
        $this->ensureStorefrontEditorEnabled($store);

        return view('admin.storefront-pages.create', [
            'page' => new StorefrontPage(),
            'store' => $store,
            'storefrontBaseUrl' => $this->storefrontBaseUrl($store),
            'contentLocale' => $this->contentLocale($store),
            'supportedLocales' => $this->supportedLocales($store),
            'usesTranslations' => $this->usesTranslations($store),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManageStructure($request), 403);

        $store = $this->currentAdminStore();
        $this->ensureStorefrontEditorEnabled($store);

        $validated = $this->validatePage($request, null, $store);

        $validated['store_id'] = $store->id;
        $validated['template'] = $this->templateForSlug((string) $validated['slug']);
        $validated['layout'] = null;

        $page = StorefrontPage::create($validated);

        if ($this->usesTranslations($store)) {
            $this->savePageTranslation($page, $store, $this->contentLocale($store), $validated);
        }

        return redirect()
            ->route('admin.storefront-pages.index')
            ->with('status', 'Pagina storefront creata correttamente.');
    }

    public function edit(StorefrontPage $storefrontPage): View
    {
        $this->ensureSameStore($storefrontPage);
        $store = $this->currentAdminStore();
        $this->ensureStorefrontEditorEnabled($store);
        $this->ensureDefaultBlocks($storefrontPage, $store);
        $contentLocale = $this->contentLocale($store);
        $storefrontPage->load('translations', 'blocks.translations', 'blocks.media');

        if ($this->usesTranslations($store)) {
            $storefrontPage->applyTranslation($contentLocale);
        }

        $this->prepareEditorBlocks($storefrontPage, $store);

        return view('admin.storefront-pages.edit', [
            'page' => $storefrontPage,
            'pageEditorSchema' => $this->editorSchema->page($storefrontPage),
            'blockEditorSchemas' => $storefrontPage->blocks
                ->mapWithKeys(fn (StorefrontPageBlock $block) => [$block->id => $this->editorSchema->block($block)]),
            'store' => $store,
            'storefrontBaseUrl' => $this->storefrontBaseUrl($store),
            'contentLocale' => $contentLocale,
            'supportedLocales' => $this->supportedLocales($store),
            'usesTranslations' => $this->usesTranslations($store),
        ]);
    }

    public function visualEdit(StorefrontPage $storefrontPage): View
    {
        $this->ensureSameStore($storefrontPage);
        $store = $this->currentAdminStore();
        $this->ensureStorefrontEditorEnabled($store);
        $this->ensureDefaultBlocks($storefrontPage, $store);
        $contentLocale = $this->contentLocale($store);
        $storefrontPage->load('translations', 'blocks.translations', 'blocks.media');

        if ($this->usesTranslations($store)) {
            $storefrontPage->applyTranslation($contentLocale);
        }

        $this->prepareEditorBlocks($storefrontPage, $store);

        return view('admin.storefront-pages.visual-edit', [
            'page' => $storefrontPage,
            'pageEditorSchema' => $this->editorSchema->page($storefrontPage),
            'blockEditorSchemas' => $storefrontPage->blocks
                ->mapWithKeys(fn (StorefrontPageBlock $block) => [$block->id => $this->editorSchema->block($block)]),
            'store' => $store,
            'storefrontBaseUrl' => $this->storefrontBaseUrl($store),
            'contentLocale' => $contentLocale,
            'supportedLocales' => $this->supportedLocales($store),
            'usesTranslations' => $this->usesTranslations($store),
        ]);
    }

    public function previewFrame(Request $request, StorefrontPage $storefrontPage): mixed
    {
        $this->ensureSameStore($storefrontPage);
        $store = $this->currentAdminStore();
        $this->ensureStorefrontEditorEnabled($store);
        $contentLocale = $this->contentLocale($store);

        $this->bindPreviewStore($store, $contentLocale);

        $slug = trim((string) ($storefrontPage->getRawOriginal('slug') ?: $storefrontPage->slug), '/');

        if ($slug === '' || $slug === 'home') {
            return app(\App\Http\Controllers\Storefront\HomeController::class)->index($request);
        }

        return app(\App\Http\Controllers\Storefront\PageController::class)->show($request, $slug);
    }

    public function update(Request $request, StorefrontPage $storefrontPage): RedirectResponse
    {
        $this->ensureSameStore($storefrontPage);

        $store = $this->currentAdminStore();
        $this->ensureStorefrontEditorEnabled($store);
        $validated = $this->validatePage($request, $storefrontPage, $store);

        $validated['template'] = $storefrontPage->template ?: $this->templateForSlug((string) $validated['slug']);
        $validated['layout'] = $storefrontPage->layout;

        if ($this->usesTranslations($store)) {
            $storefrontPage->update([
                'slug' => $storefrontPage->slug ?: $validated['slug'],
                'title' => $storefrontPage->title ?: $validated['title'],
                'template' => $validated['template'],
                'layout' => $validated['layout'],
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]);
            $this->savePageTranslation($storefrontPage, $store, $this->contentLocale($store), $validated);
        } else {
            $storefrontPage->update($validated);
        }

        return redirect()
            ->route('admin.storefront-pages.edit', $storefrontPage)
            ->with('status', 'Pagina storefront aggiornata correttamente.');
    }

    public function updateBlocks(Request $request, StorefrontPage $storefrontPage): RedirectResponse
    {
        $this->ensureSameStore($storefrontPage);
        $store = $this->currentAdminStore();
        $this->ensureStorefrontEditorEnabled($store);
        $usesTranslations = $this->usesTranslations($store);
        $contentLocale = $this->contentLocale($store);
        $editableBlockNames = $this->editableBlockNamesForStore($storefrontPage, $store);

        $validated = $request->validate([
            'blocks' => ['nullable', 'array'],
            'blocks.*.id' => ['nullable', 'integer', 'exists:storefront_page_blocks,id'],
            'blocks.*.type' => ['required', 'string', 'max:60'],
            'blocks.*.name' => ['nullable', 'string', 'max:190'],
            'blocks.*.title' => ['nullable', 'string', 'max:190'],
            'blocks.*.subtitle' => ['nullable', 'string', 'max:255'],
            'blocks.*.content' => ['nullable', 'string'],
            'blocks.*.specs' => ['nullable', 'string', 'max:1200'],
            'blocks.*.image_path' => ['nullable', 'string', 'max:255'],
            'blocks.*.mobile_image_path' => ['nullable', 'string', 'max:255'],
            'blocks.*.video_path' => ['nullable', 'string', 'max:255'],
            'blocks.*.image_alt' => ['nullable', 'string', 'max:255'],
            'blocks.*.mobile_image_alt' => ['nullable', 'string', 'max:255'],
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

            if ($editableBlockNames !== null && ! in_array((string) ($block->name ?: ($blockData['name'] ?? '')), $editableBlockNames, true)) {
                continue;
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

            $settings = is_array($block->settings) ? $block->settings : [];
            $settings['image_alt'] = $this->cleanNullableString($blockData['image_alt'] ?? data_get($settings, 'image_alt'));
            $settings['mobile_image_alt'] = $this->cleanNullableString($blockData['mobile_image_alt'] ?? data_get($settings, 'mobile_image_alt'));

            if (array_key_exists('specs', $blockData)) {
                if ($usesTranslations) {
                    $settings['specs'] ??= [];
                    $settings['specs'][$contentLocale] = $this->stringList($blockData['specs']);
                } else {
                    $settings['specs'] = $this->stringList($blockData['specs']);
                }
            }

            $block->fill([
                'type' => $blockData['type'],
                'name' => $blockData['name'] ?? null,
                'title' => $usesTranslations ? $block->title : ($blockData['title'] ?? null),
                'subtitle' => $usesTranslations ? $block->subtitle : ($blockData['subtitle'] ?? null),
                'content' => $usesTranslations ? $block->content : ($blockData['content'] ?? null),
                'image_path' => $imagePath,
                'mobile_image_path' => $mobileImagePath,
                'video_path' => $videoPath,
                'button_label' => $usesTranslations ? $block->button_label : ($blockData['button_label'] ?? null),
                'button_url' => $blockData['button_url'] ?? null,
                'button_new_tab' => (bool) ($blockData['button_new_tab'] ?? false),
                'sort_order' => (int) ($blockData['sort_order'] ?? ($index + 1)),
                'is_active' => (bool) ($blockData['is_active'] ?? false),
                'settings' => $settings,
            ]);

            $block->storefront_page_id = $storefrontPage->id;
            $block->save();

            if ($usesTranslations) {
                $this->saveBlockTranslation($block, $contentLocale, $blockData);
            }

            $this->syncBlockMedia($request, $block, $blockData, $index);
        }

        return redirect()
            ->back()
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

    public function destroy(Request $request, StorefrontPage $storefrontPage): RedirectResponse
    {
        abort_unless($this->canManageStructure($request), 403);

        $this->ensureSameStore($storefrontPage);
        $this->ensureStorefrontEditorEnabled($this->currentAdminStore());

        $storefrontPage->delete();

        return redirect()
            ->route('admin.storefront-pages.index')
            ->with('status', 'Pagina storefront eliminata correttamente.');
    }

    private function ensureDefaultBlocks(StorefrontPage $storefrontPage, Store $store): void
    {
        if (! $this->storefrontEditorEnabled($store)) {
            return;
        }

        if ($storefrontPage->slug === 'home' && $store->isB2C()) {
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
            $block = StorefrontPageBlock::query()->create([
                'storefront_page_id' => $storefrontPage->id,
                'type' => 'brand_grid',
                'name' => 'login_background_' . $index,
                'sort_order' => $index,
                'is_active' => true,
                'title' => 'Brand ' . $index,
                'image_path' => 'https://picsum.photos/seed/intempo-login-' . $index . '/900/700',
                'button_new_tab' => false,
            ]);

            $this->saveBlockTranslation($block, 'it', $block->only(['title', 'subtitle', 'content', 'button_label']));
        }
    }

    private function prepareEditorBlocks(StorefrontPage $storefrontPage, Store $store): void
    {
        if (! $storefrontPage->relationLoaded('blocks')) {
            return;
        }

        $blocks = $storefrontPage->blocks;
        $editableBlockNames = $this->editableBlockNamesForStore($storefrontPage, $store);

        if ($editableBlockNames !== null) {
            $blocks = $blocks
                ->filter(fn (StorefrontPageBlock $block) => in_array((string) $block->name, $editableBlockNames, true))
                ->values();
        }

        if ($this->isIntempoB2cHome($storefrontPage, $store)) {
            $blocks = $blocks
                ->map(fn (StorefrontPageBlock $block) => $this->applyIntempoEditorFallbacks($block))
                ->values();
        }

        $storefrontPage->setRelation('blocks', $blocks);
    }

    /**
     * @return array<int, string>|null
     */
    private function editableBlockNamesForStore(StorefrontPage $storefrontPage, Store $store): ?array
    {
        if ($this->isIntempoB2cHome($storefrontPage, $store)) {
            return [
                'home_hero',
                'home_about',
            ];
        }

        return null;
    }

    private function isIntempoB2cHome(StorefrontPage $storefrontPage, Store $store): bool
    {
        $slug = trim((string) ($storefrontPage->getRawOriginal('slug') ?: $storefrontPage->slug), '/');

        return $store->isB2C()
            && $slug === 'home'
            && strtolower(trim((string) $store->theme)) === 'intemposhop';
    }

    private function applyIntempoEditorFallbacks(StorefrontPageBlock $block): StorefrontPageBlock
    {
        $legacyText = [
            'CIAK Firenze',
            'Agende e taccuini per ogni giorno',
            'Oggetti quotidiani per scrivere, pianificare e portare con te le idee.',
            'Dal cuore di Firenze, CIAK crea agende e taccuini pensati per accompagnare idee, progetti e giornate piene di dettagli.',
            'Ciak celebra la bellezza della carta e la trasforma in esperienze di valore. Ogni prodotto nasce da attenzione, ricerca e passione artigianale.',
            'Scopri chi siamo',
        ];

        if ($block->name === 'home_hero') {
            $block->subtitle = $this->editorTextOrFallback($block->subtitle, $legacyText, __('themes_b2c.intempo.hero_eyebrow'));
            $block->title = $this->editorTextOrFallback($block->title, $legacyText, __('themes_b2c.intempo.hero_title'));
            $block->content = $this->editorTextOrFallback($block->content, $legacyText, __('themes_b2c.intempo.hero_intro'));
            $block->button_label = $this->editorTextOrFallback($block->button_label, [''], __('themes_b2c.intempo.discover_collection'));
            $block->button_url = $block->button_url ?: '/catalog';
        }

        if ($block->name === 'home_about') {
            $block->subtitle = $this->editorTextOrFallback($block->subtitle, ['La nostra storia', 'CIAK Firenze'], __('themes_b2c.intempo.about_us'));
            $block->title = $this->editorTextOrFallback($block->title, [''], __('themes_b2c.intempo.about_us'));
            $block->content = $this->editorTextOrFallback($block->content, $legacyText, __('themes_b2c.intempo.story_intro'));
            $block->button_label = $this->editorTextOrFallback($block->button_label, ['Scopri chi siamo', ''], __('themes_b2c.intempo.explore_intempo_world'));
            $block->button_url = in_array($block->button_url, [null, '', '/about'], true) ? '/catalog' : $block->button_url;
        }

        return $block;
    }

    /**
     * @param array<int, string> $legacyText
     */
    private function editorTextOrFallback(mixed $value, array $legacyText, string $fallback): string
    {
        $text = trim((string) $value);

        return $text === '' || in_array($text, $legacyText, true) ? $fallback : $text;
    }

    private function ensureStorefrontEditorPages(Store $store): void
    {
        if (! $store->isB2B() || ! $this->canManageStructure(request())) {
            return;
        }

        $page = StorefrontPage::query()->firstOrCreate(
            [
                'store_id' => $store->id,
                'slug' => 'login',
            ],
            [
                'title' => 'Accesso clienti',
                'description' => 'Pagina di accesso clienti B2B.',
                'template' => 'login',
                'layout' => null,
                'is_active' => true,
                'sort_order' => 10,
                'meta_title' => 'Accesso clienti',
                'meta_description' => null,
            ]
        );

        $this->ensureDefaultBlocks($page, $store);
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
                'content' => 'Una pagina per ogni giorno: tanto spazio per pensare e per fermarsi a scrivere ciò che conta.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
                'settings' => [
                    'specs' => [
                        'it' => ['Cinque lingue: EN-FR-DE-ES-IT', 'Orario', 'Ampio spazio per scrivere'],
                        'en' => ['Five languages: EN-FR-DE-ES-IT', 'Time schedule', 'Ample writing space'],
                    ],
                ],
            ],
            [
                'type' => 'format',
                'name' => 'home_format_weekly',
                'sort_order' => 72,
                'title' => 'Agenda settimanale',
                'subtitle' => 'Agende',
                'content' => 'Vista di sette giorni per chi organizza la settimana prima ancora che inizi.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
                'settings' => [
                    'specs' => [
                        'it' => ['Cinque lingue: EN-FR-DE-ES-IT', 'Settimana in due pagine', 'Calendario'],
                        'en' => ['Five languages: EN-FR-DE-ES-IT', 'Week on two pages', 'Calendar'],
                    ],
                ],
            ],
            [
                'type' => 'format',
                'name' => 'home_format_dotted',
                'sort_order' => 73,
                'title' => 'Pagine a puntini',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'Una griglia leggera che guida la scrittura: perfetta per liste, schizzi e bullet journal.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
                'settings' => [
                    'specs' => [
                        'it' => ['Struttura flessibile', 'Libertà di scrivere', 'Per chi ama organizzarsi'],
                        'en' => ['Flexible structure', 'Freedom to write and draw', 'For the organized mind'],
                    ],
                ],
            ],
            [
                'type' => 'format',
                'name' => 'home_format_lined',
                'sort_order' => 74,
                'title' => 'Pagine a righe',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'La pagina classica su cui scrivere: ordinata, familiare e simmetrica.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
                'settings' => [
                    'specs' => [
                        'it' => ['Scrittura ordinata', 'Nessuna distrazione', 'Adatta a testi lunghi'],
                        'en' => ['Neat handwriting', 'No distractions', 'Great for longer notes'],
                    ],
                ],
            ],
            [
                'type' => 'format',
                'name' => 'home_format_blank',
                'sort_order' => 75,
                'title' => 'Pagine bianche',
                'subtitle' => 'Taccuini e quaderni',
                'content' => 'Nessuna riga, nessun limite: solo spazio bianco per idee, disegni e pensieri che non seguono uno schema predefinito.',
                'button_label' => 'Scopri la selezione',
                'button_url' => '/catalog',
                'settings' => [
                    'specs' => [
                        'it' => ['Nessun limite', 'Spazio versatile', 'Massima libertà'],
                        'en' => ['No limits', 'Versatile space', 'Total freedom'],
                    ],
                ],
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
            $created = StorefrontPageBlock::query()->firstOrCreate(
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
                    'settings' => $block['settings'] ?? [],
                ]
            );

            if (! $created->wasRecentlyCreated && empty($created->settings) && ! empty($block['settings'])) {
                $created->forceFill(['settings' => $block['settings']])->save();
            }

            if ($created->wasRecentlyCreated) {
                $this->saveBlockTranslation($created, 'it', $block);
            }
        }
    }

    private function validatePage(Request $request, ?StorefrontPage $page = null, ?Store $store = null): array
    {
        $store ??= $this->currentAdminStore();
        $contentLocale = $this->contentLocale($store);
        $slugRule = ['required', 'string', 'max:120', 'regex:/^[a-z0-9\-\/]+$/'];

        if ($this->usesTranslations($store)) {
            $translation = $page?->translation($contentLocale);
            $slugRule[] = Rule::unique('storefront_page_translations', 'slug')
                ->where(fn ($query) => $query->where('store_id', $store->id)->where('locale', $contentLocale))
                ->ignore($translation?->id);
        } else {
            $slugRule[] = Rule::unique('storefront_pages', 'slug')
                ->where(fn ($query) => $query->where('store_id', $store->id))
                ->ignore($page?->id);
        }

        return $request->validate([
            'slug' => $slugRule,
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
        $store = current_store();

        return $store;
    }

    private function usesTranslations(Store $store): bool
    {
        return $store->supportsLocale('en') || $store->supportsLocale('es');
    }

    private function contentLocale(Store $store): string
    {
        $locale = strtolower((string) app()->getLocale());

        if ($store->supportsLocale($locale)) {
            return $locale;
        }

        return $store->defaultLocale();
    }

    /**
     * @return array<int, string>
     */
    private function supportedLocales(Store $store): array
    {
        return $store->supportedLocales();
    }

    private function storefrontBaseUrl(Store $store): string
    {
        $domain = trim((string) ($store->domain ?: config('app.url')));

        if ($domain === '') {
            return rtrim((string) config('app.url'), '/');
        }

        if (! preg_match('#^https?://#i', $domain)) {
            $scheme = parse_url(request()->getSchemeAndHttpHost(), PHP_URL_SCHEME)
                ?: parse_url((string) config('app.url'), PHP_URL_SCHEME)
                ?: 'https';

            $domain = $scheme . '://' . ltrim($domain, '/');
        }

        return rtrim($domain, '/');
    }

    private function savePageTranslation(StorefrontPage $page, Store $store, string $locale, array $data): void
    {
        StorefrontPageTranslation::query()->updateOrCreate(
            [
                'storefront_page_id' => $page->id,
                'locale' => $locale,
            ],
            [
                'store_id' => $store->id,
                'slug' => $data['slug'] ?? null,
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ]
        );

        if ($locale === 'it') {
            $page->forceFill([
                'slug' => $data['slug'] ?? $page->slug,
                'title' => $data['title'] ?? $page->title,
                'description' => $data['description'] ?? null,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ])->save();
        }
    }

    private function saveBlockTranslation(StorefrontPageBlock $block, string $locale, array $data): void
    {
        StorefrontPageBlockTranslation::query()->updateOrCreate(
            [
                'storefront_page_block_id' => $block->id,
                'locale' => $locale,
            ],
            [
                'title' => $data['title'] ?? null,
                'subtitle' => $data['subtitle'] ?? null,
                'content' => $data['content'] ?? null,
                'button_label' => $data['button_label'] ?? null,
            ]
        );

        if ($locale === 'it') {
            $block->forceFill([
                'title' => $data['title'] ?? null,
                'subtitle' => $data['subtitle'] ?? null,
                'content' => $data['content'] ?? null,
                'button_label' => $data['button_label'] ?? null,
            ])->save();
        }
    }

    private function ensureSameStore(StorefrontPage $storefrontPage): void
    {
        $store = $this->currentAdminStore();

        abort_unless((int) $storefrontPage->store_id === (int) $store->id, 404);
    }

    private function storefrontEditorEnabled(Store $store): bool
    {
        if ($store->isB2C()) {
            return true;
        }

        $user = request()->user();

        return $store->isB2B()
            && $user
            && method_exists($user, 'isSuperAdmin')
            && $user->isSuperAdmin();
    }

    private function ensureStorefrontEditorEnabled(Store $store): void
    {
        abort_unless($this->storefrontEditorEnabled($store), 404);
    }

    private function bindPreviewStore(Store $store, string $locale): void
    {
        app()->instance('currentStore', $store);
        app()->instance('adminStore', $store);
        app()->setLocale($locale);

        view()->share('currentStore', $store);
        view()->share('adminStore', $store);

        config([
            'app.store_theme' => $store->theme,
            'app.store_locale' => $locale,
            'app.current_store_id' => $store->id,
            'app.current_store_domain' => $store->domain,
            'app.admin_store_id' => $store->id,
            'app.admin_store_domain' => $store->domain,
        ]);
    }

    private function canManageStructure(Request $request): bool
    {
        $user = $request->user();

        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    private function cleanNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value) ?: [])
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}

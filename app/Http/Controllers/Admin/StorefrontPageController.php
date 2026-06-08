<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageBlock;
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
            'page' => $storefrontPage->load('blocks'),
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
                    ->store("storefront/pages/{$storefrontPage->id}", 'public');
            }

            if ($request->hasFile("blocks.{$index}.mobile_image_file")) {
                $mobileImagePath = $request->file("blocks.{$index}.mobile_image_file")
                    ->store("storefront/pages/{$storefrontPage->id}", 'public');
            }

            if ($request->hasFile("blocks.{$index}.video_file")) {
                $videoPath = $request->file("blocks.{$index}.video_file")
                    ->store("storefront/pages/{$storefrontPage->id}", 'public');
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
        }

        return redirect()
            ->route('admin.storefront-pages.edit', $storefrontPage)
            ->with('status', 'Slot contenuto aggiornati correttamente.');
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
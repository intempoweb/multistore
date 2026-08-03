<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorefrontPopupStoreRequest;
use App\Http\Requests\Admin\StorefrontPopupUpdateRequest;
use App\Models\Store;
use App\Models\StorefrontPopup;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StorefrontPopupController extends Controller
{
    public function index(Request $request): View
    {
        $store = $this->resolveAdminStore();

        $query = StorefrontPopup::query()
            ->with(['translations', 'stores'])
            ->forStore($store);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('translations', function ($translationQuery) use ($search) {
                        $translationQuery->where('title', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($request->filled('scope')) {
            $query->where('display_scope', (string) $request->input('scope'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $popups = $query->ordered()->paginate(25)->withQueryString();

        return view('admin.storefront-popups.index', [
            'store' => $store,
            'popups' => $popups,
            'scopeLabels' => StorefrontPopup::SCOPES,
            'frequencyLabels' => StorefrontPopup::FREQUENCIES,
        ]);
    }

    public function create(): View
    {
        $store = $this->resolveAdminStore();
        $availableStores = $this->availableStores();

        return view('admin.storefront-popups.create', [
            'store' => $store,
            'popup' => new StorefrontPopup([
                'store_id' => $store->id,
                'display_scope' => StorefrontPopup::SCOPE_HOME,
                'frequency' => StorefrontPopup::FREQUENCY_ONCE_SESSION,
                'delay_ms' => 800,
                'priority' => 0,
                'is_active' => true,
            ]),
            'availableStores' => $availableStores,
            'selectedStoreIds' => [$store->id],
            'locales' => $this->supportedLocalesForStores($availableStores, $store),
            'scopeLabels' => StorefrontPopup::SCOPES,
            'frequencyLabels' => StorefrontPopup::FREQUENCIES,
        ]);
    }

    public function store(StorefrontPopupStoreRequest $request): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $data = $request->validated();

        DB::transaction(function () use ($data, $store) {
            $selectedStores = $this->selectedStores($data, $store);
            $popup = StorefrontPopup::query()->create($this->popupPayload($data, $store));
            $popup->stores()->sync($selectedStores->modelKeys());
            $this->syncTranslations($popup, $data['translations'] ?? [], $selectedStores, $store);
        });

        return redirect()
            ->route('admin.storefront-popups.index')
            ->with('success', 'Popup creato correttamente.');
    }

    public function edit(StorefrontPopup $storefrontPopup): View
    {
        $store = $this->resolveAdminStore();
        $this->guardPopupContext($storefrontPopup, $store);
        $availableStores = $this->availableStores();
        $selectedStoreIds = $storefrontPopup->stores()->pluck('stores.id')->push($storefrontPopup->store_id)->unique()->values()->all();

        return view('admin.storefront-popups.edit', [
            'store' => $store,
            'popup' => $storefrontPopup->load('translations'),
            'availableStores' => $availableStores,
            'selectedStoreIds' => $selectedStoreIds,
            'locales' => $this->supportedLocalesForStores($availableStores->whereIn('id', $selectedStoreIds), $store),
            'scopeLabels' => StorefrontPopup::SCOPES,
            'frequencyLabels' => StorefrontPopup::FREQUENCIES,
        ]);
    }

    public function update(StorefrontPopupUpdateRequest $request, StorefrontPopup $storefrontPopup): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $this->guardPopupContext($storefrontPopup, $store);

        $data = $request->validated();

        DB::transaction(function () use ($data, $store, $storefrontPopup) {
            $selectedStores = $this->selectedStores($data, $store);
            $storefrontPopup->update($this->popupPayload($data, $store));
            $storefrontPopup->stores()->sync($selectedStores->modelKeys());
            $this->syncTranslations($storefrontPopup, $data['translations'] ?? [], $selectedStores, $store);
        });

        return redirect()
            ->route('admin.storefront-popups.index')
            ->with('success', 'Popup aggiornato correttamente.');
    }

    public function destroy(StorefrontPopup $storefrontPopup): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $this->guardPopupContext($storefrontPopup, $store);

        $storefrontPopup->delete();

        return redirect()
            ->route('admin.storefront-popups.index')
            ->with('success', 'Popup eliminato correttamente.');
    }

    private function popupPayload(array $data, Store $store): array
    {
        return [
            'store_id' => $store->id,
            'name' => (string) $data['name'],
            'display_scope' => (string) $data['display_scope'],
            'frequency' => (string) $data['frequency'],
            'position' => 'center',
            'image_url' => $this->nullableString($data['image_url'] ?? null),
            'cta_url' => $this->nullableString($data['cta_url'] ?? null),
            'open_in_new_tab' => (bool) ($data['open_in_new_tab'] ?? false),
            'background_color' => $this->nullableString($data['background_color'] ?? null),
            'text_color' => $this->nullableString($data['text_color'] ?? null),
            'button_background_color' => $this->nullableString($data['button_background_color'] ?? null),
            'button_text_color' => $this->nullableString($data['button_text_color'] ?? null),
            'delay_ms' => (int) ($data['delay_ms'] ?? 0),
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
    }

    private function syncTranslations(StorefrontPopup $popup, array $translations, EloquentCollection $stores, Store $fallbackStore): void
    {
        foreach ($this->supportedLocalesForStores($stores, $fallbackStore) as $locale) {
            $translation = $translations[$locale] ?? [];

            $popup->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'title' => $this->nullableString($translation['title'] ?? null),
                    'subtitle' => $this->nullableString($translation['subtitle'] ?? null),
                    'body' => $this->nullableString($translation['body'] ?? null),
                    'cta_label' => $this->nullableString($translation['cta_label'] ?? null),
                ]
            );
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function resolveAdminStore(): Store
    {
        $store = app('adminStore');

        if (!$store instanceof Store) {
            throw new InvalidArgumentException('Store admin non risolto.');
        }

        return $store;
    }

    private function guardPopupContext(StorefrontPopup $popup, Store $store): void
    {
        $belongsToStore = (int) $popup->store_id === (int) $store->id
            || $popup->stores()->whereKey($store->id)->exists();

        abort_if(! $belongsToStore, 404);
    }

    private function availableStores(): EloquentCollection
    {
        return Store::query()
            ->where('is_active', true)
            ->orderBy('is_b2b')
            ->orderBy('name')
            ->get(['id', 'name', 'site_code', 'domain', 'is_b2b', 'supported_locales', 'default_locale']);
    }

    private function selectedStores(array $data, Store $currentStore): EloquentCollection
    {
        $storeIds = collect($data['store_ids'] ?? [])
            ->push($currentStore->id)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        return Store::query()
            ->where('is_active', true)
            ->whereIn('id', $storeIds)
            ->get();
    }

    private function supportedLocalesForStores(EloquentCollection $stores, Store $fallbackStore): array
    {
        $locales = $stores
            ->flatMap(fn (Store $store) => $store->supportedLocales($store->defaultLocale('it')))
            ->push($fallbackStore->defaultLocale('it'))
            ->unique()
            ->values()
            ->all();

        return $locales ?: $fallbackStore->supportedLocales($fallbackStore->defaultLocale('it'));
    }
}

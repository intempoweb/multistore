<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShippingRuleStoreRequest;
use App\Http\Requests\Admin\ShippingRuleUpdateRequest;
use App\Models\ShippingRule;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ShippingRuleController extends Controller
{
    public function index(Request $request): View
    {
        $store = $this->resolveAdminStore();

        $query = ShippingRule::query()->forStore($store);

        $country = ShippingRule::normalizeCountry($request->input('country'));
        $province = ShippingRule::normalizeProvince($request->input('province'));
        $cap = ShippingRule::normalizeCap($request->input('cap'));

        if ($country !== null) {
            $query->where('country', 'like', '%' . $country . '%');
        }

        if ($province !== null) {
            $query->where('province', 'like', '%' . $province . '%');
        }

        if ($cap !== null) {
            $query->where('cap', 'like', preg_replace('/\*+$/', '', $cap) . '%');
        }

        $rules = $query
            ->orderByDesc('priority')
            ->orderBy('type')
            ->orderBy('country')
            ->orderBy('province')
            ->orderBy('cap')
            ->orderBy('min_amount')
            ->orderBy('weight_from')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.shipping-rules.index', [
            'store' => $store,
            'rules' => $rules,
            'countries' => $this->resolveCountries(),
            'shareableStores' => $this->resolveShareableStores($store),
            'sharedStoreIds' => $this->resolveSharedStoreIds($store),
            'hasExportableTableRules' => $store->isB2C() && ShippingRule::query()
                ->forStore($store)
                ->where('type', 'table')
                ->exists(),
        ]);
    }

    public function create(): View
    {
        $store = $this->resolveAdminStore();

        return view('admin.shipping-rules.create', [
            'store' => $store,
            'countries' => $this->resolveCountries(),
            'rule' => new ShippingRule([
                'store_id' => $store->id,
                'ditta_cg18' => (int) $store->ditta_cg18,
                'erp_site_code' => (int) $store->erp_site_code,
                'type' => 'fixed',
                'country' => 'ITA',
                'priority' => 100,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(ShippingRuleStoreRequest $request): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $data = $this->normalizeRulePayload($request->validated());

        ShippingRule::query()->create([
            'store_id' => $store->id,
            'ditta_cg18' => (int) $store->ditta_cg18,
            'erp_site_code' => (int) $store->erp_site_code,
            'type' => $data['type'],
            'country' => $data['country'],
            'province' => $data['province'],
            'cap' => $data['cap'],
            'weight_from' => $data['weight_from'],
            'min_amount' => $data['min_amount'],
            'max_amount' => $data['max_amount'],
            'amount' => $data['amount'],
            'priority' => $data['priority'],
            'is_active' => $data['is_active'],
        ]);

        return redirect()
            ->route('admin.shipping-rules.index')
            ->with('success', 'Regola di spedizione creata correttamente.');
    }

    public function edit(ShippingRule $shippingRule): View
    {
        $store = $this->resolveAdminStore();
        $this->guardRuleBelongsToStore($shippingRule, $store);

        return view('admin.shipping-rules.edit', [
            'store' => $store,
            'rule' => $shippingRule,
            'countries' => $this->resolveCountries(),
        ]);
    }

    public function update(
        ShippingRuleUpdateRequest $request,
        ShippingRule $shippingRule
    ): RedirectResponse {
        $store = $this->resolveAdminStore();
        $this->guardRuleBelongsToStore($shippingRule, $store);

        $data = $this->normalizeRulePayload($request->validated(), $shippingRule);

        $shippingRule->update([
            'type' => $data['type'],
            'country' => $data['country'],
            'province' => $data['province'],
            'cap' => $data['cap'],
            'weight_from' => $data['weight_from'],
            'min_amount' => $data['min_amount'],
            'max_amount' => $data['max_amount'],
            'amount' => $data['amount'],
            'priority' => $data['priority'],
            'is_active' => $data['is_active'],
        ]);

        return redirect()
            ->route('admin.shipping-rules.index')
            ->with('success', 'Regola di spedizione aggiornata correttamente.');
    }

    public function destroy(ShippingRule $shippingRule): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $this->guardRuleBelongsToStore($shippingRule, $store);

        $shippingRule->delete();

        return redirect()
            ->route('admin.shipping-rules.index')
            ->with('success', 'Regola di spedizione eliminata correttamente.');
    }

    public function updateSharedStores(Request $request): RedirectResponse
    {
        $store = $this->resolveAdminStore();

        $validated = $request->validate([
            'shared_store_ids' => ['nullable', 'array'],
            'shared_store_ids.*' => ['integer'],
        ]);

        $allowedStoreIds = $this->resolveShareableStores($store)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedStoreIds = collect($validated['shared_store_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $allowedStoreIds, true))
            ->values()
            ->all();

        $ruleIds = ShippingRule::query()
            ->where('store_id', $store->id)
            ->where('type', 'table')
            ->pluck('id');

        if ($ruleIds->isEmpty()) {
            return redirect()
                ->route('admin.shipping-rules.index')
                ->with('error', 'Nessuna regola table trovata per lo store corrente.');
        }

        ShippingRule::query()
            ->whereIn('id', $ruleIds)
            ->each(function (ShippingRule $rule) use ($selectedStoreIds) {
                $rule->stores()->sync($selectedStoreIds);
            });

        return redirect()
            ->route('admin.shipping-rules.index')
            ->with('success', 'Condivisione listino spedizioni aggiornata correttamente.');
    }

    protected function normalizeRulePayload(array $data, ?ShippingRule $existingRule = null): array
    {
        $type = strtolower(trim((string) ($data['type'] ?? $existingRule?->type ?? 'fixed')));

        if (!in_array($type, ['fixed', 'free_over', 'table'], true)) {
            $type = 'fixed';
        }

        $amount = $data['amount'] ?? null;

        if ($type === 'free_over') {
            $amount = 0;
        }

        return [
            'type' => $type,
            'country' => ShippingRule::normalizeCountry($data['country'] ?? null),
            'province' => ShippingRule::normalizeProvince($data['province'] ?? null),
            'cap' => ShippingRule::normalizeCap($data['cap'] ?? null),
            'weight_from' => $data['weight_from'] ?? null,
            'min_amount' => $data['min_amount'] ?? null,
            'max_amount' => $data['max_amount'] ?? null,
            'amount' => $amount,
            'priority' => (int) ($data['priority'] ?? $existingRule?->priority ?? 100),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    protected function resolveAdminStore(): Store
    {
        $store = null;

        if (session()->has('admin_store_id')) {
            $store = Store::query()
                ->where('id', (int) session('admin_store_id'))
                ->where('is_active', true)
                ->first();
        }

        if (!$store instanceof Store) {
            $boundStore = current_store();

            if ($boundStore instanceof Store) {
                $store = $boundStore;
            }
        }

        if (!$store instanceof Store) {
            throw new InvalidArgumentException('Nessuno store admin selezionato.');
        }

        return $store;
    }

    protected function guardRuleBelongsToStore(ShippingRule $rule, Store $store): void
    {
        $matchesStore = (int) $rule->store_id === (int) $store->id;

        $matchesPivotStore = $rule->stores()
            ->where('stores.id', $store->id)
            ->exists();

        $matchesFallbackContext = $rule->store_id === null
            && (int) $rule->ditta_cg18 === (int) $store->ditta_cg18
            && (int) $rule->erp_site_code === (int) $store->erp_site_code;

        abort_unless($matchesStore || $matchesPivotStore || $matchesFallbackContext, 404);
    }

    protected function resolveShareableStores(Store $store): Collection
    {
        return Store::query()
            ->where('is_active', true)
            ->where('is_b2b', false)
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('id', '!=', (int) $store->id)
            ->orderBy('name')
            ->get();
    }

    protected function resolveSharedStoreIds(Store $store): array
    {
        return ShippingRule::query()
            ->where('store_id', $store->id)
            ->where('type', 'table')
            ->with('stores:id')
            ->get()
            ->flatMap(fn (ShippingRule $rule) => $rule->stores->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveCountries(): Collection
    {
        $path = app_path('Data/countries.json');

        if (!File::exists($path)) {
            return collect();
        }

        $countries = json_decode((string) File::get($path), true);

        return collect(is_array($countries) ? $countries : [])
            ->filter(fn ($country) => is_array($country) && !empty($country['code']))
            ->map(fn ($country) => [
                'code' => strtoupper((string) $country['code']),
                'label' => (string) ($country['label'] ?? $country['code']),
            ])
            ->sortBy('label')
            ->values();
    }
}

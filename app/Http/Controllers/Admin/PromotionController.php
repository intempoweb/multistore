<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionStoreRequest;
use App\Http\Requests\Admin\PromotionUpdateRequest;
use App\Models\Promotion;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $store = $this->resolveAdminStore();

        $query = Promotion::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where(function ($q) use ($store) {
                $q->whereNull('site_type')
                    ->orWhere('site_type', (int) $store->erp_site_code);
            });

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . mb_strtoupper($search) . '%');
            });
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->input('type'));
        }

        if ($request->filled('discount_type')) {
            $query->where('discount_type', (string) $request->input('discount_type'));
        }

        if ($request->filled('scope')) {
            $query->where('scope', (string) $request->input('scope'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $promotions = $query
            ->orderBy('priority')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.promotions.index', [
            'store' => $store,
            'promotions' => $promotions,
        ]);
    }

    public function create(): View
    {
        $store = $this->resolveAdminStore();

        return view('admin.promotions.create', [
            'store' => $store,
            'promotion' => new Promotion([
                'ditta_cg18' => (int) $store->ditta_cg18,
                'site_type' => (int) $store->erp_site_code,
                'discount_type' => 'fixed',
                'discount_value' => null,
                'scope' => 'cart',
                'priority' => 0,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(PromotionStoreRequest $request): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $data = $request->validated();

        Promotion::query()->create($this->buildPromotionPayload($data, $store));

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'Promozione creata correttamente.');
    }

    public function edit(Promotion $promotion): View
    {
        $store = $this->resolveAdminStore();
        $this->guardPromotionContext($promotion, $store);

        return view('admin.promotions.edit', [
            'store' => $store,
            'promotion' => $promotion,
        ]);
    }

    public function update(PromotionUpdateRequest $request, Promotion $promotion): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $this->guardPromotionContext($promotion, $store);

        $data = $request->validated();

        $promotion->update($this->buildPromotionPayload($data, $store));

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'Promozione aggiornata correttamente.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $this->guardPromotionContext($promotion, $store);

        $promotion->delete();

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'Promozione eliminata correttamente.');
    }

    protected function buildPromotionPayload(array $data, Store $store): array
    {
        $conditions = [];

        if (($data['minimum_subtotal'] ?? null) !== null && $data['minimum_subtotal'] !== '') {
            $conditions[] = [
                'type' => 'minimum_subtotal',
                'value' => (float) $data['minimum_subtotal'],
            ];
        }

        if (($data['requires_coupon'] ?? false) === true) {
            $couponCodes = $this->normalizeCouponCodes($data['coupon_codes'] ?? null);

            $conditions[] = [
                'type' => 'coupon_code',
                'value' => [
                    'codes' => $couponCodes,
                ],
            ];
        }

        if (($data['usage_limit_per_customer'] ?? null) !== null && $data['usage_limit_per_customer'] !== '') {
            $conditions[] = [
                'type' => 'usage_limit_per_customer',
                'value' => [
                    'limit' => (int) $data['usage_limit_per_customer'],
                ],
            ];
        }

        $discountType = $this->normalizeDiscountType((string) ($data['discount_type'] ?? 'fixed'));
        $scope = $this->normalizeScope((string) ($data['scope'] ?? 'cart'));

        $actions = [
            [
                'type' => $discountType === 'percent' ? 'discount_percent' : 'discount_fixed',
                'value' => (float) ($data['discount_value'] ?? 0),
                'scope' => $scope,
            ],
        ];

        return [
            'ditta_cg18' => (int) $store->ditta_cg18,
            'site_type' => (int) $store->erp_site_code,
            'name' => (string) $data['name'],
            'code' => $data['code'] ?? null,
            'type' => $data['type'] ?? $this->resolveLegacyType($discountType, $scope),
            'discount_type' => $discountType,
            'discount_value' => (float) $data['discount_value'],
            'scope' => $scope,
            'minimum_subtotal' => $data['minimum_subtotal'] ?? null,
            'conditions' => $conditions,
            'actions' => $actions,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
    }

    protected function normalizeCouponCodes(mixed $value): array
    {
        if ($value === null || trim((string) $value) === '') {
            return [];
        }

        return collect(preg_split('/[\s,;]+/', (string) $value) ?: [])
            ->map(fn ($code) => mb_strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeDiscountType(string $type): string
    {
        $type = strtolower(trim($type));

        return in_array($type, ['percent', 'percentage'], true) ? 'percent' : 'fixed';
    }

    protected function normalizeScope(string $scope): string
    {
        $scope = strtolower(trim($scope));

        return in_array($scope, ['line', 'item', 'product', 'row'], true) ? 'line' : 'cart';
    }

    protected function resolveLegacyType(string $discountType, string $scope): string
    {
        if ($scope === 'line') {
            return $discountType === 'percent' ? 'line_percent' : 'line_fixed';
        }

        return $discountType === 'percent' ? 'cart_percent' : 'cart_fixed';
    }

    protected function guardPromotionContext(Promotion $promotion, Store $store): void
    {
        abort_unless((int) $promotion->ditta_cg18 === (int) $store->ditta_cg18, 404);

        abort_unless(
            $promotion->site_type === null || (int) $promotion->site_type === (int) $store->erp_site_code,
            404
        );
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

        if (!$store instanceof Store && app()->bound('currentStore')) {
            $boundStore = app('currentStore');

            if ($boundStore instanceof Store) {
                $store = $boundStore;
            }
        }

        if (!$store instanceof Store) {
            throw new InvalidArgumentException('Nessuno store admin selezionato.');
        }

        return $store;
    }
}
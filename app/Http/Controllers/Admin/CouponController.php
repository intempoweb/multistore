<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponStoreRequest;
use App\Http\Requests\Admin\CouponUpdateRequest;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $store = $this->resolveAdminStore();

        $query = Coupon::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code);

        if ($request->filled('search')) {
            $search = mb_strtoupper(trim((string) $request->input('search')));
            $query->where('code', 'like', '%' . $search . '%');
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $coupons = $query
            ->with('promotion:id,name,discount_type,discount_value')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.coupons.index', [
            'store' => $store,
            'coupons' => $coupons,
        ]);
    }

    public function create(): View
    {
        $store = $this->resolveAdminStore();

        return view('admin.coupons.create', [
            'store' => $store,
            'coupon' => new Coupon([
                'ditta_cg18' => (int) $store->ditta_cg18,
                'site_type' => (int) $store->erp_site_code,
                'is_active' => true,
            ]),
            'promotions' => $this->availablePromotions($store),
        ]);
    }

    public function store(CouponStoreRequest $request): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $data = $request->validated();

        $promotionId = $data['promotion_id'] ?? null;

        if (!$promotionId) {
            $promotionId = $this->createOrReusePromotionFromCode($data['code'], $store)->id;
        }

        Coupon::query()->create([
            'ditta_cg18' => (int) $store->ditta_cg18,
            'site_type' => (int) $store->erp_site_code,
            'code' => $data['code'],
            'promotion_id' => $promotionId,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', 'Coupon creato correttamente.');
    }

    public function edit(Coupon $coupon): View
    {
        $store = $this->resolveAdminStore();
        $this->guardCouponContext($coupon, $store);

        return view('admin.coupons.edit', [
            'store' => $store,
            'coupon' => $coupon,
            'promotions' => $this->availablePromotions($store),
        ]);
    }

    public function update(CouponUpdateRequest $request, Coupon $coupon): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $this->guardCouponContext($coupon, $store);

        $data = $request->validated();
        $promotionId = $data['promotion_id'] ?? null;

        if (!$promotionId) {
            $promotionId = $this->createOrReusePromotionFromCode($data['code'], $store)->id;
        }

        $coupon->update([
            'code' => $data['code'],
            'promotion_id' => $promotionId,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', 'Coupon aggiornato correttamente.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $this->guardCouponContext($coupon, $store);

        $coupon->delete();

        return redirect()
            ->route('admin.coupons.index')
            ->with('success', 'Coupon eliminato correttamente.');
    }

    protected function createOrReusePromotionFromCode(string $code, Store $store): Promotion
    {
        $code = mb_strtoupper(trim($code));

        $existing = Promotion::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where(function ($query) use ($store) {
                $query->whereNull('site_type')
                    ->orWhere('site_type', (int) $store->erp_site_code);
            })
            ->where('code', $code)
            ->first();

        if ($existing instanceof Promotion) {
            return $existing;
        }

        preg_match('/(\d+)$/', $code, $matches);
        $value = isset($matches[1]) ? (float) $matches[1] : 0.0;

        return Promotion::query()->create([
            'ditta_cg18' => (int) $store->ditta_cg18,
            'site_type' => (int) $store->erp_site_code,
            'name' => 'Coupon ' . $code,
            'code' => $code,
            'type' => 'cart_fixed',
            'discount_type' => 'fixed',
            'discount_value' => $value,
            'scope' => 'cart',
            'minimum_subtotal' => null,
            'conditions' => [
                [
                    'type' => 'coupon_code',
                    'value' => [
                        'codes' => [$code],
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'discount_fixed',
                    'value' => $value,
                    'scope' => 'cart',
                ],
            ],
            'priority' => 0,
            'is_active' => true,
        ]);
    }

    protected function availablePromotions(Store $store)
    {
        return Promotion::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where(function ($query) use ($store) {
                $query->whereNull('site_type')
                    ->orWhere('site_type', (int) $store->erp_site_code);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'discount_type', 'discount_value']);
    }

    protected function guardCouponContext(Coupon $coupon, Store $store): void
    {
        abort_unless((int) $coupon->ditta_cg18 === (int) $store->ditta_cg18, 404);

        abort_unless(
            $coupon->site_type === null || (int) $coupon->site_type === (int) $store->erp_site_code,
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
}

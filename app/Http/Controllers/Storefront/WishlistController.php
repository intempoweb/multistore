<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CustomerWishlistItem;
use App\Models\Product;
use App\Models\Store;
use App\Services\Storefront\Cart\CartService;
use App\Services\Storefront\ThemeResolver;
use App\Services\Storefront\Wishlist\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class WishlistController extends Controller
{
    public function __construct(
        protected WishlistService $wishlistService,
        protected CartService $cartService,
        protected ThemeResolver $themeResolver,
    ) {
    }

    public function index(Request $request): View
    {
        $store = $this->resolveStore();
        $customer = auth('customer')->user();

        $items = $this->wishlistService->getItems(
            customer: $customer,
            store: $store,
        );

        return view($this->themeResolver->view('account.wishlist', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'wishlistItems' => $items,
            'wishlistCount' => $items->count(),
        ]);
    }

    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string'],
        ]);

        $store = $this->resolveStore();
        $customer = auth('customer')->user();
        $product = $this->resolveProduct($store, (string) $validated['sku']);

        $this->wishlistService->add(
            customer: $customer,
            store: $store,
            product: $product,
        );

        return $this->response($request, $store, 'Prodotto aggiunto ai preferiti.', [
            'added' => true,
        ]);
    }

    public function toggle(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string'],
        ]);

        $store = $this->resolveStore();
        $customer = auth('customer')->user();
        $product = $this->resolveProduct($store, (string) $validated['sku']);

        $result = $this->wishlistService->toggle(
            customer: $customer,
            store: $store,
            product: $product,
        );

        return $this->response(
            $request,
            $store,
            $result['added']
                ? 'Prodotto aggiunto ai preferiti.'
                : 'Prodotto rimosso dai preferiti.',
            [
                'added' => (bool) $result['added'],
            ]
        );
    }

    public function remove(
        Request $request,
        CustomerWishlistItem $item
    ): RedirectResponse|JsonResponse {
        $store = $this->resolveStore();
        $customer = auth('customer')->user();

        $this->guardWishlistItem($item, $store, (int) $customer->id);

        $item->delete();

        return $this->response($request, $store, 'Prodotto rimosso dai preferiti.', [
            'added' => false,
        ]);
    }

    public function moveToCart(
        Request $request,
        CustomerWishlistItem $item
    ): RedirectResponse|JsonResponse {
        $store = $this->resolveStore();
        $customer = auth('customer')->user();

        $this->guardWishlistItem($item, $store, (int) $customer->id);

        $product = $this->resolveProduct($store, (string) $item->sku);

        try {
            $this->cartService->addProduct(
                store: $store,
                product: $product,
                quantity: 1,
                customer: $customer,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->response(
                $request,
                $store,
                $exception->getMessage() ?: 'Impossibile spostare il prodotto nel carrello.',
                [],
                422,
                'error'
            );
        }

        $item->delete();

        return $this->response($request, $store, 'Prodotto spostato nel carrello.', [
            'added' => false,
        ]);
    }

    protected function resolveProduct(Store $store, string $sku): Product
    {
        return Product::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code)
            ->where('sku', trim($sku))
            ->where('is_active', true)
            ->firstOrFail();
    }

    protected function guardWishlistItem(
        CustomerWishlistItem $item,
        Store $store,
        int $customerId
    ): void {
        abort_unless((int) $item->customer_id === $customerId, 404);
        abort_unless((int) $item->ditta_cg18 === (int) $store->ditta_cg18, 404);
        abort_unless((int) $item->site_type === (int) $store->erp_site_code, 404);
    }

    protected function resolveStore(): Store
    {
        $store = app()->bound('currentStore')
            ? app('currentStore')
            : null;

        abort_unless($store instanceof Store, 404);

        return $store;
    }

    protected function response(
        Request $request,
        Store $store,
        string $message,
        array $extra = [],
        int $status = 200,
        string $flashType = 'success'
    ): RedirectResponse|JsonResponse {
        $customer = auth('customer')->user();

        $payload = array_merge([
            'valid' => $status < 400,
            'message' => $message,
            'wishlist_count' => $customer
                ? $this->wishlistService->count($customer, $store)
                : 0,
        ], $extra);

        if ($request->expectsJson()) {
            return response()->json($payload, $status);
        }

        return redirect()
            ->back()
            ->with($flashType, $message);
    }
}
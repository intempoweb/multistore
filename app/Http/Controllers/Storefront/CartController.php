<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Store;
use App\Services\Storefront\Cart\CartService;
use App\Services\Storefront\Cart\Import\CartImportService;
use App\Services\Storefront\Promotion\CouponService;
use App\Services\Storefront\ThemeResolver;
use App\Services\Storefront\Totals\CartTotalsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CartTotalsService $cartTotalsService,
        protected ThemeResolver $themeResolver,
        protected CouponService $couponService,
        protected CartImportService $cartImportService,
    ) {
    }

    public function index(Request $request): View
    {
        $store = $this->resolveStore();
        $cart = $this->resolveCart($store);
        $decoratedItems = $this->decorateCartItems($cart, $store);

        $cart->setRelation('items', $decoratedItems);

        $calculatedTotals = $this->cartTotalsService->calculate($cart);

        return view($this->themeResolver->view('cart.index', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'cart' => $cart,
            'items' => $decoratedItems,
            'cartCount' => $this->resolveCartCountFromItems($decoratedItems),
            'cartTotal' => (float) ($calculatedTotals['grand_total'] ?? 0),
            'shippingCost' => (float) ($calculatedTotals['shipping_total'] ?? 0),
            'shippingDetails' => $calculatedTotals['shipping'] ?? [],
            'cartTotals' => $calculatedTotals,
            'promotions' => $calculatedTotals['promotions'] ?? [],
            'activeCouponCode' => $this->couponService->extractCouponCodeFromCart($cart),
        ]);
    }

    public function mini(Request $request): View
    {
        $store = $this->resolveStore();
        $cart = $this->resolveCart($store);
        $decoratedItems = $this->decorateCartItems($cart, $store);

        $cart->setRelation('items', $decoratedItems);

        $calculatedTotals = $this->cartTotalsService->calculate($cart);

        return view($this->themeResolver->view('cart.minicart', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'cart' => $cart,
            'items' => $decoratedItems,
            'cartCount' => $this->resolveCartCountFromItems($decoratedItems),
            'cartTotal' => (float) ($calculatedTotals['grand_total'] ?? 0),
            'cartTotals' => $calculatedTotals,
            'promotions' => $calculatedTotals['promotions'] ?? [],
            'activeCouponCode' => $this->couponService->extractCouponCodeFromCart($cart),
        ]);
    }

    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string'],
            'qty' => ['required', 'numeric', 'min:1'],
        ]);

        $store = $this->resolveStore();

        $product = Product::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code)
            ->where('sku', trim((string) $validated['sku']))
            ->where('is_active', true)
            ->firstOrFail();

        try {
            $cart = $this->cartService->addProduct(
                store: $store,
                product: $product,
                quantity: (float) $validated['qty'],
                customer: null,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->quantityErrorResponse($request, $exception);
        }

        return $this->cartResponse($request, $store, $cart, 'Prodotto aggiunto al carrello.');
    }

    public function import(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:10240'],
        ]);

        $store = $this->resolveStore();
        $customer = null;

        if (!$store->is_b2b) {
            abort(404);
        }

        try {
            $result = $this->cartImportService->import(
                store: $store,
                file: $validated['import_file'],
                customer: $customer,
            );
        } catch (InvalidArgumentException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'valid' => false,
                ], 422);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        $cart = $this->resolveCart($store);

        $message = sprintf(
            'Import completato: %d prodotti importati, %d errori.',
            (int) ($result['imported'] ?? 0),
            (int) ($result['failed'] ?? 0)
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'valid' => true,
                'result' => $result,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', $message)
            ->with('cart_import_errors', $result['errors'] ?? []);
    }

    public function downloadImportTemplate(): BinaryFileResponse
    {
        $store = $this->resolveStore();

        if (!$store->is_b2b) {
            abort(404);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import carrello');

        $sheet->fromArray([
            ['sku', 'qty'],
            ['ESEMPIO-SKU-001', 10],
            ['ESEMPIO-SKU-002', 25],
        ], null, 'A1');

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);

        $path = storage_path('app/cart-import-template-' . uniqid('', true) . '.xlsx');

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return Response::download($path, 'template-import-carrello.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function export(): BinaryFileResponse
    {
        $store = $this->resolveStore();

        if (!$store->is_b2b) {
            abort(404);
        }

        $cart = $this->resolveCart($store);
        $items = $this->decorateCartItems($cart, $store)
            ->reject(fn ($item) => str_starts_with(strtoupper(trim((string) ($item->sku ?? ''))), 'MTBUONO'))
            ->values();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Export carrello');

        $sheet->fromArray([
            ['sku', 'qty', 'product_name'],
        ], null, 'A1');

        $rowNumber = 2;

        foreach ($items as $item) {
            $sheet->fromArray([
                [
                    (string) $item->sku,
                    (float) $item->quantity,
                    (string) ($item->product_name ?? ''),
                ],
            ], null, 'A' . $rowNumber);

            $rowNumber++;
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(60);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        $path = storage_path('app/cart-export-' . uniqid('', true) . '.xlsx');

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return Response::download($path, 'export-carrello.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function update(Request $request, CartItem $item): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'numeric', 'min:1'],
        ]);

        $store = $this->resolveStore();

        $this->guardCartItemStoreContext($item, $store);

        try {
            $cart = $this->cartService->updateItemQuantity(
                item: $item,
                quantity: (float) $validated['qty'],
                customer: null,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->quantityErrorResponse($request, $exception);
        }

        return $this->cartResponse($request, $store, $cart, 'Carrello aggiornato.');
    }

    public function remove(Request $request, CartItem $item): RedirectResponse|JsonResponse
    {
        $store = $this->resolveStore();

        $this->guardCartItemStoreContext($item, $store);

        $cart = $this->cartService->removeItem($item);

        return $this->cartResponse($request, $store, $cart, 'Prodotto rimosso dal carrello.');
    }

    public function applyCoupon(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'max:80'],
        ]);

        $store = $this->resolveStore();
        $cart = $this->resolveCart($store);

        $result = $this->cartService->applyCoupon(
            cart: $cart,
            code: (string) $validated['coupon_code'],
            customer: null,
        );

        if (($result['valid'] ?? false) !== true) {
            $message = (string) ($result['message'] ?? 'Coupon non valido.');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'valid' => false,
                ], 422);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $message);
        }

        $updatedCart = $result['cart'] instanceof Cart
            ? $result['cart']
            : $cart->fresh(['items', 'customer', 'store', 'shippingAddress']);

        return $this->cartResponse(
            $request,
            $store,
            $updatedCart,
            (string) ($result['message'] ?? 'Coupon applicato correttamente.'),
            ['coupon' => $result['coupon'] ?? null]
        );
    }

    public function removeCoupon(Request $request): RedirectResponse|JsonResponse
    {
        $store = $this->resolveStore();
        $cart = $this->resolveCart($store);

        $cart = $this->cartService->removeCoupon($cart, null);

        return $this->cartResponse($request, $store, $cart, 'Coupon rimosso.');
    }

    protected function cartResponse(
        Request $request,
        Store $store,
        Cart $cart,
        string $message,
        array $extra = []
    ): RedirectResponse|JsonResponse {
        $cart = $cart->fresh(['items', 'customer', 'store', 'shippingAddress']);
        $decoratedItems = $this->decorateCartItems($cart, $store);
        $cart->setRelation('items', $decoratedItems);

        $this->queueCartCookie($cart);

        $cartCount = $this->resolveCartCountFromItems($decoratedItems);
        $calculatedTotals = $this->cartTotalsService->calculate($cart);

        if ($request->expectsJson()) {
            return response()->json(array_merge([
                'message' => $message,
                'valid' => true,
                'cart' => $cart,
                'items' => $decoratedItems,
                'cart_count' => $cartCount,
                'cart_total' => (float) ($calculatedTotals['grand_total'] ?? $cart->grand_total ?? 0),
                'totals' => $calculatedTotals,
                'promotions' => $calculatedTotals['promotions'] ?? [],
                'active_coupon_code' => $this->couponService->extractCouponCodeFromCart($cart),
            ], $extra));
        }

        return redirect()->back()->with('success', $message);
    }

    protected function quantityErrorResponse(Request $request, InvalidArgumentException $exception): RedirectResponse|JsonResponse
    {
        $message = trim($exception->getMessage()) !== ''
            ? $exception->getMessage()
            : 'Quantità non disponibile per questo prodotto.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'valid' => false,
            ], 422);
        }

        return redirect()->back()->withInput()->with('error', $message);
    }

    protected function resolveCart(Store $store): Cart
    {
        $cart = $this->cartService
            ->getOrCreate($store)
            ->fresh(['items', 'customer', 'store', 'shippingAddress']);

        $this->queueCartCookie($cart);

        return $cart;
    }

    protected function resolveStore(): Store
    {
        $store = app()->bound('currentStore') ? app('currentStore') : null;

        abort_unless($store instanceof Store, 404, 'Store corrente non disponibile.');

        return $store;
    }

    protected function guardCartItemStoreContext(CartItem $item, Store $store): void
    {
        $cart = $item->cart;

        abort_unless($cart instanceof Cart, 404);

        if ((int) $cart->ditta_cg18 !== (int) $store->ditta_cg18) {
            abort(404);
        }

        if ($cart->site_type !== null && (int) $cart->site_type !== (int) $store->erp_site_code) {
            abort(404);
        }

        $currentCart = $this->resolveCart($store);

        if ((int) $currentCart->id !== (int) $cart->id) {
            abort(404);
        }
    }

    protected function queueCartCookie(Cart $cart): void
    {
        cookie()->queue('cart_token', $cart->cart_token, 60 * 24 * 30);
    }

    protected function decorateCartItems(Cart $cart, Store $store): Collection
    {
        return $this->cartService->decorateItems(collect($cart->items ?? [])->values(), $store);
    }

    protected function resolveCartCount(Cart $cart): float
    {
        return $this->resolveCartCountFromItems(collect($cart->items ?? []));
    }

    protected function resolveCartCountFromItems(Collection $items): float
    {
        return $items->sum(fn ($item) => (float) ($item->quantity ?? 0));
    }
}
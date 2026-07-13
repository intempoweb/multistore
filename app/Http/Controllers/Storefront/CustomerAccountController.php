<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Storefront\Orders\OrderProductImagesZipService;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (session('agent_mode') === true && !$request->filled('agent_context')) {
            return redirect()->route('storefront.agent.customers');
        }

        $store = current_store();
        $customer = $this->customer();
        $recentOrders = collect();
        $orderStats = [];

        $ordersQuery = Order::query()
            ->forStore((int) $store->id)
            ->forCustomer((int) $customer->id)
            ->placed();

        $store->isB2B() ? $ordersQuery->b2b() : $ordersQuery->b2c();

        $recentOrders = (clone $ordersQuery)
            ->withCount('items')
            ->latest('placed_at')
            ->take(5)
            ->get();

        $orderStats = [
            'total' => (clone $ordersQuery)->count(),
            'processing' => (clone $ordersQuery)->whereIn('status', ['pending', 'processing'])->count(),
            'shipped' => (clone $ordersQuery)->whereNotNull('shipping_tracking_number')->count(),
        ];

        return view($this->themeResolver->view('account.index', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'customer' => $customer,
            'recentOrders' => $recentOrders,
            'orderStats' => $orderStats,
        ]);
    }

    public function orders(): View
    {
        $store = current_store();
        $ordersQuery = Order::query()
            ->forStore((int) $store->id)
            ->forCustomer((int) $this->customer()->id)
            ->placed();

        $store->isB2B() ? $ordersQuery->b2b() : $ordersQuery->b2c();

        $orders = $ordersQuery
            ->withCount('items')
            ->latest('placed_at')
            ->paginate(12);

        return view($this->themeResolver->view('account.orders.index', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'orders' => $orders,
        ]);
    }

    public function order(Order $order): View
    {
        $store = current_store();
        $customer = $this->customer();

        abort_unless(
            (int) $order->store_id === (int) $store->id
            && (int) $order->customer_id === (int) $customer->id
            && ($store->isB2B() ? $order->isB2b() : $order->isB2c()),
            404
        );

        $itemsDisplayLimit = $this->accountOrderItemsDisplayLimit();
        $order->loadCount('items');
        $items = $itemsDisplayLimit > 0
            ? $order->items()->limit($itemsDisplayLimit)->get()
            : $order->items()->get();
        $order->setRelation('items', $items);

        return view($this->themeResolver->view('account.orders.show', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'order' => $order,
            'itemsDisplayLimit' => $itemsDisplayLimit,
            'itemsTotalCount' => (int) $order->items_count,
            'productImagesDownload' => $store->isB2B()
                ? $this->productImagesDownloadPayload($order)
                : null,
        ]);
    }

    private function customer(): Customer
    {
        $customer = auth('customer')->user();
        abort_unless($customer instanceof Customer, 401);

        return $customer;
    }

    private function productImagesDownloadPayload(Order $order): ?array
    {
        $archive = $this->productImagesArchive($order);

        if ($archive === null) {
            return null;
        }

        $file = basename((string) $archive['path']);
        $expires = now()->addMinutes($this->productImagesDownloadTtlMinutes())->getTimestamp();
        $url = route('storefront.orders.product-images.download', [
            'order' => $order->order_number,
            'file' => $file,
            'expires' => $expires,
            'token' => $this->productImagesDownloadToken($order, $file, $expires),
        ], false);

        return [
            'url' => $url,
            'file' => $file,
            'size' => (int) $archive['size'],
            'size_label' => $this->formatBytes((int) $archive['size']),
        ];
    }

    private function productImagesArchive(Order $order): ?array
    {
        $archive = data_get($order->meta ?? [], 'mail.product_images_zip');

        if (!is_array($archive)) {
            $archive = app(OrderProductImagesZipService::class)->importLatestLegacyLocalArchive($order);

            if (!is_array($archive)) {
                return null;
            }
        }

        if (filled(data_get($archive, 'deleted_at'))) {
            return null;
        }

        $disk = trim((string) data_get($archive, 'disk', 's3')) ?: 's3';
        $path = ltrim((string) data_get($archive, 'path', ''), '/');
        $size = (int) data_get($archive, 'size', 0);
        $expiresAt = data_get($archive, 'expires_at');

        if ($path === '' || $size <= 0) {
            return null;
        }

        if ($expiresAt) {
            try {
                if (Carbon::parse($expiresAt)->isPast()) {
                    return null;
                }
            } catch (\Throwable) {
                return null;
            }
        }

        if (!Storage::disk($disk)->exists($path)) {
            return null;
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'size' => $size,
        ];
    }

    private function productImagesDownloadTtlMinutes(): int
    {
        return max(1, (int) config('mail.storefront.order_product_images.download_url_ttl_minutes', 10080));
    }

    private function accountOrderItemsDisplayLimit(): int
    {
        return max(0, (int) config('storefront.checkout.account_order_items_display_limit', 80));
    }

    private function productImagesDownloadToken(Order $order, string $file, int $expires): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [
                (string) $order->order_number,
                $file,
                (string) $expires,
            ]),
            (string) config('app.key')
        );
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }

        return number_format($bytes, 0, ',', '.') . ' B';
    }
}

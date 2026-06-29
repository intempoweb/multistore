<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $store = app('currentStore');
        $customer = $this->customer();
        $recentOrders = collect();
        $orderStats = [];

        $ordersQuery = Order::query()
            ->forStore((int) $store->id)
            ->forCustomer((int) $customer->id)
            ->placed();

        $store->is_b2b ? $ordersQuery->b2b() : $ordersQuery->b2c();

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
        $store = app('currentStore');
        $ordersQuery = Order::query()
            ->forStore((int) $store->id)
            ->forCustomer((int) $this->customer()->id)
            ->placed();

        $store->is_b2b ? $ordersQuery->b2b() : $ordersQuery->b2c();

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
        $store = app('currentStore');
        $customer = $this->customer();

        abort_unless(
            (int) $order->store_id === (int) $store->id
            && (int) $order->customer_id === (int) $customer->id
            && ($store->is_b2b ? $order->isB2b() : $order->isB2c()),
            404
        );

        $order->load('items');

        return view($this->themeResolver->view('account.orders.show', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'order' => $order,
            'productImagesDownload' => $store->is_b2b
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
        $file = $this->latestProductImagesZipFile($order);

        if ($file === null) {
            return null;
        }

        $expires = now()->addMinutes($this->productImagesDownloadTtlMinutes())->getTimestamp();
        $url = route('storefront.orders.product-images.download', [
            'order' => $order->order_number,
            'file' => $file['name'],
            'expires' => $expires,
            'token' => $this->productImagesDownloadToken($order, $file['name'], $expires),
        ], false);

        return [
            'url' => $url,
            'file' => $file['name'],
            'size' => $file['size'],
            'size_label' => $this->formatBytes((int) $file['size']),
        ];
    }

    private function latestProductImagesZipFile(Order $order): ?array
    {
        $pattern = storage_path('app/mail-attachments/orders/ordine-' . $this->safeOrderNumber($order) . '-prodotti-*.zip');
        $files = collect(glob($pattern) ?: [])
            ->filter(fn (string $path): bool => is_file($path) && is_readable($path))
            ->map(fn (string $path): array => [
                'path' => $path,
                'name' => basename($path),
                'size' => (int) filesize($path),
                'mtime' => (int) filemtime($path),
            ])
            ->sortByDesc('mtime')
            ->values();

        return $files->first();
    }

    private function productImagesDownloadTtlMinutes(): int
    {
        return max(1, (int) config('mail.storefront.order_product_images.download_url_ttl_minutes', 10080));
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

    private function safeOrderNumber(Order $order): string
    {
        $orderNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $order->order_number);
        $orderNumber = trim((string) $orderNumber, '-_');

        return $orderNumber !== '' ? $orderNumber : (string) $order->id;
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

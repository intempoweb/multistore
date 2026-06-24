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

        if (!$store->is_b2b) {
            $ordersQuery = Order::query()
                ->forStore((int) $store->id)
                ->forCustomer((int) $customer->id)
                ->b2c()
                ->placed();

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
        }

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
        abort_if($store->is_b2b, 404);

        $orders = Order::query()
            ->forStore((int) $store->id)
            ->forCustomer((int) $this->customer()->id)
            ->b2c()
            ->placed()
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
        abort_if($store->is_b2b, 404);
        abort_unless(
            (int) $order->store_id === (int) $store->id
            && (int) $order->customer_id === (int) $customer->id
            && $order->isB2c(),
            404
        );

        return view($this->themeResolver->view('account.orders.show', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'order' => $order->load('items'),
        ]);
    }

    private function customer(): Customer
    {
        $customer = auth('customer')->user();
        abort_unless($customer instanceof Customer, 401);

        return $customer;
    }
}

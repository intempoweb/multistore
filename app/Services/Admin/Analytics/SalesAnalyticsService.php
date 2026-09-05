<?php

namespace App\Services\Admin\Analytics;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SalesAnalyticsService
{
    public function dashboard(Store $store, AnalyticsDateRange $range): array
    {
        $currentCommercial = $this->commercialOrderQuery($store, $range->start, $range->end);
        $previousCommercial = $range->hasPreviousPeriod()
            ? $this->commercialOrderQuery($store, $range->previousStart, $range->previousEnd)
            : null;

        $commercialOrders = (int) (clone $currentCommercial)->count();
        $revenue = (float) (clone $currentCommercial)->sum('grand_total');
        $previousOrders = $previousCommercial ? (int) (clone $previousCommercial)->count() : 0;
        $previousRevenue = $previousCommercial ? (float) (clone $previousCommercial)->sum('grand_total') : 0.0;

        $quantitySold = $this->quantitySold($store, $range);
        $previousQuantitySold = $range->hasPreviousPeriod()
            ? $this->quantitySold($store, new AnalyticsDateRange('previous', $range->previousStart, $range->previousEnd))
            : 0.0;

        $customers = $this->customerStats($store, $range);
        $previousCustomers = $range->hasPreviousPeriod()
            ? $this->customerStats($store, new AnalyticsDateRange('previous', $range->previousStart, $range->previousEnd))
            : ['customers' => 0, 'new_customers' => 0, 'returning_customers' => 0];

        return [
            'range' => [
                'label' => $range->label(),
                'preset' => $range->preset,
                'date_from' => $range->start->toDateString(),
                'date_to' => $range->end->toDateString(),
                'granularity' => $range->chartGranularity(),
            ],
            'kpis' => [
                'revenue' => $this->kpi($revenue, $previousRevenue, 'currency'),
                'orders' => $this->kpi($commercialOrders, $previousOrders, 'integer'),
                'aov' => $this->kpi($commercialOrders > 0 ? $revenue / $commercialOrders : 0, $previousOrders > 0 ? $previousRevenue / $previousOrders : 0, 'currency'),
                'quantity_sold' => $this->kpi($quantitySold, $previousQuantitySold, 'decimal'),
                'customers' => $this->kpi($customers['customers'], $previousCustomers['customers'], 'integer'),
                'new_customers' => $this->kpi($customers['new_customers'], $previousCustomers['new_customers'], 'integer'),
                'returning_customers' => $this->kpi($customers['returning_customers'], $previousCustomers['returning_customers'], 'integer'),
                'paid_orders' => $this->kpi((int) (clone $this->baseOrderQuery($store, $range->start, $range->end))->where('payment_status', 'paid')->count(), null, 'integer'),
            ],
            'sales_chart' => $this->salesChart($store, $range),
            'order_statuses' => $this->breakdown($this->baseOrderQuery($store, $range->start, $range->end), 'status'),
            'payment_methods' => $this->breakdown($this->baseOrderQuery($store, $range->start, $range->end), 'payment_gateway', 'payment_method_label'),
            'top_products' => $this->topProducts($store, $range),
        ];
    }

    public function catalogStats(Store $store): array
    {
        $productsQuery = Product::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code);

        $simpleActiveProductsQuery = Product::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->simple()
            ->active();

        return [
            'products_total' => (clone $productsQuery)->count(),
            'products_simple' => (clone $productsQuery)->simple()->count(),
            'products_configurable' => (clone $productsQuery)->configurable()->count(),
            'products_active' => (clone $productsQuery)->active()->count(),
            'products_with_price' => (clone $productsQuery)->whereNotNull('public_price')->count(),
            'products_without_price' => (clone $productsQuery)->whereNull('public_price')->count(),
            'families_total' => (clone $simpleActiveProductsQuery)->whereNotNull('fam_99')->distinct()->count('fam_99'),
            'subfamilies_total' => (clone $simpleActiveProductsQuery)->whereNotNull('sfam_99')->distinct()->count('sfam_99'),
            'groups_total' => (clone $simpleActiveProductsQuery)->whereNotNull('gruppo_99')->distinct()->count('gruppo_99'),
            'subgroups_total' => (clone $simpleActiveProductsQuery)->whereNotNull('sgruppo_99')->distinct()->count('sgruppo_99'),
            'attributes_total' => Attribute::query()->count(),
            'attribute_values_total' => AttributeValue::query()->count(),
            'price_min' => (clone $productsQuery)->whereNotNull('public_price')->min('public_price'),
            'price_max' => (clone $productsQuery)->whereNotNull('public_price')->max('public_price'),
        ];
    }

    private function baseOrderQuery(Store $store, mixed $start, mixed $end): Builder
    {
        return Order::query()
            ->where('store_id', (int) $store->id)
            ->whereNotNull('placed_at')
            ->whereBetween('placed_at', [$start, $end]);
    }

    private function commercialOrderQuery(Store $store, mixed $start, mixed $end): Builder
    {
        return $this->baseOrderQuery($store, $start, $end)
            ->whereNotIn('status', ['canceled', 'cancelled'])
            ->whereNotIn('payment_status', ['refunded', 'canceled', 'cancelled']);
    }

    private function quantitySold(Store $store, AnalyticsDateRange $range): float
    {
        return (float) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', (int) $store->id)
            ->whereNotNull('orders.placed_at')
            ->whereBetween('orders.placed_at', [$range->start, $range->end])
            ->whereNotIn('orders.status', ['canceled', 'cancelled'])
            ->whereNotIn('orders.payment_status', ['refunded', 'canceled', 'cancelled'])
            ->whereNotNull('order_items.sku')
            ->sum('order_items.quantity');
    }

    private function customerStats(Store $store, AnalyticsDateRange $range): array
    {
        $identities = $this->orderCustomerIdentities($this->commercialOrderQuery($store, $range->start, $range->end));

        if ($identities->isEmpty()) {
            return [
                'customers' => 0,
                'new_customers' => 0,
                'returning_customers' => 0,
            ];
        }

        $returning = $this->orderCustomerIdentities(
            $this->commercialOrderQuery($store, '1970-01-01 00:00:00', $range->start->copy()->subSecond())
                ->where(function (Builder $query) use ($identities): void {
                    $query
                        ->whereIn('customer_id', $identities->filter(fn (string $identity) => str_starts_with($identity, 'customer:'))->map(fn (string $identity) => (int) substr($identity, 9))->values())
                        ->orWhereIn(DB::raw('LOWER(customer_email)'), $identities->filter(fn (string $identity) => str_starts_with($identity, 'email:'))->map(fn (string $identity) => substr($identity, 6))->values());
                })
        )->count();

        return [
            'customers' => $identities->count(),
            'new_customers' => max(0, $identities->count() - $returning),
            'returning_customers' => $returning,
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function orderCustomerIdentities(Builder $query): Collection
    {
        return $query
            ->get(['customer_id', 'customer_email'])
            ->map(function (Order $order): ?string {
                if ($order->customer_id !== null) {
                    return 'customer:'.(int) $order->customer_id;
                }

                $email = strtolower(trim((string) $order->customer_email));

                return $email !== '' ? 'email:'.$email : null;
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function salesChart(Store $store, AnalyticsDateRange $range): array
    {
        $dateExpression = $this->dateBucketExpression($range->chartGranularity());

        $rows = $this->commercialOrderQuery($store, $range->start, $range->end)
            ->selectRaw($dateExpression.' as bucket')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(grand_total) as revenue')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        return $this->bucketLabels($range)
            ->map(function (string $label) use ($rows): array {
                $row = $rows->get($label);
                $orders = (int) ($row->orders_count ?? 0);
                $revenue = (float) ($row->revenue ?? 0);

                return [
                    'label' => $label,
                    'orders' => $orders,
                    'revenue' => round($revenue, 2),
                    'aov' => $orders > 0 ? round($revenue / $orders, 2) : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function dateBucketExpression(string $granularity): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return match ($granularity) {
                'month' => "strftime('%Y-%m', placed_at)",
                'week' => "strftime('%Y-W%W', placed_at)",
                default => 'date(placed_at)',
            };
        }

        return match ($granularity) {
            'month' => "DATE_FORMAT(placed_at, '%Y-%m')",
            'week' => "DATE_FORMAT(placed_at, '%x-W%v')",
            default => 'DATE(placed_at)',
        };
    }

    /**
     * @return Collection<int, string>
     */
    private function bucketLabels(AnalyticsDateRange $range): Collection
    {
        $labels = collect();
        $cursor = $range->start->copy();

        if ($range->chartGranularity() === 'month') {
            while ($cursor->lessThanOrEqualTo($range->end)) {
                $labels->push($cursor->format('Y-m'));
                $cursor->addMonthNoOverflow()->startOfMonth();
            }

            return $labels;
        }

        if ($range->chartGranularity() === 'week') {
            while ($cursor->lessThanOrEqualTo($range->end)) {
                $labels->push($cursor->format('o-\WW'));
                $cursor->addWeek()->startOfWeek();
            }

            return $labels;
        }

        while ($cursor->lessThanOrEqualTo($range->end)) {
            $labels->push($cursor->toDateString());
            $cursor->addDay();
        }

        return $labels;
    }

    private function breakdown(Builder $query, string $column, ?string $fallbackColumn = null): array
    {
        return $query
            ->selectRaw($column.' as value')
            ->when($fallbackColumn, fn (Builder $builder) => $builder->selectRaw($fallbackColumn.' as fallback_value'))
            ->selectRaw('COUNT(*) as total')
            ->groupBy($column)
            ->when($fallbackColumn, fn (Builder $builder) => $builder->groupBy($fallbackColumn))
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'label' => filled($row->value) ? (string) $row->value : ((filled($row->fallback_value ?? null) ? (string) $row->fallback_value : 'N/D')),
                'total' => (int) $row->total,
            ])
            ->all();
    }

    private function topProducts(Store $store, AnalyticsDateRange $range, int $limit = 10): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.store_id', (int) $store->id)
            ->whereNotNull('orders.placed_at')
            ->whereBetween('orders.placed_at', [$range->start, $range->end])
            ->whereNotIn('orders.status', ['canceled', 'cancelled'])
            ->whereNotIn('orders.payment_status', ['refunded', 'canceled', 'cancelled'])
            ->whereNotNull('order_items.sku')
            ->selectRaw('order_items.product_id')
            ->selectRaw('order_items.sku')
            ->selectRaw('COALESCE(order_items.product_name, products.sku, order_items.sku) as product_name')
            ->selectRaw('products.fam_99')
            ->selectRaw('products.sfam_99')
            ->selectRaw('products.gruppo_99')
            ->selectRaw('products.marca_mg64')
            ->selectRaw('SUM(order_items.quantity) as quantity')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw('SUM(order_items.row_total) as revenue')
            ->groupBy('order_items.product_id', 'order_items.sku', 'order_items.product_name', 'products.sku', 'products.fam_99', 'products.sfam_99', 'products.gruppo_99', 'products.marca_mg64')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'product_id' => $row->product_id !== null ? (int) $row->product_id : null,
                'sku' => (string) $row->sku,
                'product_name' => (string) $row->product_name,
                'family' => $row->fam_99,
                'subfamily' => $row->sfam_99,
                'group' => $row->gruppo_99,
                'brand' => $row->marca_mg64,
                'quantity' => (float) $row->quantity,
                'orders' => (int) $row->orders_count,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    private function kpi(float|int $value, float|int|null $previousValue, string $format): array
    {
        return [
            'value' => $value,
            'previous_value' => $previousValue,
            'variation' => $previousValue !== null ? $this->variation($value, $previousValue) : null,
            'format' => $format,
        ];
    }

    private function variation(float|int $value, float|int $previousValue): ?float
    {
        if ((float) $previousValue === 0.0) {
            return (float) $value === 0.0 ? 0.0 : null;
        }

        return round((((float) $value - (float) $previousValue) / abs((float) $previousValue)) * 100, 1);
    }
}

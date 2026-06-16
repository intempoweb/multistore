<?php

namespace App\Services\Storefront\Wishlist;

use App\Models\Customer;
use App\Models\CustomerWishlistItem;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class WishlistService
{
    public function getItems(
        ?Customer $customer,
        Store $store
    ): Collection {
        $customer = $this->resolveCustomer($customer, $store);

        return CustomerWishlistItem::query()
            ->with([
                'product.translations',
                'product.mediaAssets',
            ])
            ->forCustomer((int) $customer->id)
            ->forContext(
                ditta: (int) $store->ditta_cg18,
                siteType: (int) $store->erp_site_code,
            )
            ->latest('id')
            ->get();
    }

    public function add(
        ?Customer $customer,
        Store $store,
        Product $product,
        array $meta = []
    ): CustomerWishlistItem {
        $customer = $this->resolveCustomer($customer, $store);

        /** @var CustomerWishlistItem|null $existing */
        $existing = CustomerWishlistItem::query()
            ->forCustomer((int) $customer->id)
            ->forContext(
                ditta: (int) $store->ditta_cg18,
                siteType: (int) $store->erp_site_code,
            )
            ->forSku((string) $product->sku)
            ->first();

        if ($existing instanceof CustomerWishlistItem) {
            return $existing;
        }

        /** @var CustomerWishlistItem $item */
        $item = CustomerWishlistItem::query()->create([
            'customer_id' => (int) $customer->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'ditta_cg18' => (int) $store->ditta_cg18,
            'site_type' => (int) $store->erp_site_code,
            'sku' => (string) $product->sku,
            'meta' => $meta,
        ]);

        return $item->fresh([
            'product.translations',
            'product.mediaAssets',
        ]);
    }

    public function remove(
        ?Customer $customer,
        Store $store,
        Product|string $product
    ): bool {
        $customer = $this->resolveCustomer($customer, $store);

        $sku = $product instanceof Product
            ? (string) $product->sku
            : trim((string) $product);

        return (bool) CustomerWishlistItem::query()
            ->forCustomer((int) $customer->id)
            ->forContext(
                ditta: (int) $store->ditta_cg18,
                siteType: (int) $store->erp_site_code,
            )
            ->forSku($sku)
            ->delete();
    }

    public function toggle(
        ?Customer $customer,
        Store $store,
        Product $product
    ): array {
        $customer = $this->resolveCustomer($customer, $store);

        /** @var CustomerWishlistItem|null $existing */
        $existing = CustomerWishlistItem::query()
            ->forCustomer((int) $customer->id)
            ->forContext(
                ditta: (int) $store->ditta_cg18,
                siteType: (int) $store->erp_site_code,
            )
            ->forSku((string) $product->sku)
            ->first();

        if ($existing instanceof CustomerWishlistItem) {
            $existing->delete();

            return [
                'added' => false,
                'item' => null,
            ];
        }

        $item = $this->add(
            customer: $customer,
            store: $store,
            product: $product,
        );

        return [
            'added' => true,
            'item' => $item,
        ];
    }

    public function has(
        ?Customer $customer,
        Store $store,
        Product|string $product
    ): bool {
        $customer = $this->resolveCustomer($customer, $store);

        $sku = $product instanceof Product
            ? (string) $product->sku
            : trim((string) $product);

        return CustomerWishlistItem::query()
            ->forCustomer((int) $customer->id)
            ->forContext(
                ditta: (int) $store->ditta_cg18,
                siteType: (int) $store->erp_site_code,
            )
            ->forSku($sku)
            ->exists();
    }

    public function count(
        ?Customer $customer,
        Store $store
    ): int {
        $customer = $this->resolveCustomer($customer, $store);

        return CustomerWishlistItem::query()
            ->forCustomer((int) $customer->id)
            ->forContext(
                ditta: (int) $store->ditta_cg18,
                siteType: (int) $store->erp_site_code,
            )
            ->count();
    }

    protected function resolveCustomer(?Customer $customer, Store $store): Customer
    {
        if ($customer instanceof Customer) {
            return $customer;
        }

        $contextId = (string) request()->query('agent_context', '');

        if ($contextId !== '' && session()->get('agent_mode') === true) {
            $context = session()->get("agent_contexts.$contextId");

            if (is_array($context) && !empty($context['customer_id'])) {
                $contextCustomer = Customer::query()
                    ->active()
                    ->webEnabled()
                    ->where('id', (int) $context['customer_id'])
                    ->where('ditta_cg18', (int) $store->ditta_cg18)
                    ->first();

                if ($contextCustomer instanceof Customer) {
                    return $contextCustomer;
                }
            }
        }

        $authCustomer = auth('customer')->user();

        if ($authCustomer instanceof Customer) {
            return $authCustomer;
        }

        throw new InvalidArgumentException('Cliente non disponibile per la wishlist.');
    }
}
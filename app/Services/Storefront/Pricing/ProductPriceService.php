<?php

namespace App\Services\Storefront\Pricing;

use App\Models\Customer;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;

class ProductPriceService
{
    private array $customerListinoCache = [];
    private array $priceBreaksCache = [];

    public function resolveForListing(
        mixed $store,
        Product $product,
        int|float $qty = 1,
        ?Customer $customer = null
    ): array {
        $resolvedQty = max(1, (float) $qty);
        $resolvedStore = $store instanceof Store
            ? $store
            : null;

        $publicPrice = $product->public_price !== null
            ? (float) $product->public_price
            : (
                $product->effective_price !== null
                    ? (float) $product->effective_price
                    : null
            );

        if (
            !$resolvedStore instanceof Store
            || $resolvedStore->isB2C()
        ) {
            return $this->buildPublicPriceResult($publicPrice);
        }

        $resolvedCustomer = $this->resolveCustomer(
            $customer,
            $resolvedStore
        );

        $ditta = (int) (
            $resolvedStore->ditta_cg18
            ?? $product->ditta_cg18
            ?? 0
        );

        $storeSiteType = (int) (
            $resolvedStore->erp_site_code
            ?? 0
        );

        $productSiteType = (int) (
            $product->site_type
            ?? 0
        );

        $sku = trim((string) ($product->sku ?? ''));

        if (
            $productSiteType > 0
            && $storeSiteType > 0
            && $productSiteType !== $storeSiteType
        ) {
            return $this->buildPublicPriceResult($publicPrice);
        }

        if (
            !$resolvedCustomer instanceof Customer
            || $ditta <= 0
            || $storeSiteType <= 0
            || $sku === ''
        ) {
            return $this->buildPublicPriceResult($publicPrice);
        }

        /*
        |--------------------------------------------------------------------------
        | 1. Listino commerciale richiesto dal cliente
        |--------------------------------------------------------------------------
        */
        $requestedListinoId = $this->resolveCustomerListinoId(
            ditta: $ditta,
            clifor: (int) ($resolvedCustomer->clifor_cg44 ?? 0),
            store: $resolvedStore,
            customer: $resolvedCustomer
        );

        if ($requestedListinoId === null) {
            return $this->buildPublicPriceResult($publicPrice);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Ricerca prezzo sul listino cliente
        |--------------------------------------------------------------------------
        */
        $customerPriceBreaks = $this->resolvePriceBreaks(
            $ditta,
            $requestedListinoId,
            $sku
        );

        $customerTier = $this->resolveTierFromBreaks(
            $customerPriceBreaks,
            $resolvedQty
        );

        if ($customerTier !== null) {
            return $this->buildTierResult(
                tierData: $customerTier,
                priceBreaks: $customerPriceBreaks,
                appliedListinoId: $requestedListinoId,
                requestedListinoId: $requestedListinoId,
                publicPrice: $publicPrice,
                fallbackUsed: false
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Fallback sul listino base LISTARTIC_TOT dello store
        |--------------------------------------------------------------------------
        |
        | Esempi:
        | - ditta 1, sito 1 => listino 31
        | - ditta 3, sito 1 => listino 1
        |
        | Il listino cliente resta quello richiesto, ma il prezzo viene preso
        | dal listino base quando lo SKU o lo scaglione non esiste nel listino
        | commerciale del cliente.
        |--------------------------------------------------------------------------
        */
        $fallbackListinoId = app(CustomerListinoResolver::class)
            ->defaultListinoForStore($resolvedStore);

        if (
            $fallbackListinoId !== null
            && $fallbackListinoId > 0
            && $fallbackListinoId !== $requestedListinoId
        ) {
            $fallbackPriceBreaks = $this->resolvePriceBreaks(
                $ditta,
                $fallbackListinoId,
                $sku
            );

            $fallbackTier = $this->resolveTierFromBreaks(
                $fallbackPriceBreaks,
                $resolvedQty
            );

            if ($fallbackTier !== null) {
                return $this->buildTierResult(
                    tierData: $fallbackTier,
                    priceBreaks: $fallbackPriceBreaks,
                    appliedListinoId: $fallbackListinoId,
                    requestedListinoId: $requestedListinoId,
                    publicPrice: $publicPrice,
                    fallbackUsed: true
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Ultimo fallback: prezzo pubblico/effective_price locale
        |--------------------------------------------------------------------------
        |
        | Il listino commerciale richiesto viene mantenuto nel payload per
        | tracciabilità, ma listino_id resta null perché nessun listino ERP
        | ha effettivamente fornito il prezzo.
        |--------------------------------------------------------------------------
        */
        return [
            'price' => $publicPrice,

            'price_payload' => [
                'price' => $publicPrice,
                'price_net' => $publicPrice,
                'price_gross' => null,

                // Nessun listino ERP ha fornito il prezzo.
                'listino_id' => null,

                // Listino commerciale assegnato al cliente.
                'requested_listino_id' => $requestedListinoId,

                'fallback_listino_id' => null,
                'fallback_used' => false,

                'qty_from' => null,
                'qty_to' => null,

                'sc1' => null,
                'sc2' => null,
                'sc3' => null,
                'sc4' => null,
                'sc5' => null,
                'sc6' => null,
            ],

            'price_breaks' => $customerPriceBreaks,
        ];
    }

    protected function resolveCustomer(
        ?Customer $customer,
        ?Store $store
    ): ?Customer {
        if ($customer instanceof Customer) {
            return $customer;
        }

        $contextId = (string) request()->query(
            'agent_context',
            ''
        );

        if (
            $contextId !== ''
            && session()->get('agent_mode') === true
        ) {
            $context = session()->get(
                "agent_contexts.$contextId"
            );

            if (
                is_array($context)
                && !empty($context['customer_id'])
            ) {
                $query = Customer::query()
                    ->active()
                    ->webEnabled()
                    ->where(
                        'id',
                        (int) $context['customer_id']
                    );

                if ($store instanceof Store) {
                    $query->where(
                        'ditta_cg18',
                        (int) $store->ditta_cg18
                    );
                }

                $contextCustomer = $query->first();

                if ($contextCustomer instanceof Customer) {
                    return $contextCustomer;
                }
            }
        }

        $authCustomer = auth('customer')->user();

        return $authCustomer instanceof Customer
            ? $authCustomer
            : null;
    }

    protected function resolveCustomerListinoId(
        int $ditta,
        int $clifor,
        Store $store,
        Customer $customer
    ): ?int {
        if ($ditta <= 0 || $clifor <= 0) {
            return null;
        }

        $cacheKey = implode('|', [
            $ditta,
            (int) ($store->erp_site_code ?? 0),
            $clifor,
            (int) ($customer->codlistinoded ?? 0),
        ]);

        if (
            array_key_exists(
                $cacheKey,
                $this->customerListinoCache
            )
        ) {
            return $this->customerListinoCache[$cacheKey];
        }

        $this->customerListinoCache[$cacheKey] =
            app(CustomerListinoResolver::class)
                ->resolveForCustomer(
                    $store,
                    $customer
                );

        return $this->customerListinoCache[$cacheKey];
    }

    protected function resolvePriceBreaks(
        int $ditta,
        int $listinoId,
        string $sku
    ): array {
        $sku = trim($sku);

        if (
            $ditta <= 0
            || $listinoId <= 0
            || $sku === ''
        ) {
            return [];
        }

        $cacheKey = implode('|', [
            $ditta,
            $listinoId,
            $sku,
        ]);

        if (
            array_key_exists(
                $cacheKey,
                $this->priceBreaksCache
            )
        ) {
            return $this->priceBreaksCache[$cacheKey];
        }

        /** @var Collection<int, PriceTier> $tiers */
        $tiers = PriceTier::query()
            ->forProduct(
                $ditta,
                $listinoId,
                $sku
            )
            ->orderBy('qty_from')
            ->get();

        $this->priceBreaksCache[$cacheKey] = $tiers
            ->map(
                function (PriceTier $tier) use ($listinoId): array {
                    $priceNet = $tier->price_net !== null
                        ? (float) $tier->price_net
                        : null;

                    return [
                        'qty_from' => $tier->qty_from !== null
                            ? (float) $tier->qty_from
                            : null,

                        'qty_to' => $this->normalizeQtyTo(
                            $tier->qty_to
                        ),

                        'price' => $priceNet,
                        'price_net' => $priceNet,
                        'listino_id' => $listinoId,

                        'sc1' => $tier->sc1 !== null
                            ? (float) $tier->sc1
                            : null,

                        'sc2' => $tier->sc2 !== null
                            ? (float) $tier->sc2
                            : null,

                        'sc3' => $tier->sc3 !== null
                            ? (float) $tier->sc3
                            : null,

                        'sc4' => $tier->sc4 !== null
                            ? (float) $tier->sc4
                            : null,

                        'sc5' => $tier->sc5 !== null
                            ? (float) $tier->sc5
                            : null,

                        'sc6' => $tier->sc6 !== null
                            ? (float) $tier->sc6
                            : null,
                    ];
                }
            )
            ->values()
            ->all();

        return $this->priceBreaksCache[$cacheKey];
    }
    protected function resolveTierFromBreaks(
        array $priceBreaks,
        float $qty
    ): ?array {
        foreach ($priceBreaks as $break) {
            $priceNet = array_key_exists('price_net', $break)
                && $break['price_net'] !== null
                    ? (float) $break['price_net']
                    : null;

            /*
            * Un prezzo nullo, zero o negativo non è applicabile.
            * La ricerca prosegue sul listino base o sul prezzo pubblico.
            */
            if ($priceNet === null || $priceNet <= 0) {
                continue;
            }

            $qtyFrom = isset($break['qty_from'])
                && $break['qty_from'] !== null
                    ? (float) $break['qty_from']
                    : 0.0;

            $qtyTo = isset($break['qty_to'])
                && $break['qty_to'] !== null
                    ? (float) $break['qty_to']
                    : null;

            if (
                $qty >= $qtyFrom
                && (
                    $qtyTo === null
                    || $qty <= $qtyTo
                )
            ) {
                return $break;
            }
        }

        return null;
    }

    protected function buildTierResult(
        array $tierData,
        array $priceBreaks,
        int $appliedListinoId,
        int $requestedListinoId,
        ?float $publicPrice,
        bool $fallbackUsed
    ): array {
        $tierPrice = array_key_exists('price_net', $tierData)
            && $tierData['price_net'] !== null
                ? (float) $tierData['price_net']
                : null;

        $resolvedPrice = $tierPrice ?? $publicPrice;

        return [
            'price' => $resolvedPrice,

            'price_payload' => [
                'price' => $resolvedPrice,
                'price_net' => $resolvedPrice,
                'price_gross' => null,

                // Listino dal quale è stato realmente preso il prezzo.
                'listino_id' => $appliedListinoId,

                // Listino commerciale assegnato al cliente.
                'requested_listino_id' => $requestedListinoId,

                // Listino base usato in fallback.
                'fallback_listino_id' => $fallbackUsed
                    ? $appliedListinoId
                    : null,

                'fallback_used' => $fallbackUsed,

                'qty_from' => $tierData['qty_from'] ?? null,
                'qty_to' => $tierData['qty_to'] ?? null,

                'sc1' => $tierData['sc1'] ?? null,
                'sc2' => $tierData['sc2'] ?? null,
                'sc3' => $tierData['sc3'] ?? null,
                'sc4' => $tierData['sc4'] ?? null,
                'sc5' => $tierData['sc5'] ?? null,
                'sc6' => $tierData['sc6'] ?? null,
            ],

            'price_breaks' => $priceBreaks,
        ];
    }

    protected function buildPublicPriceResult(
        ?float $publicPrice
    ): array {
        return [
            'price' => $publicPrice,

            'price_payload' => [
                'price' => $publicPrice,
                'price_net' => $publicPrice,
                'price_gross' => null,

                'listino_id' => null,
                'requested_listino_id' => null,
                'fallback_listino_id' => null,
                'fallback_used' => false,

                'qty_from' => null,
                'qty_to' => null,

                'sc1' => null,
                'sc2' => null,
                'sc3' => null,
                'sc4' => null,
                'sc5' => null,
                'sc6' => null,
            ],

            'price_breaks' => [],
        ];
    }

    protected function normalizeQtyTo(
        mixed $value
    ): ?float {
        if ($value === null) {
            return null;
        }

        $resolved = (float) $value;

        if (
            $resolved <= 0
            || $resolved >= 99999999
        ) {
            return null;
        }

        return $resolved;
    }
}
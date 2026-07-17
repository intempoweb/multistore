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

        /*
        |--------------------------------------------------------------------------
        | Prezzo pubblico locale
        |--------------------------------------------------------------------------
        |
        | public_price ed effective_price sono considerati validi soltanto
        | quando maggiori di zero.
        |--------------------------------------------------------------------------
        */
        $publicPrice = $this->resolvePublicPrice($product);

        if (
            !($resolvedStore instanceof Store)
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

        /*
        |--------------------------------------------------------------------------
        | Il prodotto deve appartenere allo stesso sito ERP dello store
        |--------------------------------------------------------------------------
        */
        if (
            $productSiteType > 0
            && $storeSiteType > 0
            && $productSiteType !== $storeSiteType
        ) {
            return $this->buildPublicPriceResult($publicPrice);
        }

        /*
        |--------------------------------------------------------------------------
        | Dati minimi richiesti per il pricing B2B
        |--------------------------------------------------------------------------
        */
        if (
            !($resolvedCustomer instanceof Customer)
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
        | 2. Ricerca del prezzo nel listino cliente
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
        | Il listino cliente rimane quello richiesto, ma il prezzo può essere
        | prelevato dal listino base se lo SKU o lo scaglione non è disponibile
        | nel listino commerciale del cliente.
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
                    fallbackUsed: true
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Ultimo fallback: prezzo pubblico/effective_price locale
        |--------------------------------------------------------------------------
        |
        | Nessun listino ERP ha fornito un prezzo valido. Il listino commerciale
        | assegnato al cliente viene mantenuto per tracciabilità.
        |
        | Se anche il prezzo locale non è disponibile, price sarà null.
        |--------------------------------------------------------------------------
        */
        return $this->buildLocalFallbackResult(
            publicPrice: $publicPrice,
            requestedListinoId: $requestedListinoId,
            customerPriceBreaks: $customerPriceBreaks
        );
    }

    /**
     * Restituisce il primo prezzo locale valido.
     *
     * effective_price può essere un accessor Eloquent e non necessariamente
     * una colonna fisica della tabella products.
     */
    protected function resolvePublicPrice(Product $product): ?float
    {
        $candidates = [
            $product->public_price,
            $product->effective_price,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || !is_numeric($candidate)) {
                continue;
            }

            $price = (float) $candidate;

            if ($price > 0) {
                return $price;
            }
        }

        return null;
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

                        /*
                         * Il prezzo viene mantenuto nel payload anche se pari
                         * a zero, per consentire la diagnostica dei dati ERP.
                         * Sarà resolveTierFromBreaks() a stabilire se è valido.
                         */
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

    /**
     * Trova lo scaglione applicabile alla quantità richiesta.
     *
     * Un prezzo ERP nullo, zero o negativo non è applicabile e viene
     * ignorato, permettendo il fallback sul listino base o sul prezzo locale.
     */
    protected function resolveTierFromBreaks(
        array $priceBreaks,
        float $qty
    ): ?array {
        foreach ($priceBreaks as $break) {
            $priceNet = array_key_exists('price_net', $break)
                && $break['price_net'] !== null
                    ? (float) $break['price_net']
                    : null;

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
        bool $fallbackUsed
    ): array {
        $tierPrice = array_key_exists('price_net', $tierData)
            && $tierData['price_net'] !== null
                ? (float) $tierData['price_net']
                : null;

        /*
         * Controllo difensivo: buildTierResult() dovrebbe essere richiamato
         * esclusivamente con uno scaglione già validato.
         */
        $resolvedPrice = $tierPrice !== null && $tierPrice > 0
            ? $tierPrice
            : null;

        return [
            'price' => $resolvedPrice,

            'price_payload' => [
                'price' => $resolvedPrice,
                'price_net' => $resolvedPrice,
                'price_gross' => null,

                // Listino dal quale è stato realmente prelevato il prezzo.
                'listino_id' => $appliedListinoId,

                // Listino commerciale assegnato al cliente.
                'requested_listino_id' => $requestedListinoId,

                // Listino base utilizzato come fallback.
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

    /**
     * Risultato utilizzato quando il cliente ha un listino assegnato, ma
     * nessun listino ERP ha fornito un prezzo valido.
     */
    protected function buildLocalFallbackResult(
        ?float $publicPrice,
        int $requestedListinoId,
        array $customerPriceBreaks
    ): array {
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

                /*
                 * Il listino base potrebbe essere stato interrogato, ma non
                 * ha fornito un prezzo valido; quindi non risulta applicato.
                 */
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

            /*
             * Mantiene gli scaglioni del listino cliente per diagnostica,
             * inclusi gli eventuali prezzi ERP pari a zero.
             */
            'price_breaks' => $customerPriceBreaks,
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
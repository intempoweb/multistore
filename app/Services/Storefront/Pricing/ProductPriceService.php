<?php

namespace App\Services\Storefront\Pricing;

use App\Models\Customer;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductPriceService
{
    public function resolveForListing(
        mixed $store,
        Product $product,
        int|float $qty = 1,
        ?Customer $customer = null
    ): array {
        $resolvedQty = max(1, (float) $qty);
        $resolvedStore = $store instanceof Store ? $store : null;

        $publicPrice = $product->public_price !== null
            ? (float) $product->public_price
            : ($product->effective_price !== null ? (float) $product->effective_price : null);

        if (!$resolvedStore instanceof Store || !$resolvedStore->is_b2b) {
            return [
                'price' => $publicPrice,
                'price_payload' => [
                    'price' => $publicPrice,
                    'price_net' => $publicPrice,
                    'price_gross' => null,
                    'listino_id' => null,
                ],
                'price_breaks' => [],
            ];
        }

        $resolvedCustomer = $customer instanceof Customer
            ? $customer
            : auth('customer')->user();

        $ditta = (int) ($resolvedStore->ditta_cg18 ?? $product->ditta_cg18 ?? 0);
        $sku = trim((string) ($product->sku ?? ''));

        if ($resolvedCustomer instanceof Customer && $ditta > 0 && $sku !== '') {
            $listinoId = $this->resolveCustomerListinoId(
                ditta: $ditta,
                clifor: (int) ($resolvedCustomer->clifor_cg44 ?? 0)
            );

            if ($listinoId !== null) {
                $tier = PriceTier::query()
                    ->forProduct($ditta, $listinoId, $sku)
                    ->forQuantity($resolvedQty)
                    ->first();

                $priceBreaks = $this->resolvePriceBreaks($ditta, $listinoId, $sku);

                if ($tier instanceof PriceTier) {
                    $tierPrice = $tier->price_net !== null ? (float) $tier->price_net : null;

                    return [
                        'price' => $tierPrice ?? $publicPrice,
                        'price_payload' => [
                            'price' => $tierPrice ?? $publicPrice,
                            'price_net' => $tierPrice ?? $publicPrice,
                            'price_gross' => null,
                            'listino_id' => $listinoId,
                            'qty_from' => $tier->qty_from !== null ? (float) $tier->qty_from : null,
                            'qty_to' => $this->normalizeQtyTo($tier->qty_to),
                            'sc1' => $tier->sc1 !== null ? (float) $tier->sc1 : null,
                            'sc2' => $tier->sc2 !== null ? (float) $tier->sc2 : null,
                            'sc3' => $tier->sc3 !== null ? (float) $tier->sc3 : null,
                            'sc4' => $tier->sc4 !== null ? (float) $tier->sc4 : null,
                            'sc5' => $tier->sc5 !== null ? (float) $tier->sc5 : null,
                            'sc6' => $tier->sc6 !== null ? (float) $tier->sc6 : null,
                        ],
                        'price_breaks' => $priceBreaks,
                    ];
                }

                return [
                    'price' => $publicPrice,
                    'price_payload' => [
                        'price' => $publicPrice,
                        'price_net' => $publicPrice,
                        'price_gross' => null,
                        'listino_id' => $listinoId,
                    ],
                    'price_breaks' => $priceBreaks,
                ];
            }
        }

        return [
            'price' => $publicPrice,
            'price_payload' => [
                'price' => $publicPrice,
                'price_net' => $publicPrice,
                'price_gross' => null,
                'listino_id' => null,
            ],
            'price_breaks' => [],
        ];
    }

    protected function resolveCustomerListinoId(int $ditta, int $clifor): ?int
    {
        if ($ditta <= 0 || $clifor <= 0) {
            return null;
        }

        $listinoId = DB::table('customer_listino_assignments')
            ->where('ditta_cg18', $ditta)
            ->where('clifor_cg44', $clifor)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('listino_id');

        return $listinoId !== null ? (int) $listinoId : null;
    }

    protected function resolvePriceBreaks(int $ditta, int $listinoId, string $sku): array
    {
        /** @var Collection<int, PriceTier> $tiers */
        $tiers = PriceTier::query()
            ->forProduct($ditta, $listinoId, $sku)
            ->orderBy('qty_from')
            ->get();

        return $tiers->map(function (PriceTier $tier) use ($listinoId) {
            $priceNet = $tier->price_net !== null ? (float) $tier->price_net : null;

            return [
                'qty_from' => $tier->qty_from !== null ? (float) $tier->qty_from : null,
                'qty_to' => $this->normalizeQtyTo($tier->qty_to),
                'price' => $priceNet,
                'price_net' => $priceNet,
                'listino_id' => $listinoId,
                'sc1' => $tier->sc1 !== null ? (float) $tier->sc1 : null,
                'sc2' => $tier->sc2 !== null ? (float) $tier->sc2 : null,
                'sc3' => $tier->sc3 !== null ? (float) $tier->sc3 : null,
                'sc4' => $tier->sc4 !== null ? (float) $tier->sc4 : null,
                'sc5' => $tier->sc5 !== null ? (float) $tier->sc5 : null,
                'sc6' => $tier->sc6 !== null ? (float) $tier->sc6 : null,
            ];
        })->values()->all();
    }

    protected function normalizeQtyTo(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $resolved = (float) $value;

        if ($resolved <= 0 || $resolved >= 99999999) {
            return null;
        }

        return $resolved;
    }
}
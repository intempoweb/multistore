<?php

namespace App\Services\Storefront;

use App\Models\Cart;

use App\Models\Order;

use App\Models\ShippingRule;

use App\Models\Store;

use App\Services\Storefront\InventoryStockService;

use App\Services\Storefront\Totals\CartTotalsService;

use Illuminate\Support\Facades\DB;

use InvalidArgumentException;

class CheckoutService

{

    public function __construct(

        protected CartTotalsService $totalsService,

        protected InventoryStockService $inventoryStockService,

    ) {

    }

    public function checkout(Cart $cart): Order

    {

        return $this->placeOrder($cart);

    }

    public function placeOrder(Cart $cart): Order

    {

        $cart->loadMissing([

            'items',

            'store',

            'customer',

            'shippingAddress',

        ]);

        /** @var Store|null $store */

        $store = $cart->store;

        if (!$store instanceof Store) {

            throw new InvalidArgumentException('Store non associato al carrello.');

        }

        if (collect($cart->items ?? [])->isEmpty()) {

            throw new InvalidArgumentException('Impossibile creare un ordine da un carrello vuoto.');

        }

        $cart = $this->totalsService->recalculate($cart);

        $cart->loadMissing([
            'items',
            'store',
            'customer',
            'shippingAddress',
        ]);

        $existingOrder = Order::query()

            ->where('cart_id', $cart->id)

            ->whereNotNull('placed_at')

            ->latest('id')

            ->first();

        if ($existingOrder instanceof Order) {

            if ($cart->status !== 'ordered') {

                $cart->forceFill([

                    'status' => 'ordered',

                ])->save();

            }

            return $existingOrder->fresh([

                'items',

                'store',

                'customer',

                'shippingAddress',

            ]);

        }

        return DB::transaction(function () use ($cart, $store) {

            $isB2b = (bool) $store->is_b2b;

            $country = $this->resolveShippingCountry($cart);

            $totals = $this->totalsService->calculate($cart);

            $shippingData = is_array($totals['shipping'] ?? null)

                ? $totals['shipping']

                : [];

            $shippingRule = $this->resolveShippingRuleFromTotals($shippingData);

            $customer = $cart->customer;

            $shippingAddress = $cart->shippingAddress;

            $invoiceRequired = $this->resolveInvoiceRequired($cart, $isB2b);

            $requiresErpExport = $this->requiresErpExport($isB2b, $invoiceRequired);

            $billingNameParts = $this->splitFullName($cart->customer_name);

            $shippingNameParts = $this->splitFullName($cart->shipping_name);

            $billingCompany = $customer?->ragsoanag_cg16;

            $billingFirstName = $customer?->nomeconnweb ?? $billingNameParts['first_name'];

            $billingLastName = $customer?->cognomeconnweb ?? $billingNameParts['last_name'];

            $billingAddressLine1 = $customer?->indirizzo_cg16;

            $billingPostcode = $customer?->cap_cg16;

            $billingCity = $customer?->citta_cg16;

            $billingProvince = $customer?->prov_cg16;

            $billingCountry = $this->normalizeCountry($customer?->statoestero_cg16) ?? 'ITA';

            $billingPhone = $customer?->tel1num_cg16 ?? $customer?->cellnum_cg16 ?? null;

            $billingEmail = $customer?->indemail_cg16 ?? $cart->customer_email;

            $customerVatNumber = $customer?->partiva_cg16 ?? null;

            $customerTaxCode = $customer?->codfiscale_cg16 ?? null;

            $shippingCompany = $cart->shipping_name;

            $shippingContactName = $shippingAddress?->destragsoc_mg22 ?? $cart->shipping_name;

            $shippingFirstName = $shippingNameParts['first_name'];

            $shippingLastName = $shippingNameParts['last_name'];

            $shippingPhone = $shippingAddress?->desttel_mg22

                ?? $shippingAddress?->destcell_mg22

                ?? null;

            $shippingEmail = $shippingAddress?->destemail_mg22

                ?? $cart->customer_email;

            if (!$isB2b) {

                $billingSameAsShipping = filter_var(

                    $this->checkoutMeta($cart, 'billing_same_as_shipping'),

                    FILTER_VALIDATE_BOOLEAN

                );

                $billingCompany = $this->checkoutMeta($cart, 'billing_company');

                $billingFirstName = $this->checkoutMeta($cart, 'billing_first_name')

                    ?? $billingNameParts['first_name'];

                $billingLastName = $this->checkoutMeta($cart, 'billing_last_name')

                    ?? $billingNameParts['last_name'];

                $billingAddressLine1 = $this->checkoutMeta($cart, 'billing_address_line_1');

                $billingPostcode = $this->checkoutMeta($cart, 'billing_postcode');

                $billingCity = $this->checkoutMeta($cart, 'billing_city');

                $billingProvince = $this->checkoutMeta($cart, 'billing_province');

                $billingCountry = $this->normalizeCountry(

                    $this->checkoutMeta($cart, 'billing_country')

                ) ?? $country ?? 'ITA';

                $billingPhone = $this->checkoutMeta($cart, 'billing_phone')

                    ?? $this->checkoutMeta($cart, 'shipping_phone');

                $billingEmail = $this->checkoutMeta($cart, 'billing_email')

                    ?? $cart->customer_email;

                $customerVatNumber = $this->checkoutMeta($cart, 'billing_vat_number');

                $customerTaxCode = $this->checkoutMeta($cart, 'billing_tax_code');

                if ($billingSameAsShipping || !$invoiceRequired) {

                    $billingFirstName = $billingFirstName

                        ?? $shippingNameParts['first_name'];

                    $billingLastName = $billingLastName

                        ?? $shippingNameParts['last_name'];

                    $billingAddressLine1 = $billingAddressLine1

                        ?? $cart->shipping_address;

                    $billingPostcode = $billingPostcode

                        ?? $cart->shipping_zip;

                    $billingCity = $billingCity

                        ?? $cart->shipping_city;

                    $billingProvince = $billingProvince

                        ?? $cart->shipping_province;

                    $billingCountry = $this->normalizeCountry($billingCountry)

                        ?? $country

                        ?? 'ITA';

                    $billingEmail = $billingEmail

                        ?? $cart->customer_email;

                }

                if ($invoiceRequired && $billingCompany === null) {

                    $billingCompany = trim(implode(' ', array_filter([

                        $billingFirstName,

                        $billingLastName,

                    ]))) ?: null;

                }

                $shippingCompany = $cart->shipping_name;

                $shippingContactName = $cart->shipping_name;

                $shippingPhone = $this->checkoutMeta($cart, 'shipping_phone');

                $shippingEmail = $this->checkoutMeta($cart, 'shipping_email')

                    ?? $cart->customer_email;

            }

            /** @var Order $order */

            $order = Order::query()->create([

                'store_id' => $cart->store_id,

                'channel' => $isB2b ? 'b2b' : 'b2c',

                'ditta_cg18' => (int) $cart->ditta_cg18,

                'site_type' => $cart->site_type !== null

                    ? (int) $cart->site_type

                    : null,

                'cart_id' => $cart->id,

                'customer_id' => $cart->customer_id,

                'customer_tipocf_cg44' => $customer?->tipocf_cg44 !== null

                    ? (int) $customer->tipocf_cg44

                    : null,

                'customer_clifor_cg44' => $cart->customer_clifor_cg44 !== null

                    ? (int) $cart->customer_clifor_cg44

                    : null,

                'order_number' => $this->generateOrderNumber($store),

                'legacy_magento_order_number' => null,

                'erp_web_id' => null,

                'erp_web_numreg' => null,

                'erp_document_number' => null,

                'erp_document_visible_number' => null,

                'status' => $isB2b ? 'pending' : 'processing',

                'payment_status' => $isB2b ? 'not_required' : 'pending',

                'fulfillment_status' => 'pending',

                'source' => 'storefront',

                'currency' => (string) ($cart->currency ?? 'EUR'),

                'invoice_required' => $invoiceRequired,

                'invoice_status' => $invoiceRequired

                    ? 'required'

                    : 'not_required',

                'erp_export_status' => $requiresErpExport

                    ? 'pending'

                    : 'skipped',

                'erp_export_error' => null,

                'customer_code' => $cart->customer_clifor_cg44 !== null

                    ? (string) $cart->customer_clifor_cg44

                    : null,

                'customer_company_name' => $billingCompany ?: $cart->customer_name,

                'customer_name' => $cart->customer_name,

                'customer_email' => $cart->customer_email,

                'customer_phone' => $billingPhone,

                'customer_vat_number' => $customerVatNumber,

                'customer_tax_code' => $customerTaxCode,

                'billing_company' => $billingCompany,

                'billing_first_name' => $billingFirstName,

                'billing_last_name' => $billingLastName,

                'billing_address_line_1' => $billingAddressLine1,

                'billing_address_line_2' => null,

                'billing_postcode' => $billingPostcode,

                'billing_city' => $billingCity,

                'billing_province' => $billingProvince,

                'billing_country_code' => $billingCountry,

                'billing_phone' => $billingPhone,

                'billing_email' => $billingEmail,

                'customer_shipping_address_id' => $cart->shipping_address_id,

                'shipping_address_code' => $shippingAddress?->coddestin_mg22 !== null

                    ? (int) $shippingAddress->coddestin_mg22

                    : null,

                'shipping_company' => $shippingCompany,

                'shipping_contact_name' => $shippingContactName,

                'shipping_first_name' => $shippingFirstName,

                'shipping_last_name' => $shippingLastName,

                'shipping_address_line_1' => $cart->shipping_address,

                'shipping_address_line_2' => null,

                'shipping_postcode' => $cart->shipping_zip,

                'shipping_city' => $cart->shipping_city,

                'shipping_province' => $cart->shipping_province,

                'shipping_country_code' => $country,

                'shipping_phone' => $shippingPhone,

                'shipping_email' => $shippingEmail,

                'payment_method_code' => $this->resolvePaymentMethodCode($cart, $isB2b),

                'payment_method_label' => $this->resolvePaymentMethodLabel($cart, $isB2b),

                'payment_terms_code' => $customer?->codpag_cg62 !== null

                    ? (int) $customer->codpag_cg62

                    : null,

                'payment_terms_label' => $customer?->descrpag_cg62 ?? null,

                'payment_vat_percent' => null,

                'bank_abi' => $customer?->ccabi_mg35 !== null

                    ? (int) $customer->ccabi_mg35

                    : null,

                'bank_cab' => $customer?->cccab_mg35 !== null

                    ? (int) $customer->cccab_mg35

                    : null,

                'bank_name' => $customer?->desbanca_cg12_cg13 ?? null,

                'bank_iban' => $customer?->iban_mg35 ?? null,

                'payment_gateway' => $isB2b

                    ? null

                    : $this->checkoutMeta($cart, 'payment_gateway'),

                'payment_transaction_id' => $isB2b

                    ? null

                    : $this->checkoutMeta($cart, 'payment_transaction_id'),

                'paid_at' => null,

                'shipping_method_code' => $shippingRule?->type ?? null,

                'shipping_method_label' => $this->resolveShippingMethodLabel(

                    $shippingRule?->type ?? null

                ),

                'shipping_gateway' => $isB2b ? null : 'sendcloud',

                'shipping_carrier' => $isB2b

                    ? null

                    : $this->resolveB2cCarrier($cart, $shippingData),

                'shipping_service_code' => $isB2b

                    ? null

                    : $this->checkoutMeta($cart, 'shipping_service_code'),

                'shipping_tracking_number' => null,

                'shipping_label_url' => null,

                'shipping_label_created_at' => null,

                'coupon_code' => $this->resolveCouponCodeFromTotals($totals),

                'subtotal' => $this->formatAmount($totals['subtotal'] ?? 0),

                'discount_total' => $this->formatAmount($totals['discount_total'] ?? 0),

                'shipping_total' => $this->formatAmount($totals['shipping_total'] ?? 0),

                'tax_total' => $this->formatAmount($totals['tax_total'] ?? 0),

                'grand_total' => $this->formatAmount($totals['grand_total'] ?? 0),

                'customer_notes' => $cart->notes,

                'internal_notes' => null,

                'meta' => [

                    'erp_export' => [

                        'required' => $requiresErpExport,

                        'reason' => $this->resolveErpExportReason(

                            $isB2b,

                            $invoiceRequired

                        ),

                        'source_cart_id' => $cart->id,

                    ],

                    'checkout' => [

                        'invoice_required' => $invoiceRequired,

                        'billing_same_as_shipping' => !$isB2b

                            ? filter_var(

                                $this->checkoutMeta($cart, 'billing_same_as_shipping'),

                                FILTER_VALIDATE_BOOLEAN

                            )

                            : null,

                    ],

                    'promotions' => is_array(data_get($totals, 'promotions'))
                        ? data_get($totals, 'promotions')
                        : [],

                ],

                'erp_exported_at' => null,

                'erp_synced_at' => null,

                'placed_at' => now(),

            ]);

            foreach ($cart->items as $index => $item) {

                $quantity = (float) ($item->quantity ?? 0);

                $price = $item->price !== null

                    ? (float) $item->price

                    : null;

                $priceNet = $item->price_net !== null

                    ? (float) $item->price_net

                    : null;

                $priceGross = $item->price_gross !== null

                    ? (float) $item->price_gross

                    : null;

                $rowSubtotal = $item->row_subtotal !== null

                    ? (float) $item->row_subtotal

                    : ($quantity * (float) ($price ?? $priceNet ?? 0));

                $rowTotal = $item->row_total !== null

                    ? (float) $item->row_total

                    : $rowSubtotal;

                $rowDiscountTotal = $item->row_discount_total !== null

                    ? max(0, (float) $item->row_discount_total)

                    : max(0, $rowSubtotal - $rowTotal);

                $rowTaxTotal = $item->row_tax_total !== null

                    ? (float) $item->row_tax_total

                    : 0.0;

                $rowNumber = (int) ($index + 1);

                $order->items()->create([

                    'ditta_cg18' => (int) (

                        $item->ditta_cg18

                        ?? $cart->ditta_cg18

                    ),

                    'site_type' => $cart->site_type !== null

                        ? (int) $cart->site_type

                        : null,

                    'erp_web_row_id' => null,

                    'erp_web_numreg' => null,

                    'erp_web_row_number' => $rowNumber,

                    'erp_row_type' => 0,

                    'product_id' => $item->product_id ?? null,

                    'sku' => (string) $item->sku,

                    'product_name' => $item->product_name,

                    'product_description' => $item->product_description,

                    'product_thumbnail_url' => $item->product_thumbnail_url,

                    'variant_attributes' => $this->extractVariantAttributes($item),

                    'quantity' => $this->formatQuantity($quantity),

                    'min_qty' => $this->nullableFormattedQuantity(

                        $item->min_order_qty_snapshot

                        ?? $item->quantity_min

                        ?? null

                    ),

                    'step_qty' => $this->nullableFormattedQuantity(

                        $item->quantity_step_snapshot

                        ?? $item->quantity_step

                        ?? null

                    ),

                    'price_source' => $this->resolvePriceSource($store, $item),

                    'price' => $this->nullableFormattedPrice($price, $isB2b),

                    'price_net' => $this->nullableFormattedPrice(

                        $priceNet,

                        $isB2b

                    ),

                    'price_gross' => $this->nullableFormattedPrice(

                        $priceGross,

                        $isB2b

                    ),

                    'erp_price' => $this->nullableFormattedPrice(

                        $priceNet ?? $price,

                        $isB2b

                    ),

                    'erp_price_tax' => null,

                    'erp_price_gross' => $this->nullableFormattedPrice(

                        $priceGross,

                        $isB2b

                    ),

                    'listino_id' => $item->listino_id !== null

                        ? (int) $item->listino_id

                        : null,

                    'qty_from' => $this->nullableFormattedQuantity(

                        $item->qty_from ?? null

                    ),

                    'qty_to' => $this->nullableFormattedQuantity(

                        $item->qty_to ?? null

                    ),

                    'sc1' => $this->nullableFormattedDiscount($item->sc1 ?? null),

                    'sc2' => $this->nullableFormattedDiscount($item->sc2 ?? null),

                    'sc3' => $this->nullableFormattedDiscount($item->sc3 ?? null),

                    'sc4' => $this->nullableFormattedDiscount($item->sc4 ?? null),

                    'sc5' => $this->nullableFormattedDiscount($item->sc5 ?? null),

                    'sc6' => $this->nullableFormattedDiscount($item->sc6 ?? null),

                    'tax_percent' => $item->tax_percent !== null

                        ? $this->nullableFormattedDiscount($item->tax_percent)

                        : null,

                    'tax_code' => $this->nullableString($item->tax_code ?? null),

                    'tax_label' => $this->nullableString($item->tax_label ?? null),

                    'row_subtotal' => $this->formatAmount($rowSubtotal),

                    'row_discount_total' => $this->formatAmount($rowDiscountTotal),

                    'row_tax_total' => $this->formatAmount($rowTaxTotal),

                    'row_total' => $this->formatAmount($rowTotal),

                    'erp_row_subtotal' => $this->formatAmount($rowSubtotal),

                    'erp_row_tax_total' => $this->formatAmount($rowTaxTotal),

                    'erp_row_net_total' => $this->formatAmount(

                        $this->isCouponDiscountItem($item)
                            ? $rowTotal
                            : ($rowSubtotal - $rowDiscountTotal)

                    ),

                    'erp_row_cash_total' => $this->formatCashAmount(

                        $rowTotal,

                        $isB2b

                    ),

                    'price_payload' => [

                        'price' => $item->price,

                        'price_net' => $item->price_net,

                        'price_gross' => $item->price_gross,

                    ],

                    'stock_qty' => $item->stock_qty ?? null,

                    'no_backorder' => $item->no_backorder ?? null,

                    'meta' => [

                        'cart_item_id' => $item->id,

                        'shipping_rule_id' => $shippingRule?->id,

                        'quantity_min' => $item->quantity_min ?? null,

                        'quantity_step' => $item->quantity_step ?? null,

                        'pack_multiple' => $item->pack_multiple ?? null,

                        'erp_export' => [

                            'required' => $requiresErpExport,

                            'row_number' => $rowNumber,

                        ],

                    ],

                ]);

            }

            $this->createShippingOrderItem(

                order: $order,

                cart: $cart,

                shippingRule: $shippingRule,

                shippingTotal: (float) ($totals['shipping_total'] ?? 0),

                requiresErpExport: $requiresErpExport,

                rowNumber: (int) ($order->items()->count() + 1)

            );

            $stockStats = $this->inventoryStockService->confirmOrderStock(

                $order->fresh(['items'])

            );

            $order->forceFill([

                'meta' => array_merge($order->meta ?? [], [

                    'stock_confirmation' => [

                        'confirmed_at' => now()->toISOString(),

                        'stats' => $stockStats,

                    ],

                ]),

            ])->save();

            $cart->forceFill([

                'status' => 'ordered',

            ])->save();

            return $order->fresh([

                'items',

                'store',

                'customer',

                'shippingAddress',

            ]);

        });

    }

    protected function checkoutMeta(Cart $cart, string $key): ?string

    {

        $meta = $cart->meta ?? [];

        if (is_string($meta)) {

            $meta = json_decode($meta, true) ?: [];

        }

        $value = data_get(

            is_array($meta) ? $meta : [],

            'checkout.' . $key

        );

        return $this->nullableString($value);

    }

    protected function resolveCouponCodeFromTotals(array $totals): ?string

    {

        $coupons = data_get(

            $totals,

            'promotions.applied_coupons',

            []

        );

        if (!is_array($coupons) || empty($coupons)) {

            return null;

        }

        $code = $coupons[0]['code'] ?? null;

        return is_string($code) && trim($code) !== ''

            ? trim($code)

            : null;

    }

    protected function resolveShippingRuleFromTotals(

        array $shippingData

    ): ?ShippingRule {

        $ruleId = $shippingData['rule_id'] ?? null;

        return $ruleId !== null

            ? ShippingRule::query()->find((int) $ruleId)

            : null;

    }

    protected function resolveShippingCountry(Cart $cart): ?string

    {

        return $this->normalizeCountry(

            $cart->shipping_country

            ?? $cart->shippingAddress?->statoest_cg07

            ?? $cart->customer?->statoestero_cg16

            ?? 'ITA'

        );

    }

    protected function normalizeCountry(mixed $country): ?string

    {

        $country = strtoupper(trim((string) $country));

        return match ($country) {

            '' => null,

            'IT' => 'ITA',

            default => $country,

        };

    }

    protected function resolveShippingMethodLabel(?string $type): ?string

    {

        return match ($type) {

            'fixed' => 'Spedizione fissa',

            'free_over' => 'Porto franco',

            'table' => 'Tabella spedizione',

            default => null,

        };

    }

    protected function resolvePriceSource(Store $store, mixed $item): string
    {
        if ($this->isCouponDiscountItem($item)) {
            return 'coupon_discount';
        }
        return $store->is_b2b && !empty($item->listino_id)
            ? 'b2b_tier'
            : 'public';
    }

    protected function isCouponDiscountItem(mixed $item): bool
    {
        $sku = strtoupper(trim((string) ($item->sku ?? '')));
        return str_starts_with($sku, 'MTBUONO');
    }

    protected function resolveInvoiceRequired(

        Cart $cart,

        bool $isB2b

    ): bool {

        if ($isB2b) {

            return false;

        }

        return filter_var(

            $this->checkoutMeta($cart, 'billing_request_invoice'),

            FILTER_VALIDATE_BOOLEAN

        );

    }

    protected function requiresErpExport(

        bool $isB2b,

        bool $invoiceRequired

    ): bool {

        return $isB2b || $invoiceRequired;

    }

    protected function resolvePaymentMethodCode(

        Cart $cart,

        bool $isB2b

    ): ?string {

        if ($isB2b) {

            return $cart->customer?->codpag_cg62 !== null

                ? trim((string) $cart->customer->codpag_cg62)

                : null;

        }

        return $this->checkoutMeta($cart, 'payment_method_code')

            ?? $this->checkoutMeta($cart, 'payment_gateway');

    }

    protected function resolvePaymentMethodLabel(

        Cart $cart,

        bool $isB2b

    ): ?string {

        if ($isB2b) {

            return $cart->customer?->descrpag_cg62 !== null

                ? trim((string) $cart->customer->descrpag_cg62)

                : null;

        }

        return $this->checkoutMeta($cart, 'payment_method_label')

            ?? $this->checkoutMeta($cart, 'payment_gateway');

    }

    protected function resolveB2cCarrier(

        Cart $cart,

        array $shippingData

    ): ?string {

        $carrier = $this->checkoutMeta($cart, 'shipping_carrier')

            ?? $this->nullableString($shippingData['carrier'] ?? null);

        if ($carrier === null) {

            return null;

        }

        return match (strtolower($carrier)) {

            'brt', 'bartolini' => 'brt',

            'dhl', 'dhl express', 'dhlexpress' => 'dhl',

            default => strtolower($carrier),

        };

    }

    protected function extractVariantAttributes($item): ?array

    {

        if (

            !empty($item->variant_attributes)

            && is_array($item->variant_attributes)

        ) {

            return $item->variant_attributes;

        }

        $attributes = [];

        if (!empty($item->color)) {

            $attributes['color'] = $item->color;

        }

        if (!empty($item->format)) {

            $attributes['format'] = $item->format;

        }

        return !empty($attributes)

            ? $attributes

            : null;

    }

    protected function createShippingOrderItem(

        Order $order,

        Cart $cart,

        ?ShippingRule $shippingRule,

        float $shippingTotal,

        bool $requiresErpExport,

        int $rowNumber

    ): void {

        if ($shippingRule === null && $shippingTotal <= 0) {

            return;

        }

        $description = $shippingTotal > 0

            ? 'Spesa Spedizione'

            : $this->resolveFreeShippingRowDescription($shippingRule);

        $order->items()->create([

            'ditta_cg18' => (int) $cart->ditta_cg18,

            'site_type' => $cart->site_type !== null

                ? (int) $cart->site_type

                : null,

            'erp_web_row_id' => null,

            'erp_web_numreg' => null,

            'erp_web_row_number' => $rowNumber,

            'erp_row_type' => 3,

            'product_id' => null,

            'sku' => null,

            'product_name' => $description,

            'product_description' => $description,

            'product_thumbnail_url' => null,

            'variant_attributes' => null,

            'quantity' => $this->formatQuantity(1),

            'price_source' => 'shipping',

            'price' => $this->formatPrice(

                $shippingTotal,

                $order->isB2b()

            ),

            'price_net' => $this->formatPrice(

                $shippingTotal,

                $order->isB2b()

            ),

            'price_gross' => $this->formatPrice(

                $shippingTotal,

                $order->isB2b()

            ),

            'row_subtotal' => $this->formatAmount($shippingTotal),

            'row_discount_total' => $this->formatAmount(0),

            'row_tax_total' => $this->formatAmount(0),

            'row_total' => $this->formatAmount($shippingTotal),

            'meta' => [

                'shipping_rule_id' => $shippingRule?->id,

                'erp_export' => [

                    'required' => $requiresErpExport,

                    'row_number' => $rowNumber,

                    'row_type' => 3,

                ],

            ],

        ]);

    }

    protected function resolveFreeShippingRowDescription(

        ?ShippingRule $shippingRule

    ): string {

        if (

            $shippingRule instanceof ShippingRule

            && $shippingRule->type === 'free_over'

        ) {

            return 'Costo Spedizione - Porto Franco';

        }

        return 'Spesa Spedizione';

    }

    protected function generateOrderNumber(Store $store): string

    {

        $prefix = $this->resolveOrderNumberPrefix($store);

        do {

            $orderNumber = $prefix

                . now()->format('ymdHis')

                . str_pad(

                    (string) random_int(0, 9999),

                    4,

                    '0',

                    STR_PAD_LEFT

                );

        } while (

            Order::query()

                ->where('order_number', $orderNumber)

                ->exists()

        );

        return $orderNumber;

    }

    protected function resolveOrderNumberPrefix(Store $store): string

    {

        return (string) max(1, (int) $store->ditta_cg18)

            . (string) max(1, (int) $store->erp_site_code);

    }

    protected function formatAmount(float|int|string $value): string

    {

        return number_format((float) $value, 3, '.', '');

    }

    protected function formatPrice(

        float|int|string $value,

        bool $isB2b

    ): string {

        return number_format(

            (float) $value,

            $isB2b ? 3 : 2,

            '.',

            ''

        );

    }

    protected function formatCashAmount(

        float|int|string $value,

        bool $isB2b

    ): string {

        return number_format(

            (float) $value,

            $isB2b ? 3 : 2,

            '.',

            ''

        );

    }

    protected function formatQuantity(float|int|string $value): string

    {

        return number_format((float) $value, 3, '.', '');

    }

    protected function nullableFormattedAmount(

        float|int|string|null $value

    ): ?string {

        return $value !== null

            ? $this->formatAmount($value)

            : null;

    }

    protected function nullableFormattedPrice(

        float|int|string|null $value,

        bool $isB2b

    ): ?string {

        return $value !== null

            ? $this->formatPrice($value, $isB2b)

            : null;

    }

    protected function nullableFormattedQuantity(

        float|int|string|null $value

    ): ?string {

        return $value !== null

            ? $this->formatQuantity($value)

            : null;

    }

    protected function nullableFormattedDiscount(

        float|int|string|null $value

    ): ?string {

        return $value !== null

            ? number_format((float) $value, 3, '.', '')

            : null;

    }

    protected function nullableString(mixed $value): ?string

    {

        $value = trim((string) $value);

        return $value !== ''

            ? $value

            : null;

    }

    protected function splitFullName(?string $fullName): array

    {

        $fullName = trim((string) $fullName);

        if ($fullName === '') {

            return [

                'first_name' => null,

                'last_name' => null,

            ];

        }

        $parts = preg_split('/\s+/', $fullName) ?: [];

        if (count($parts) === 1) {

            return [

                'first_name' => $parts[0],

                'last_name' => null,

            ];

        }

        $firstName = array_shift($parts);

        return [

            'first_name' => $firstName ?: null,

            'last_name' => !empty($parts)

                ? implode(' ', $parts)

                : null,

        ];

    }
    protected function resolveErpExportReason(bool $isB2b, bool $invoiceRequired): string
    {
        if ($isB2b) {
            return 'b2b_order';
        }

        if ($invoiceRequired) {
            return 'b2c_invoice_required';
        }

        return 'b2c_no_invoice';
    }

}
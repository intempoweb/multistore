<?php

namespace App\Http\Controllers\Storefront;


use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerShippingAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRule;
use App\Models\Store;
use App\Mail\Storefront\Orders\OrderInternalNotificationMail;
use App\Mail\Storefront\Orders\OrderStatusMail;
use App\Services\Erp\OrderExportService;
use App\Services\Payments\PaymentService;
use App\Services\Storefront\Cart\CartService;
use App\Services\Storefront\CheckoutService;
use App\Services\Storefront\Mail\StorefrontMailService;
use App\Services\Storefront\Promotion\CouponService;
use App\Services\Storefront\ThemeResolver;
use App\Services\Storefront\Totals\CartTotalsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CartTotalsService $cartTotalsService,
        private CheckoutService $checkoutService,
        private ThemeResolver $themeResolver,
        private CouponService $couponService,
        private PaymentService $paymentService,
        private OrderExportService $orderExportService,
    ) {
    }

    public function show(Request $request): View|JsonResponse|RedirectResponse
    {
        $store = $this->resolveStore();
        $customer = $this->resolveCustomer($store);

        if ($this->mustAuthenticateForCheckout($store, $customer)) {
            return $this->redirectToLoginForB2b($request);
        }

        $cart = $this->cartService
            ->getOrCreate($store, $customer)
            ->fresh(['items', 'customer', 'store', 'shippingAddress']);

        if (!$cart || $cart->items->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Carrello vuoto.',
                    'data' => $this->emptyCheckoutData($cart),
                ], 422);
            }

            return redirect()
                ->route('storefront.cart.index')
                ->with('error', 'Il carrello è vuoto.');
        }

        $decoratedItems = $this->decorateCartItems($cart, $store);
        $cart->setRelation('items', $decoratedItems);

        $shippingAddresses = $this->resolveShippingAddresses($customer, $store);
        $selectedShippingAddressId = $this->resolveSelectedShippingAddressId($request, $cart, $shippingAddresses);

        $countryCatalog = !$store->is_b2b ? $this->loadCountryCatalog() : collect();
        $availableCountries = !$store->is_b2b
            ? $this->resolveAvailableB2cCountries($store, $countryCatalog)
            : collect();

        $previewCart = $cart;

        if (!$store->is_b2b) {
            $previewCart = $this->applyGuestCheckoutPreviewData($request, clone $cart, $availableCountries);
            $previewCart->setRelation('items', $decoratedItems);
        }

        $billing = $this->buildBillingData($customer, $previewCart);
        $bank = $this->buildBankData($customer);
        $calculatedTotals = $this->cartTotalsService->calculate($previewCart);

        $b2cCheckout = !$store->is_b2b
            ? $this->buildB2cCheckoutData($request, $previewCart, $availableCountries)
            : [];

        $checkoutSummary = $this->buildCheckoutSummaryData($previewCart, $calculatedTotals);
        $shippingDetails = is_array($calculatedTotals['shipping'] ?? null) ? $calculatedTotals['shipping'] : [];
        $promotions = is_array($calculatedTotals['promotions'] ?? null) ? $calculatedTotals['promotions'] : [];

        $viewData = [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'locale' => app()->getLocale(),
            'customer' => $customer,
            'cart' => $previewCart,
            'items' => $decoratedItems,

            'isB2b' => (bool) $store->is_b2b,
            'isGuestCheckout' => !$store->is_b2b,

            'shippingAddresses' => $shippingAddresses,
            'selectedShippingAddressId' => $selectedShippingAddressId,
            'shippingSelectionStorageKey' => 'checkout_shipping_address_' . ($cart?->id ?? $cart?->cart_token ?? 'current'),

            'billing' => $billing,
            'bank' => $bank,
            'hasBillingData' => $this->hasFilledValues($billing),
            'hasBankData' => $this->hasFilledValues($bank),

            'shippingCost' => (float) ($calculatedTotals['shipping_total'] ?? 0),
            'shippingDetails' => $shippingDetails,
            'shippingAvailable' => (bool) ($shippingDetails['available'] ?? false),
            'shippingMessage' => trim((string) ($shippingDetails['message'] ?? '')),
            'shippingIsFree' => (bool) ($shippingDetails['is_free'] ?? false),
            'shippingTotal' => (float) ($calculatedTotals['shipping_total'] ?? 0),

            'cartTotals' => $calculatedTotals,
            'subtotal' => (float) ($calculatedTotals['subtotal'] ?? 0),
            'discountTotal' => (float) ($calculatedTotals['discount_total'] ?? 0),
            'grandTotal' => max(0, (float) ($calculatedTotals['grand_total'] ?? 0)),

            'promotions' => $promotions,
            'appliedCoupons' => collect($promotions['applied_coupons'] ?? []),
            'appliedPromotions' => collect($promotions['applied_promotions'] ?? []),
            'activeCouponCode' => $this->couponService->extractCouponCodeFromCart($cart),
            'displayCouponCode' => $this->resolveDisplayCouponCode($cart, $promotions),

            'b2cCheckout' => $b2cCheckout,
            'b2cShipping' => is_array($b2cCheckout['shipping'] ?? null) ? $b2cCheckout['shipping'] : [],
            'b2cBilling' => is_array($b2cCheckout['billing'] ?? null) ? $b2cCheckout['billing'] : [],
            'availableCountries' => collect($b2cCheckout['available_countries'] ?? []),
            'requestInvoice' => (bool) old('billing_request_invoice', data_get($b2cCheckout, 'billing.request_invoice', false)),
            'billingSameAsShipping' => (bool) old('billing_same_as_shipping', data_get($b2cCheckout, 'billing.same_as_shipping', true)),
            'selectedPaymentGateway' => $this->resolveSelectedPaymentGateway($cart),

            'checkoutSummary' => $checkoutSummary,
            'paymentConfig' => [
                'stripe_key' => config('services.stripe.key'),
                'paypal_client_id' => config('services.paypal.client_id'),
                'currency' => strtoupper((string) config('services.paypal.currency', config('services.stripe.currency', 'eur'))),
                'paypal_intent' => 'authorize',
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $viewData]);
        }

        return view($this->themeResolver->view('checkout.index', $store), $viewData);
    }

    public function success(Order $order): View
    {
        $store = $this->resolveStore();

        abort_unless((int) $order->store_id === (int) $store->id, 404);

        return view($this->themeResolver->view('checkout.success', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'locale' => app()->getLocale(),
            'order' => $order->load('items'),
        ]);
    }

    public function paymentPreview(Request $request): JsonResponse|RedirectResponse
    {
        $store = $this->resolveStore();
        $customer = $this->resolveCustomer($store);

        if ($store->is_b2b) {
            return response()->json([
                'message' => 'Anteprima pagamento disponibile solo per checkout B2C.',
            ], 422);
        }

        $countryCatalog = $this->loadCountryCatalog();
        $availableCountries = $this->resolveAvailableB2cCountries($store, $countryCatalog);

        $rules = $this->b2cValidationRules($request, $availableCountries);
        $validated = $request->validate($rules);

        $cart = $this->cartService
            ->getOrCreate($store, $customer)
            ->fresh(['items', 'customer', 'store', 'shippingAddress']);

        if (!$cart || !$cart->isActive()) {
            return response()->json(['message' => 'Carrello non attivo.'], 422);
        }

        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Carrello vuoto.'], 422);
        }

        try {
            $previewCart = $this->applyGuestCheckoutData($cart, $validated, $availableCountries);

            $previewCart = $this->cartService->setNotes($previewCart, $validated['notes'] ?? null);

            $previewCart = $this->cartService->recalculate(
                $previewCart->fresh(['items', 'customer', 'store', 'shippingAddress']),
                $customer
            );

            $totals = $this->cartTotalsService->calculate($previewCart);
            $shipping = is_array($totals['shipping'] ?? null) ? $totals['shipping'] : [];

            if (!((bool) ($shipping['available'] ?? false))) {
                return response()->json([
                    'message' => trim((string) ($shipping['message'] ?? 'Spedizione non disponibile.')),
                    'data' => [
                        'checkout_summary' => $this->buildCheckoutSummaryData($previewCart, $totals),
                    ],
                ], 422);
            }

            $gateway = $this->normalizeGateway($validated['payment_gateway'] ?? null);
            $payment = $this->paymentService->createPaymentPreview($gateway, $previewCart, $totals);

            return response()->json([
                'message' => 'Pagamento inizializzato.',
                'data' => [
                    'payment_gateway' => $gateway,
                    'payment' => $payment,

                    'client_secret' => $gateway === 'stripe' ? ($payment['client_secret'] ?? null) : null,
                    'payment_client_secret' => $gateway === 'stripe' ? ($payment['client_secret'] ?? null) : null,
                    'stripe_key' => config('services.stripe.key'),

                    'paypal_order_id' => $gateway === 'paypal' ? ($payment['id'] ?? null) : null,
                    'paypal_intent' => $gateway === 'paypal' ? 'authorize' : null,

                    'checkout_summary' => $this->buildCheckoutSummaryData($previewCart, $totals),
                    'totals' => [
                        'subtotal' => $totals['subtotal'] ?? 0,
                        'discount_total' => $totals['discount_total'] ?? 0,
                        'shipping_total' => $totals['shipping_total'] ?? 0,
                        'tax_total' => $totals['tax_total'] ?? 0,
                        'grand_total' => $totals['grand_total'] ?? 0,
                    ],
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Impossibile inizializzare il pagamento.'], 500);
        }
    }

    public function placeOrder(Request $request): RedirectResponse|JsonResponse
    {
        $store = $this->resolveStore();
        $customer = $this->resolveCustomer($store);

        if ($this->mustAuthenticateForCheckout($store, $customer)) {
            return $this->redirectToLoginForB2b($request);
        }

        $countryCatalog = !$store->is_b2b ? $this->loadCountryCatalog() : collect();
        $availableCountries = !$store->is_b2b
            ? $this->resolveAvailableB2cCountries($store, $countryCatalog)
            : collect();

        $rules = $store->is_b2b
            ? [
                'shipping_address_id' => ['required', 'integer'],
                'notes' => ['nullable', 'string', 'max:5000'],
            ]
            : $this->b2cValidationRules($request, $availableCountries, true);

        $validated = $request->validate($rules);

        $cart = $this->cartService
            ->getOrCreate($store, $customer)
            ->fresh(['items', 'customer', 'store', 'shippingAddress']);

        if (!$cart || !$cart->isActive()) {
            return $this->handleException($request, 'Carrello non attivo.', 422);
        }

        if ($cart->items->isEmpty()) {
            return $this->handleException($request, 'Carrello vuoto.', 422);
        }

        try {
            if ($store->is_b2b) {
                $shippingAddress = $this->resolveRequestedShippingAddress(
                    $customer,
                    $store,
                    (int) $validated['shipping_address_id']
                );

                $cart = $this->cartService->assignShippingAddress($cart, $shippingAddress);
            } else {
                $cart = $this->applyGuestCheckoutData($cart, $validated, $availableCountries);
            }

            $cart = $this->cartService->setNotes($cart, $validated['notes'] ?? null);

            $cart = $this->cartService->recalculate(
                $cart->fresh(['items', 'customer', 'store', 'shippingAddress']),
                $customer
            );

            $decoratedItems = $this->decorateCartItems($cart, $store);
            $cart->setRelation('items', $decoratedItems);

            if (!$store->is_b2b) {
                $this->assertB2cPaymentAuthorized($validated);
            }

            $order = $this->checkoutService->placeOrder($cart);

            if (!$store->is_b2b) {
                $this->markOrderPaymentAuthorizedFromCheckout($order, $validated);
                $this->markB2cOrderAwaitingBoApproval($order->fresh());
                $this->syncLocalB2cCustomerData($customer, $validated);
            }

            $order = $order->fresh(['store', 'customer', 'items']);
            $this->exportOrderToErpIfRequired($order);
            $order = $order->fresh(['store', 'customer', 'items']);
            $this->sendOrderCreatedEmails($order);
        } catch (InvalidArgumentException $exception) {
            return $this->handleException($request, $exception->getMessage(), 422);
        } catch (Throwable $exception) {
            report($exception);

            $message = app()->hasDebugModeEnabled()
                ? 'Impossibile completare il checkout: ' . $exception->getMessage()
                : 'Impossibile completare il checkout.';

            return $this->handleException($request, $message, 500);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Ordine creato correttamente.',
                'data' => [
                    'order' => $order->load('items'),
                    'payment_gateway' => $validated['payment_gateway'] ?? null,
                    'payment_status' => $order->payment_status,
                    'status' => $order->status,
                    'fulfillment_status' => $order->fulfillment_status,
                    'redirect_url' => $this->contextRoute('storefront.checkout.success', $order->order_number),
                    'totals' => $this->orderTotalsPayload($order),
                ],
            ]);
        }

        return redirect()
            ->to($this->contextRoute('storefront.checkout.success', $order->order_number))
            ->with('success', 'Ordine creato correttamente. Numero ordine: ' . $order->order_number);
    }

    private function syncLocalB2cCustomerData(?Customer $customer, array $data): void
    {
        if (!$customer instanceof Customer || !$customer->isLocalStorefrontAccount()) {
            return;
        }

        $billingSameAsShipping = (bool) ($data['billing_same_as_shipping'] ?? true);
        $billing = $billingSameAsShipping
            ? [
                'first_name' => $data['shipping_first_name'] ?? null,
                'last_name' => $data['shipping_last_name'] ?? null,
                'email' => $data['shipping_email'] ?? null,
                'address' => $data['shipping_address_line_1'] ?? null,
                'postcode' => $data['shipping_postcode'] ?? null,
                'city' => $data['shipping_city'] ?? null,
                'province' => $data['shipping_province'] ?? null,
            ]
            : [
                'first_name' => $data['billing_first_name'] ?? null,
                'last_name' => $data['billing_last_name'] ?? null,
                'email' => $data['billing_email'] ?? null,
                'address' => $data['billing_address_line_1'] ?? null,
                'postcode' => $data['billing_postcode'] ?? null,
                'city' => $data['billing_city'] ?? null,
                'province' => $data['billing_province'] ?? null,
            ];
        $billingName = trim(implode(' ', array_filter([$billing['first_name'], $billing['last_name']])));
        $billingCompany = trim((string) ($data['billing_company'] ?? ''));

        $customer->forceFill([
            'nomeconnweb' => $data['shipping_first_name'] ?? $customer->nomeconnweb,
            'cognomeconnweb' => $data['shipping_last_name'] ?? $customer->cognomeconnweb,
            'tel1num_cg16' => $data['shipping_phone'] ?? $customer->tel1num_cg16,
            'indircor_cg16' => $data['shipping_address_line_1'] ?? $customer->indircor_cg16,
            'capcor_cg16' => $data['shipping_postcode'] ?? $customer->capcor_cg16,
            'cittacor_cg16' => $data['shipping_city'] ?? $customer->cittacor_cg16,
            'provcor_cg16' => $data['shipping_province'] ?? $customer->provcor_cg16,
            'ragsoanag_cg16' => $billingCompany !== ''
                ? $billingCompany
                : ($billingName !== '' ? $billingName : $customer->ragsoanag_cg16),
            'indemailperfatt_cg16' => $billing['email'] ?? $customer->indemailperfatt_cg16,
            'indirizzo_cg16' => $billing['address'] ?? $customer->indirizzo_cg16,
            'cap_cg16' => $billing['postcode'] ?? $customer->cap_cg16,
            'citta_cg16' => $billing['city'] ?? $customer->citta_cg16,
            'prov_cg16' => $billing['province'] ?? $customer->prov_cg16,
            'partiva_cg16' => $data['billing_vat_number'] ?? $customer->partiva_cg16,
            'codfiscale_cg16' => $data['billing_tax_code'] ?? $customer->codfiscale_cg16,
            'email_pec_cg16' => $data['billing_pec'] ?? $customer->email_pec_cg16,
        ])->save();
    }

    private function sendOrderCreatedEmails(Order $order): void
    {
        $this->sendCustomerOrderCreatedEmail($order);
        $this->sendInternalOrderCreatedEmail($order);
    }

    private function sendCustomerOrderCreatedEmail(Order $order): void
    {
        $to = $this->customerOrderEmail($order);

        if ($to === null) {
            return;
        }

        try {
            Mail::to($to)->send(new OrderStatusMail($order, 'created'));

            $this->storeOrderMailSuccess($order, 'created_customer');
        } catch (Throwable $exception) {
            report($exception);

            $this->storeOrderMailError($order, 'created_customer', $exception->getMessage());
        }
    }

    private function sendInternalOrderCreatedEmail(Order $order): void
    {
        $to = $this->internalOrderEmail($order);

        if ($to === null) {
            return;
        }

        try {
            Mail::to($to)->send(new OrderInternalNotificationMail($order, 'created'));

            $this->storeOrderMailSuccess($order, 'created_internal');
        } catch (Throwable $exception) {
            report($exception);

            $this->storeOrderMailError($order, 'created_internal', $exception->getMessage());
        }
    }

    private function customerOrderEmail(Order $order): ?string
    {
        $email = trim((string) ($order->customer_email ?: $order->shipping_email ?: $order->billing_email));

        return $email !== '' ? $email : null;
    }

    private function internalOrderEmail(Order $order): ?string
    {
        $store = $order->store;

        if (!$store instanceof Store) {
            return null;
        }

        $config = app(StorefrontMailService::class)->configForStore($store);
        $email = trim((string) ($config['to_address'] ?? $config['orders_to_address'] ?? $config['admin_to_address'] ?? ''));

        return $email !== '' ? $email : null;
    }


    private function exportOrderToErpIfRequired(Order $order): void
    {
        if (!$order->requiresErpExport() || $order->isExportedToErp()) {
            return;
        }

        try {
            $this->orderExportService->export($order);
        } catch (Throwable $exception) {
            report($exception);

            $order->forceFill([
                'erp_export_status' => 'failed',
                'erp_export_error' => mb_substr($exception->getMessage(), 0, 65535),
            ])->save();
        }
    }

    private function storeOrderMailSuccess(Order $order, string $operation): void
    {
        try {
            $meta = $order->meta ?? [];

            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            }

            $meta = is_array($meta) ? $meta : [];

            $meta['mail'] = array_merge($meta['mail'] ?? [], [
                $operation . '_sent_at' => now()->toISOString(),
                $operation . '_error' => null,
                $operation . '_failed_at' => null,
            ]);

            $order->forceFill(['meta' => $meta])->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function storeOrderMailError(Order $order, string $operation, string $message): void
    {
        try {
            $meta = $order->meta ?? [];

            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            }

            $meta = is_array($meta) ? $meta : [];

            $meta['mail'] = array_merge($meta['mail'] ?? [], [
                $operation . '_error' => $message,
                $operation . '_failed_at' => now()->toISOString(),
            ]);

            $order->forceFill(['meta' => $meta])->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function b2cValidationRules(Request $request, Collection $availableCountries, bool $requirePaymentToken = false): array
    {
        $countryCodes = $availableCountries->pluck('code')->filter()->values()->all();

        $shippingCountryRules = ['required', 'string', 'size:3'];
        $billingCountryRules = ['nullable', 'string', 'size:3'];

        if (!empty($countryCodes)) {
            $shippingCountryRules[] = 'in:' . implode(',', $countryCodes);
            $billingCountryRules[] = 'in:' . implode(',', $countryCodes);
        }

        $requestInvoice = $request->boolean('billing_request_invoice');
        $billingSameAsShipping = $request->boolean('billing_same_as_shipping', true);
        $requiresBillingAddress = $requestInvoice && !$billingSameAsShipping;

        $rules = [
            'shipping_first_name' => ['required', 'string', 'max:120'],
            'shipping_last_name' => ['required', 'string', 'max:120'],
            'shipping_email' => ['required', 'email', 'max:190'],
            'shipping_phone' => ['nullable', 'string', 'max:50'],
            'shipping_address_line_1' => ['required', 'string', 'max:255'],
            'shipping_postcode' => ['required', 'string', 'max:20'],
            'shipping_city' => ['required', 'string', 'max:120'],
            'shipping_province' => ['nullable', 'string', 'max:10'],
            'shipping_country' => $shippingCountryRules,

            'billing_request_invoice' => ['nullable', 'boolean'],
            'billing_same_as_shipping' => ['nullable', 'boolean'],

            'billing_first_name' => [$requiresBillingAddress ? 'required' : 'nullable', 'string', 'max:120'],
            'billing_last_name' => [$requiresBillingAddress ? 'required' : 'nullable', 'string', 'max:120'],
            'billing_email' => [$requiresBillingAddress ? 'required' : 'nullable', 'email', 'max:190'],
            'billing_address_line_1' => [$requiresBillingAddress ? 'required' : 'nullable', 'string', 'max:255'],
            'billing_postcode' => [$requiresBillingAddress ? 'required' : 'nullable', 'string', 'max:20'],
            'billing_city' => [$requiresBillingAddress ? 'required' : 'nullable', 'string', 'max:120'],
            'billing_province' => ['nullable', 'string', 'max:10'],
            'billing_country' => $requiresBillingAddress
                ? array_values(array_filter(array_merge(['required'], $billingCountryRules), fn ($rule) => $rule !== 'nullable'))
                : $billingCountryRules,

            'billing_company' => [$requestInvoice ? 'required' : 'nullable', 'string', 'max:190'],
            'billing_tax_code' => ['nullable', 'string', 'max:50'],
            'billing_vat_number' => ['nullable', 'string', 'max:50'],
            'billing_sdi' => ['nullable', 'string', 'max:20'],
            'billing_pec' => ['nullable', 'string', 'max:190'],

            'payment_gateway' => ['required', 'string', 'in:stripe,paypal'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($requirePaymentToken) {
            $gateway = $this->normalizeGateway($request->input('payment_gateway'));

            if ($gateway === 'stripe') {
                $rules['payment_intent_id'] = ['required', 'string', 'max:255'];
            }

            if ($gateway === 'paypal') {
                $rules['paypal_order_id'] = ['required', 'string', 'max:255'];
            }
        }

        return $rules;
    }

    private function assertB2cPaymentAuthorized(array $validated): void
    {
        $gateway = $this->normalizeGateway($validated['payment_gateway'] ?? null);

        if ($gateway === 'stripe') {
            $paymentIntentId = trim((string) ($validated['payment_intent_id'] ?? ''));

            if ($paymentIntentId === '') {
                throw new InvalidArgumentException('Pagamento Stripe mancante.');
            }

            $payment = $this->paymentService->retrievePayment('stripe', $paymentIntentId);
            $status = strtolower((string) ($payment['status'] ?? ''));

            if (!in_array($status, ['requires_capture', 'succeeded'], true)) {
                throw new InvalidArgumentException('Pagamento Stripe non autorizzato.');
            }

            return;
        }

        if ($gateway === 'paypal') {
            $paypalOrderId = trim((string) ($validated['paypal_order_id'] ?? ''));

            if ($paypalOrderId === '') {
                throw new InvalidArgumentException('Pagamento PayPal mancante.');
            }

            $payment = $this->paymentService->retrievePayment('paypal', $paypalOrderId);
            $status = strtoupper((string) ($payment['status'] ?? ''));

            if (!in_array($status, ['APPROVED', 'COMPLETED'], true)) {
                throw new InvalidArgumentException('Pagamento PayPal non autorizzato. Stato: ' . ($status ?: 'sconosciuto'));
            }
        }
    }

    private function markOrderPaymentAuthorizedFromCheckout(Order $order, array $validated): void
    {
        $gateway = $this->normalizeGateway($validated['payment_gateway'] ?? null);
        $transactionId = $gateway === 'paypal'
            ? trim((string) ($validated['paypal_order_id'] ?? ''))
            : trim((string) ($validated['payment_intent_id'] ?? ''));

        $payment = $transactionId !== ''
            ? $this->paymentService->retrievePayment($gateway, $transactionId)
            : [];

        $gatewayStatus = $this->resolveGatewayPaymentStatus($gateway, $payment);
        $isAlreadyCaptured = $this->isGatewayPaymentCaptured($gateway, $gatewayStatus);

        $meta = $order->meta ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        $meta = is_array($meta) ? $meta : [];
        $meta['payment'] = array_merge($meta['payment'] ?? [], [
            'gateway' => $gateway,
            'transaction_id' => $transactionId,
            'gateway_status' => $gatewayStatus,
            'authorization_payload' => $payment,
            'authorized_at' => now()->toISOString(),
            'capture_required_from_bo' => !$isAlreadyCaptured,
            'captured_from_checkout' => $isAlreadyCaptured,
        ]);

        $order->forceFill([
            'payment_gateway' => $gateway,
            'payment_method_code' => $gateway,
            'payment_method_label' => $gateway,
            'payment_status' => $isAlreadyCaptured ? 'paid' : 'pending',
            'payment_transaction_id' => $transactionId,
            'paid_at' => $isAlreadyCaptured ? now() : null,
            'status' => 'processing',
            'fulfillment_status' => 'pending',
            'meta' => $meta,
        ])->save();
    }

    private function markB2cOrderAwaitingBoApproval(Order $order): void
    {
        if ((string) $order->channel !== 'b2c') {
            return;
        }

        $meta = $order->meta ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        $meta = is_array($meta) ? $meta : [];

        $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
            'managed_by_sendcloud' => true,
            'created_from_checkout' => true,
            'pending_bo_stock_confirmation' => true,
            'pending_webhook' => false,
            'tracking_number' => null,
            'label_url' => null,
            'error' => null,
            'failed_at' => null,
            'updated_at' => now()->toISOString(),
        ]);

        $order->forceFill([
            'shipping_gateway' => 'sendcloud',
            'shipping_tracking_number' => null,
            'shipping_label_url' => null,
            'shipping_label_created_at' => null,
            'fulfillment_status' => 'pending',
            'meta' => $meta,
        ])->save();
    }


    private function resolveGatewayPaymentStatus(string $gateway, array $payment): string
    {
        if ($gateway === 'paypal') {
            return strtoupper((string) ($payment['status'] ?? ''));
        }

        return strtolower((string) ($payment['status'] ?? ''));
    }

    private function isGatewayPaymentCaptured(string $gateway, string $status): bool
    {
        return $gateway === 'paypal'
            ? strtoupper($status) === 'COMPLETED'
            : strtolower($status) === 'succeeded';
    }

    private function normalizeGateway(mixed $gateway): string
    {
        $gateway = strtolower(trim((string) $gateway));

        return in_array($gateway, ['stripe', 'paypal'], true) ? $gateway : 'stripe';
    }

    private function orderTotalsPayload(Order $order): array
    {
        return [
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'shipping_total' => $order->shipping_total,
            'tax_total' => $order->tax_total,
            'grand_total' => $order->grand_total,
        ];
    }

    private function resolveSelectedPaymentGateway(Cart $cart): string
    {
        $meta = $cart->meta ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        $selected = old('payment_gateway', data_get(is_array($meta) ? $meta : [], 'payment.gateway', 'stripe'));

        return in_array($selected, ['stripe', 'paypal'], true) ? $selected : 'stripe';
    }

    private function resolveDisplayCouponCode(Cart $cart, array $promotions): ?string
    {
        $appliedCouponCode = collect($promotions['applied_coupons'] ?? [])
            ->pluck('code')
            ->filter(fn ($code) => is_string($code) && trim($code) !== '')
            ->first();

        if (is_string($appliedCouponCode) && trim($appliedCouponCode) !== '') {
            return trim($appliedCouponCode);
        }

        $meta = $cart->meta ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        $storedCouponCode = data_get(is_array($meta) ? $meta : [], 'coupon.code');

        return is_string($storedCouponCode) && trim($storedCouponCode) !== ''
            ? trim($storedCouponCode)
            : null;
    }

    private function resolveCustomer(Store $store): ?Customer
    {
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

        return $authCustomer instanceof Customer ? $authCustomer : null;
    }

    private function contextRoute(string $route, mixed $parameters = []): string
    {
        $parameters = is_array($parameters) ? $parameters : [$parameters];
        $contextId = (string) request()->query('agent_context', '');

        if ($contextId !== '') {
            $parameters['agent_context'] = $contextId;
        }

        return route($route, $parameters);
    }

    private function mustAuthenticateForCheckout(Store $store, ?Customer $customer): bool
    {
        return (bool) $store->is_b2b && !$customer instanceof Customer;
    }

    private function redirectToLoginForB2b(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Per procedere al checkout devi accedere.'], 401);
        }

        return redirect()
            ->to($this->contextRoute('storefront.login'))
            ->with('error', 'Per procedere al checkout devi accedere.');
    }

    private function applyGuestCheckoutPreviewData(Request $request, Cart $cart, Collection $availableCountries): Cart
    {
        $cart->shipping_address_id = null;
        $cart->unsetRelation('shippingAddress');

        $defaultCountry = $this->resolveDefaultCountryCode($availableCountries, $cart->shipping_country);

        $shippingFirstName = $this->requestValue($request, 'shipping_first_name', $this->extractFirstName($cart->shipping_name));
        $shippingLastName = $this->requestValue($request, 'shipping_last_name', $this->extractLastName($cart->shipping_name));
        $shippingEmail = $this->requestValue($request, 'shipping_email', $cart->customer_email);
        $shippingPhone = $this->requestValue($request, 'shipping_phone', null);

        $cart->shipping_name = trim(implode(' ', array_filter([$shippingFirstName, $shippingLastName]))) ?: $cart->shipping_name;
        $cart->shipping_address = $this->nullableString($this->requestValue($request, 'shipping_address_line_1', $cart->shipping_address));
        $cart->shipping_zip = $this->nullableString($this->requestValue($request, 'shipping_postcode', $cart->shipping_zip));
        $cart->shipping_city = $this->nullableString($this->requestValue($request, 'shipping_city', $cart->shipping_city));
        $cart->shipping_province = $this->nullableString($this->requestValue($request, 'shipping_province', $cart->shipping_province));
        $cart->shipping_country = $this->resolveSelectedCountryCode(
            $this->requestValue($request, 'shipping_country', $cart->shipping_country),
            $availableCountries,
            $defaultCountry
        );

        $billingSameAsShipping = filter_var(
            $this->requestValue($request, 'billing_same_as_shipping', true),
            FILTER_VALIDATE_BOOLEAN
        );

        $billingFirstName = $billingSameAsShipping
            ? $shippingFirstName
            : $this->requestValue($request, 'billing_first_name', $this->extractFirstName($cart->customer_name));

        $billingLastName = $billingSameAsShipping
            ? $shippingLastName
            : $this->requestValue($request, 'billing_last_name', $this->extractLastName($cart->customer_name));

        $billingEmail = $billingSameAsShipping
            ? $shippingEmail
            : $this->requestValue($request, 'billing_email', $cart->customer_email);

        $cart->customer_name = trim(implode(' ', array_filter([$billingFirstName, $billingLastName]))) ?: $cart->customer_name;
        $cart->customer_email = $this->nullableString($billingEmail ?? $cart->customer_email);

        $this->attachCheckoutMeta($cart, [
            'shipping_first_name' => $shippingFirstName,
            'shipping_last_name' => $shippingLastName,
            'shipping_email' => $shippingEmail,
            'shipping_phone' => $shippingPhone,
            'billing_same_as_shipping' => $billingSameAsShipping,
            'billing_request_invoice' => filter_var($this->requestValue($request, 'billing_request_invoice', false), FILTER_VALIDATE_BOOLEAN),
        ], false);

        return $cart;
    }

    private function applyGuestCheckoutData(Cart $cart, array $data, Collection $availableCountries): Cart
    {
        $cart->shipping_address_id = null;
        $cart->unsetRelation('shippingAddress');

        $cart->shipping_name = trim(implode(' ', array_filter([
            $data['shipping_first_name'] ?? null,
            $data['shipping_last_name'] ?? null,
        ]))) ?: null;

        $cart->shipping_address = $this->nullableString($data['shipping_address_line_1'] ?? null);
        $cart->shipping_zip = $this->nullableString($data['shipping_postcode'] ?? null);
        $cart->shipping_city = $this->nullableString($data['shipping_city'] ?? null);
        $cart->shipping_province = $this->nullableString($data['shipping_province'] ?? null);
        $cart->shipping_country = $this->resolveSelectedCountryCode(
            $data['shipping_country'] ?? null,
            $availableCountries,
            $this->resolveDefaultCountryCode($availableCountries, $cart->shipping_country)
        );

        $billingSameAsShipping = (bool) ($data['billing_same_as_shipping'] ?? true);
        $requestInvoice = (bool) ($data['billing_request_invoice'] ?? false);

        $billingFirstName = $requestInvoice
            ? ($billingSameAsShipping ? ($data['shipping_first_name'] ?? null) : ($data['billing_first_name'] ?? null))
            : null;

        $billingLastName = $requestInvoice
            ? ($billingSameAsShipping ? ($data['shipping_last_name'] ?? null) : ($data['billing_last_name'] ?? null))
            : null;

        $billingEmail = $requestInvoice
            ? ($billingSameAsShipping ? ($data['shipping_email'] ?? null) : ($data['billing_email'] ?? null))
            : ($data['shipping_email'] ?? null);

        $cart->customer_name = trim(implode(' ', array_filter([
            $billingFirstName ?: ($data['shipping_first_name'] ?? null),
            $billingLastName ?: ($data['shipping_last_name'] ?? null),
        ]))) ?: null;

        $cart->customer_email = $this->nullableString($billingEmail);

        $this->attachCheckoutMeta($cart, [
            'shipping_first_name' => $data['shipping_first_name'] ?? null,
            'shipping_last_name' => $data['shipping_last_name'] ?? null,
            'shipping_email' => $data['shipping_email'] ?? null,
            'shipping_phone' => $data['shipping_phone'] ?? null,

            'billing_same_as_shipping' => $billingSameAsShipping,
            'billing_request_invoice' => $requestInvoice,
            'billing_first_name' => $requestInvoice ? $billingFirstName : null,
            'billing_last_name' => $requestInvoice ? $billingLastName : null,
            'billing_email' => $requestInvoice ? $billingEmail : null,
            'billing_address_line_1' => $requestInvoice ? ($billingSameAsShipping ? ($data['shipping_address_line_1'] ?? null) : ($data['billing_address_line_1'] ?? null)) : null,
            'billing_postcode' => $requestInvoice ? ($billingSameAsShipping ? ($data['shipping_postcode'] ?? null) : ($data['billing_postcode'] ?? null)) : null,
            'billing_city' => $requestInvoice ? ($billingSameAsShipping ? ($data['shipping_city'] ?? null) : ($data['billing_city'] ?? null)) : null,
            'billing_province' => $requestInvoice ? ($billingSameAsShipping ? ($data['shipping_province'] ?? null) : ($data['billing_province'] ?? null)) : null,
            'billing_country' => $requestInvoice ? ($billingSameAsShipping ? ($cart->shipping_country ?? null) : ($data['billing_country'] ?? null)) : null,
            'billing_company' => $requestInvoice ? ($data['billing_company'] ?? null) : null,
            'billing_tax_code' => $requestInvoice ? ($data['billing_tax_code'] ?? null) : null,
            'billing_vat_number' => $requestInvoice ? ($data['billing_vat_number'] ?? null) : null,
            'billing_sdi' => $requestInvoice ? ($data['billing_sdi'] ?? null) : null,
            'billing_pec' => $requestInvoice ? ($data['billing_pec'] ?? null) : null,

            'payment_gateway' => $data['payment_gateway'] ?? null,
            'payment_method_code' => $data['payment_gateway'] ?? null,
            'payment_method_label' => $data['payment_gateway'] ?? null,
        ], true);

        return $cart->fresh(['items', 'customer', 'store', 'shippingAddress']);
    }

    private function buildB2cCheckoutData(Request $request, Cart $cart, Collection $availableCountries): array
    {
        $cartMeta = is_array($cart->meta ?? null)
            ? $cart->meta
            : (json_decode((string) ($cart->meta ?? '[]'), true) ?: []);
        $checkoutMeta = is_array($cartMeta['checkout'] ?? null) ? $cartMeta['checkout'] : [];

        $defaultCountry = $this->resolveDefaultCountryCode($availableCountries, $cart->shipping_country);

        $shippingCountry = $this->resolveSelectedCountryCode(
            $this->requestValue($request, 'shipping_country', $cart->shipping_country),
            $availableCountries,
            $defaultCountry
        ) ?? $defaultCountry;

        $billingSameAsShipping = filter_var(
            $this->requestValue($request, 'billing_same_as_shipping', '1'),
            FILTER_VALIDATE_BOOLEAN
        );

        $billingCountry = $billingSameAsShipping
            ? $shippingCountry
            : ($this->resolveSelectedCountryCode(
                $this->requestValue($request, 'billing_country', null),
                $availableCountries,
                $defaultCountry
            ) ?? $defaultCountry);

        return [
            'available_countries' => $availableCountries->values()->all(),
            'shipping' => [
                'first_name' => $this->requestValue($request, 'shipping_first_name', $checkoutMeta['shipping_first_name'] ?? $this->extractFirstName($cart->shipping_name)),
                'last_name' => $this->requestValue($request, 'shipping_last_name', $checkoutMeta['shipping_last_name'] ?? $this->extractLastName($cart->shipping_name)),
                'email' => $this->requestValue($request, 'shipping_email', $checkoutMeta['shipping_email'] ?? $cart->customer_email),
                'phone' => $this->requestValue($request, 'shipping_phone', $checkoutMeta['shipping_phone'] ?? ''),
                'address_line_1' => $this->requestValue($request, 'shipping_address_line_1', $cart->shipping_address),
                'postcode' => $this->requestValue($request, 'shipping_postcode', $cart->shipping_zip),
                'city' => $this->requestValue($request, 'shipping_city', $cart->shipping_city),
                'province' => $this->requestValue($request, 'shipping_province', $cart->shipping_province),
                'country' => $shippingCountry,
            ],
            'billing' => [
                'same_as_shipping' => $billingSameAsShipping,
                'request_invoice' => filter_var($this->requestValue($request, 'billing_request_invoice', false), FILTER_VALIDATE_BOOLEAN),
                'first_name' => $billingSameAsShipping
                    ? $this->requestValue($request, 'shipping_first_name', $this->extractFirstName($cart->shipping_name))
                    : $this->requestValue($request, 'billing_first_name', $checkoutMeta['billing_first_name'] ?? $this->extractFirstName($cart->customer_name)),
                'last_name' => $billingSameAsShipping
                    ? $this->requestValue($request, 'shipping_last_name', $this->extractLastName($cart->shipping_name))
                    : $this->requestValue($request, 'billing_last_name', $checkoutMeta['billing_last_name'] ?? $this->extractLastName($cart->customer_name)),
                'email' => $billingSameAsShipping
                    ? $this->requestValue($request, 'shipping_email', $cart->customer_email)
                    : $this->requestValue($request, 'billing_email', $checkoutMeta['billing_email'] ?? $cart->customer_email),
                'address_line_1' => $billingSameAsShipping
                    ? $this->requestValue($request, 'shipping_address_line_1', $cart->shipping_address)
                    : $this->requestValue($request, 'billing_address_line_1', $checkoutMeta['billing_address_line_1'] ?? ''),
                'postcode' => $billingSameAsShipping
                    ? $this->requestValue($request, 'shipping_postcode', $cart->shipping_zip)
                    : $this->requestValue($request, 'billing_postcode', $checkoutMeta['billing_postcode'] ?? ''),
                'city' => $billingSameAsShipping
                    ? $this->requestValue($request, 'shipping_city', $cart->shipping_city)
                    : $this->requestValue($request, 'billing_city', $checkoutMeta['billing_city'] ?? ''),
                'province' => $billingSameAsShipping
                    ? $this->requestValue($request, 'shipping_province', $cart->shipping_province)
                    : $this->requestValue($request, 'billing_province', $checkoutMeta['billing_province'] ?? ''),
                'country' => $billingCountry,
                'company' => $this->requestValue($request, 'billing_company', $checkoutMeta['billing_company'] ?? data_get($cartMeta, 'billing.company', '')),
                'tax_code' => $this->requestValue($request, 'billing_tax_code', $checkoutMeta['billing_tax_code'] ?? ''),
                'vat_number' => $this->requestValue($request, 'billing_vat_number', $checkoutMeta['billing_vat_number'] ?? ''),
                'sdi' => $this->requestValue($request, 'billing_sdi', $checkoutMeta['billing_sdi'] ?? ''),
                'pec' => $this->requestValue($request, 'billing_pec', $checkoutMeta['billing_pec'] ?? ''),
            ],
        ];
    }

    private function buildCheckoutSummaryData(Cart $cart, array $calculatedTotals): array
    {
        $shippingDetails = is_array($calculatedTotals['shipping'] ?? null)
            ? $calculatedTotals['shipping']
            : [];

        return [
            'subtotal' => (float) ($calculatedTotals['subtotal'] ?? $cart->subtotal ?? 0),
            'discount_total' => (float) ($calculatedTotals['discount_total'] ?? $cart->discount_total ?? 0),
            'grand_total' => max(0, (float) ($calculatedTotals['grand_total'] ?? $cart->grand_total ?? 0)),
            'shipping_total' => (float) ($calculatedTotals['shipping_total'] ?? 0),
            'shipping_available' => (bool) ($shippingDetails['available'] ?? false),
            'shipping_message' => trim((string) ($shippingDetails['message'] ?? '')),
            'shipping_is_free' => (bool) ($shippingDetails['is_free'] ?? false),
        ];
    }

    private function loadCountryCatalog(): Collection
    {
        $path = app_path('Data/countries.json');

        if (!File::exists($path)) {
            return collect();
        }

        $decoded = json_decode((string) File::get($path), true);

        if (!is_array($decoded)) {
            return collect();
        }

        return collect($decoded)
            ->filter(fn ($row) => is_array($row) && !empty($row['code']) && !empty($row['label']))
            ->map(fn (array $row) => [
                'code' => $this->normalizeCountryCode($row['code'] ?? null),
                'label' => trim((string) ($row['label'] ?? '')),
            ])
            ->filter(fn (array $row) => !empty($row['code']) && $row['label'] !== '')
            ->unique('code')
            ->values();
    }

    private function resolveAvailableB2cCountries(Store $store, Collection $countryCatalog): Collection
    {
        $catalogByCode = $countryCatalog->keyBy('code');

        return ShippingRule::query()
            ->forStore($store)
            ->where('type', 'table')
            ->where('is_active', true)
            ->whereNotNull('country')
            ->pluck('country')
            ->map(fn ($country) => $this->normalizeCountryCode($country))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $countryCode) => [
                'code' => $countryCode,
                'label' => is_array($catalogByCode->get($countryCode)) && !empty($catalogByCode->get($countryCode)['label'])
                    ? (string) $catalogByCode->get($countryCode)['label']
                    : $countryCode,
            ])
            ->values();
    }

    private function resolveDefaultCountryCode(Collection $availableCountries, ?string $fallback = null): string
    {
        $normalizedFallback = $this->normalizeCountryCode($fallback);

        if ($normalizedFallback !== null && $availableCountries->contains(fn ($row) => strtoupper((string) ($row['code'] ?? '')) === $normalizedFallback)) {
            return $normalizedFallback;
        }

        $it = $availableCountries->first(fn ($row) => strtoupper((string) ($row['code'] ?? '')) === 'ITA');

        if (is_array($it) && !empty($it['code'])) {
            return (string) $it['code'];
        }

        $first = $availableCountries->first();

        return is_array($first) && !empty($first['code']) ? (string) $first['code'] : 'ITA';
    }

    private function resolveSelectedCountryCode(mixed $value, Collection $availableCountries, ?string $fallback = null): ?string
    {
        $normalized = $this->normalizeCountryCode($value);

        if ($normalized !== null && $availableCountries->contains(fn ($row) => strtoupper((string) ($row['code'] ?? '')) === $normalized)) {
            return $normalized;
        }

        $normalizedFallback = $this->normalizeCountryCode($fallback);

        if ($normalizedFallback !== null && $availableCountries->contains(fn ($row) => strtoupper((string) ($row['code'] ?? '')) === $normalizedFallback)) {
            return $normalizedFallback;
        }

        return null;
    }

    private function normalizeCountryCode(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value !== '' ? $value : null;
    }

    private function requestValue(Request $request, string $key, mixed $default = null): mixed
    {
        return $request->has($key) ? $request->input($key) : old($key, $default);
    }

    private function handleException(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return redirect()->back()->withInput()->with('error', $message);
    }

    private function decorateCartItems($cart, Store $store): Collection
    {
        $items = collect($cart->items ?? [])->values();

        if ($items->isEmpty()) {
            return collect();
        }

        $skus = $items->pluck('sku')->filter()->unique()->values();

        $productsBySku = Product::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code)
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        return $items->map(function ($item) use ($productsBySku) {
            $product = $productsBySku->get($item->sku);

            $constraints = $product instanceof Product
                ? $this->cartService->resolveQuantityConstraintsForProduct($product)
                : [
                    'quantity_min' => 1,
                    'quantity_step' => 1,
                    'pack_multiple' => 1,
                    'show_pack_multiple' => false,
                ];

            $item->quantity_min = max(1, (int) ($constraints['quantity_min'] ?? 1));
            $item->quantity_step = max(1, (int) ($constraints['quantity_step'] ?? 1));
            $item->pack_multiple = max(1, (int) ($constraints['pack_multiple'] ?? 1));
            $item->show_pack_multiple = (bool) ($constraints['show_pack_multiple'] ?? false);
            $item->product_url = $this->contextRoute('storefront.product.show', ['sku' => $item->sku]);

            return $item;
        })->values();
    }

    private function resolveStore(): Store
    {
        $store = app()->bound('currentStore') ? app('currentStore') : null;

        abort_unless($store instanceof Store, 404, 'Store corrente non disponibile.');

        return $store;
    }

    private function resolveShippingAddresses(?Customer $customer, Store $store): Collection
    {
        if (!$customer instanceof Customer) {
            return collect();
        }

        return CustomerShippingAddress::query()
            ->where('clifor_cg44', $customer->clifor_cg44)
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->when(!empty($customer->tipocf_cg44), fn ($query) => $query->where('tipocf_cg44', $customer->tipocf_cg44))
            ->where('is_active', true)
            ->orderBy('coddestin_mg22')
            ->get();
    }

    private function resolveRequestedShippingAddress(?Customer $customer, Store $store, int $shippingAddressId): CustomerShippingAddress
    {
        if ($shippingAddressId <= 0) {
            throw new InvalidArgumentException('Indirizzo di spedizione obbligatorio.');
        }

        if (!$customer instanceof Customer) {
            throw new InvalidArgumentException('Cliente non autenticato.');
        }

        $address = CustomerShippingAddress::query()
            ->where('id', $shippingAddressId)
            ->where('clifor_cg44', $customer->clifor_cg44)
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->when(!empty($customer->tipocf_cg44), fn ($query) => $query->where('tipocf_cg44', $customer->tipocf_cg44))
            ->where('is_active', true)
            ->first();

        if (!$address instanceof CustomerShippingAddress) {
            throw new InvalidArgumentException('Indirizzo di spedizione non valido.');
        }

        return $address;
    }

    private function resolveSelectedShippingAddressId(Request $request, $cart, Collection $shippingAddresses): ?string
    {
        $selectedShippingAddressId = (string) ($request->input(
            'shipping_address_id',
            old('shipping_address_id', $cart->shipping_address_id ?? '')
        ) ?? '');

        return $selectedShippingAddressId !== ''
            ? $selectedShippingAddressId
            : ($shippingAddresses->isNotEmpty() ? (string) $shippingAddresses->first()->id : null);
    }

    private function buildBillingData(?Customer $customer, $cart): array
    {
        return [
            'name' => trim((string) ($customer?->ragsoanag_cg16 ?? $cart->customer_name ?? '')),
            'address' => trim((string) ($customer?->indirizzo_cg16 ?? '')),
            'zip' => trim((string) ($customer?->cap_cg16 ?? '')),
            'city' => trim((string) ($customer?->citta_cg16 ?? '')),
            'province' => trim((string) ($customer?->prov_cg16 ?? '')),
            'vat' => trim((string) ($customer?->partiva_cg16 ?? '')),
            'tax_code' => trim((string) ($customer?->codfiscale_cg16 ?? '')),
            'email' => trim((string) ($customer?->indemail_cg16 ?? $cart->customer_email ?? '')),
            'pec' => trim((string) ($customer?->email_pec_cg16 ?? '')),
            'phone' => trim((string) ($customer?->tel1num_cg16 ?? $customer?->cellnum_cg16 ?? '')),
        ];
    }

    private function buildBankData(?Customer $customer): array
    {
        return [
            'name' => trim((string) ($customer?->desbanca_cg12_cg13 ?? '')),
            'iban' => trim((string) ($customer?->iban_mg35 ?? '')),
            'abi' => trim((string) ($customer?->ccabi_mg35 ?? '')),
            'cab' => trim((string) ($customer?->cccab_mg35 ?? '')),
        ];
    }

    private function hasFilledValues(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function emptyCheckoutData($cart): array
    {
        return [
            'cart' => $cart,
            'items' => collect(),
            'shipping_addresses' => collect(),
            'selected_shipping_address_id' => null,
            'billing' => [],
            'bank' => [],
            'shipping_cost' => 0,
            'shipping_details' => [],
            'has_billing_data' => false,
            'has_bank_data' => false,
            'b2c_checkout' => [],
            'checkout_summary' => [],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function extractFirstName(?string $fullName): string
    {
        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];

        return (string) ($parts[0] ?? '');
    }

    private function extractLastName(?string $fullName): string
    {
        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];

        if (count($parts) <= 1) {
            return '';
        }

        array_shift($parts);

        return implode(' ', $parts);
    }

    private function attachCheckoutMeta(Cart $cart, array $checkoutData, bool $save): void
    {
        $meta = $cart->meta ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        $meta = is_array($meta) ? $meta : [];

        $meta['checkout'] = array_merge($meta['checkout'] ?? [], $checkoutData);

        $meta['shipping'] = array_merge($meta['shipping'] ?? [], [
            'first_name' => $checkoutData['shipping_first_name'] ?? null,
            'last_name' => $checkoutData['shipping_last_name'] ?? null,
            'email' => $checkoutData['shipping_email'] ?? null,
            'phone' => $checkoutData['shipping_phone'] ?? null,
        ]);

        $meta['billing'] = array_merge($meta['billing'] ?? [], [
            'same_as_shipping' => $checkoutData['billing_same_as_shipping'] ?? true,
            'request_invoice' => $checkoutData['billing_request_invoice'] ?? false,
            'first_name' => $checkoutData['billing_first_name'] ?? null,
            'last_name' => $checkoutData['billing_last_name'] ?? null,
            'email' => $checkoutData['billing_email'] ?? null,
            'address_line_1' => $checkoutData['billing_address_line_1'] ?? null,
            'postcode' => $checkoutData['billing_postcode'] ?? null,
            'city' => $checkoutData['billing_city'] ?? null,
            'province' => $checkoutData['billing_province'] ?? null,
            'country' => $checkoutData['billing_country'] ?? null,
            'company' => $checkoutData['billing_company'] ?? null,
            'tax_code' => $checkoutData['billing_tax_code'] ?? null,
            'vat_number' => $checkoutData['billing_vat_number'] ?? null,
            'sdi' => $checkoutData['billing_sdi'] ?? null,
            'pec' => $checkoutData['billing_pec'] ?? null,
        ]);

        $gateway = $checkoutData['payment_gateway'] ?? null;

        $meta['payment'] = array_merge($meta['payment'] ?? [], [
            'gateway' => $gateway,
            'method_code' => $gateway,
            'method_label' => $gateway,
        ]);

        $cart->meta = $meta;

        if ($save) {
            $cart->save();
        }
    }
}

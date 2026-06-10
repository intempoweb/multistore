@extends($storefrontLayout)

@section('title', 'Checkout')

@section('content')
<div
    class="container py-5 checkout-page"
    data-checkout-mode="{{ $isB2b ? 'b2b' : 'b2c' }}"
    data-shipping-storage-key="{{ $shippingSelectionStorageKey }}"
>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">Checkout</h1>
            <div class="text-muted small">Verifica spedizione, fatturazione e riepilogo ordine.</div>
        </div>

        <a href="{{ route('storefront.cart.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-2"></i>
            Torna al carrello
        </a>
    </div>

    @includeIf('storefront.base.partials.alerts')

    @if(!$shippingAvailable && $shippingMessage !== '')
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Spedizione non disponibile</div>
            <div>{{ $shippingMessage }}</div>
        </div>
    @endif

    @if (!$cart || $items->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body py-5 text-center">
                <div class="mb-3 text-muted">
                    <i class="fa-solid fa-cart-shopping fa-2x"></i>
                </div>

                <h5 class="mb-2">Il carrello è vuoto</h5>
                <p class="text-muted mb-4">Aggiungi prodotti dal catalogo per procedere con l'ordine.</p>

                <a href="{{ route('storefront.catalog.index') }}" class="btn btn-primary">
                    Vai al catalogo
                </a>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('storefront.checkout.place') }}" id="checkout-place-form" class="d-none">
            @csrf
        </form>

        <div class="row g-4 align-items-start">
            <div class="col-12 col-xl-4">
                @if($isB2b)
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="mb-1">Spedizione</h5>
                                    <div class="text-muted small">Seleziona l’indirizzo di consegna.</div>
                                </div>

                                <span class="badge text-bg-light border">
                                    {{ $shippingAddresses->count() }} indirizzi
                                </span>
                            </div>

                            @if($shippingAddresses->isNotEmpty())
                                <div class="d-flex flex-column gap-3">
                                    @foreach($shippingAddresses as $address)
                                        @php
                                            $isSelected = $selectedShippingAddressId === (string) $address->id;
                                        @endphp

                                        <label class="card border {{ $isSelected ? 'border-primary shadow-sm' : 'border-light-subtle' }} rounded-3 checkout-shipping-card" data-shipping-card>
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                                    <div class="form-check m-0">
                                                        <input
                                                            class="form-check-input"
                                                            type="radio"
                                                            name="shipping_address_id"
                                                            id="shipping_address_{{ $address->id }}"
                                                            value="{{ $address->id }}"
                                                            form="checkout-place-form"
                                                            {{ $isSelected ? 'checked' : '' }}
                                                            required
                                                            data-shipping-radio
                                                            data-shipping-address-id="{{ $address->id }}"
                                                        >

                                                        <span class="form-check-label fw-semibold">
                                                            {{ $address->destragsoc_mg22 ?: $address->display_name ?: ('Destinazione ' . $address->coddestin_mg22) }}
                                                        </span>
                                                    </div>

                                                    <span class="badge text-bg-primary {{ $isSelected ? '' : 'd-none' }}" data-shipping-selected-badge>
                                                        Selezionato
                                                    </span>
                                                </div>

                                                <div class="small text-muted d-flex flex-column gap-1">
                                                    @if(!empty($address->destind_mg22))
                                                        <div><i class="fa-solid fa-location-dot me-2"></i>{{ $address->destind_mg22 }}</div>
                                                    @endif

                                                    @if(!empty($address->destcap_mg22) || !empty($address->destcitta_mg22) || !empty($address->destprov_mg22))
                                                        <div>
                                                            <i class="fa-solid fa-city me-2"></i>
                                                            {{ trim((!empty($address->destcap_mg22) ? $address->destcap_mg22 . ' ' : '') . ($address->destcitta_mg22 ?? '') . (!empty($address->destprov_mg22) ? ' (' . $address->destprov_mg22 . ')' : '')) }}
                                                        </div>
                                                    @endif

                                                    @if(!empty($address->destemail_mg22))
                                                        <div><i class="fa-solid fa-envelope me-2"></i>{{ $address->destemail_mg22 }}</div>
                                                    @endif

                                                    @if(!empty($address->desttel_mg22) || !empty($address->destcell_mg22))
                                                        <div><i class="fa-solid fa-phone me-2"></i>{{ $address->desttel_mg22 ?: $address->destcell_mg22 }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>

                                @error('shipping_address_id')
                                    <div class="text-danger small mt-3">{{ $message }}</div>
                                @enderror
                            @else
                                <div class="alert alert-warning mb-0">
                                    Nessun indirizzo di spedizione disponibile per questo account.
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h5 class="mb-3">Dati spedizione</h5>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="shipping_first_name" class="form-label">Nome</label>
                                    <input type="text" name="shipping_first_name" id="shipping_first_name" form="checkout-place-form" class="form-control @error('shipping_first_name') is-invalid @enderror" value="{{ old('shipping_first_name', $b2cShipping['first_name'] ?? '') }}" required>
                                    @error('shipping_first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="shipping_last_name" class="form-label">Cognome</label>
                                    <input type="text" name="shipping_last_name" id="shipping_last_name" form="checkout-place-form" class="form-control @error('shipping_last_name') is-invalid @enderror" value="{{ old('shipping_last_name', $b2cShipping['last_name'] ?? '') }}" required>
                                    @error('shipping_last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label for="shipping_email" class="form-label">Email</label>
                                    <input type="email" name="shipping_email" id="shipping_email" form="checkout-place-form" class="form-control @error('shipping_email') is-invalid @enderror" value="{{ old('shipping_email', $b2cShipping['email'] ?? '') }}" required>
                                    @error('shipping_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label for="shipping_phone" class="form-label">Telefono</label>
                                    <input type="text" name="shipping_phone" id="shipping_phone" form="checkout-place-form" class="form-control @error('shipping_phone') is-invalid @enderror" value="{{ old('shipping_phone', $b2cShipping['phone'] ?? '') }}">
                                    @error('shipping_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label for="shipping_address_line_1" class="form-label">Indirizzo</label>
                                    <input type="text" name="shipping_address_line_1" id="shipping_address_line_1" form="checkout-place-form" class="form-control @error('shipping_address_line_1') is-invalid @enderror" value="{{ old('shipping_address_line_1', $b2cShipping['address_line_1'] ?? '') }}" required>
                                    @error('shipping_address_line_1') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="shipping_postcode" class="form-label">CAP</label>
                                    <input type="text" name="shipping_postcode" id="shipping_postcode" form="checkout-place-form" class="form-control @error('shipping_postcode') is-invalid @enderror" value="{{ old('shipping_postcode', $b2cShipping['postcode'] ?? '') }}" required>
                                    @error('shipping_postcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-5">
                                    <label for="shipping_city" class="form-label">Città</label>
                                    <input type="text" name="shipping_city" id="shipping_city" form="checkout-place-form" class="form-control @error('shipping_city') is-invalid @enderror" value="{{ old('shipping_city', $b2cShipping['city'] ?? '') }}" required>
                                    @error('shipping_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-3">
                                    <label for="shipping_province" class="form-label">Prov.</label>
                                    <input type="text" name="shipping_province" id="shipping_province" form="checkout-place-form" class="form-control @error('shipping_province') is-invalid @enderror" value="{{ old('shipping_province', $b2cShipping['province'] ?? '') }}">
                                    @error('shipping_province') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label for="shipping_country" class="form-label">Nazione</label>
                                    @if($availableCountries->isNotEmpty())
                                        <select name="shipping_country" id="shipping_country" form="checkout-place-form" class="form-select @error('shipping_country') is-invalid @enderror" required>
                                            @foreach($availableCountries as $country)
                                                <option value="{{ $country['code'] ?? '' }}" @selected(old('shipping_country', $b2cShipping['country'] ?? 'ITA') === ($country['code'] ?? ''))>
                                                    {{ $country['label'] ?? $country['code'] ?? '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" name="shipping_country" id="shipping_country" form="checkout-place-form" class="form-control @error('shipping_country') is-invalid @enderror" value="{{ old('shipping_country', $b2cShipping['country'] ?? 'ITA') }}" maxlength="3" required>
                                    @endif
                                    @error('shipping_country') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h5 class="mb-1">Fatturazione</h5>
                                    <div class="text-muted small">Compila solo se diversa o se richiedi fattura.</div>
                                </div>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="billing_same_as_shipping" name="billing_same_as_shipping" value="1" form="checkout-place-form" {{ $billingSameAsShipping ? 'checked' : '' }} data-billing-same-as-shipping>
                                <label class="form-check-label fw-semibold" for="billing_same_as_shipping">
                                    Fatturazione uguale alla spedizione
                                </label>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="billing_request_invoice" name="billing_request_invoice" value="1" form="checkout-place-form" {{ $requestInvoice ? 'checked' : '' }} data-invoice-toggle>
                                <label class="form-check-label fw-semibold" for="billing_request_invoice">
                                    Richiedo fattura
                                </label>
                            </div>

                            <div data-billing-wrapper>
                                <div class="row g-3" data-billing-fields>
                                    <div class="col-md-6">
                                        <label for="billing_first_name" class="form-label">Nome</label>
                                        <input type="text" name="billing_first_name" id="billing_first_name" form="checkout-place-form" class="form-control @error('billing_first_name') is-invalid @enderror" value="{{ old('billing_first_name', $b2cBilling['first_name'] ?? '') }}" data-billing-input>
                                        @error('billing_first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="billing_last_name" class="form-label">Cognome</label>
                                        <input type="text" name="billing_last_name" id="billing_last_name" form="checkout-place-form" class="form-control @error('billing_last_name') is-invalid @enderror" value="{{ old('billing_last_name', $b2cBilling['last_name'] ?? '') }}" data-billing-input>
                                        @error('billing_last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-12">
                                        <label for="billing_email" class="form-label">Email fatturazione</label>
                                        <input type="email" name="billing_email" id="billing_email" form="checkout-place-form" class="form-control @error('billing_email') is-invalid @enderror" value="{{ old('billing_email', $b2cBilling['email'] ?? '') }}" data-billing-input>
                                        @error('billing_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-12">
                                        <label for="billing_address_line_1" class="form-label">Indirizzo fatturazione</label>
                                        <input type="text" name="billing_address_line_1" id="billing_address_line_1" form="checkout-place-form" class="form-control @error('billing_address_line_1') is-invalid @enderror" value="{{ old('billing_address_line_1', $b2cBilling['address_line_1'] ?? '') }}" data-billing-input>
                                        @error('billing_address_line_1') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-4">
                                        <label for="billing_postcode" class="form-label">CAP</label>
                                        <input type="text" name="billing_postcode" id="billing_postcode" form="checkout-place-form" class="form-control @error('billing_postcode') is-invalid @enderror" value="{{ old('billing_postcode', $b2cBilling['postcode'] ?? '') }}" data-billing-input>
                                        @error('billing_postcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-5">
                                        <label for="billing_city" class="form-label">Città</label>
                                        <input type="text" name="billing_city" id="billing_city" form="checkout-place-form" class="form-control @error('billing_city') is-invalid @enderror" value="{{ old('billing_city', $b2cBilling['city'] ?? '') }}" data-billing-input>
                                        @error('billing_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-3">
                                        <label for="billing_province" class="form-label">Prov.</label>
                                        <input type="text" name="billing_province" id="billing_province" form="checkout-place-form" class="form-control @error('billing_province') is-invalid @enderror" value="{{ old('billing_province', $b2cBilling['province'] ?? '') }}" data-billing-input>
                                        @error('billing_province') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-12">
                                        <label for="billing_country" class="form-label">Nazione fatturazione</label>
                                        @if($availableCountries->isNotEmpty())
                                            <select name="billing_country" id="billing_country" form="checkout-place-form" class="form-select @error('billing_country') is-invalid @enderror" data-billing-input>
                                                <option value="">Usa nazione spedizione</option>
                                                @foreach($availableCountries as $country)
                                                    <option value="{{ $country['code'] ?? '' }}" @selected(old('billing_country', $b2cBilling['country'] ?? '') === ($country['code'] ?? ''))>
                                                        {{ $country['label'] ?? $country['code'] ?? '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="text" name="billing_country" id="billing_country" form="checkout-place-form" class="form-control @error('billing_country') is-invalid @enderror" value="{{ old('billing_country', $b2cBilling['country'] ?? '') }}" maxlength="3" data-billing-input>
                                        @endif
                                        @error('billing_country') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>

                            <div id="invoice-fields" class="{{ $requestInvoice ? '' : 'd-none' }} mt-4" data-invoice-fields>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="billing_tax_code" class="form-label">Codice fiscale</label>
                                        <input type="text" name="billing_tax_code" id="billing_tax_code" form="checkout-place-form" class="form-control @error('billing_tax_code') is-invalid @enderror" value="{{ old('billing_tax_code', $b2cBilling['tax_code'] ?? '') }}" data-invoice-input>
                                        @error('billing_tax_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="billing_vat_number" class="form-label">P. IVA</label>
                                        <input type="text" name="billing_vat_number" id="billing_vat_number" form="checkout-place-form" class="form-control @error('billing_vat_number') is-invalid @enderror" value="{{ old('billing_vat_number', $b2cBilling['vat_number'] ?? '') }}" data-invoice-input>
                                        @error('billing_vat_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="billing_sdi" class="form-label">Codice SDI</label>
                                        <input type="text" name="billing_sdi" id="billing_sdi" form="checkout-place-form" class="form-control @error('billing_sdi') is-invalid @enderror" value="{{ old('billing_sdi', $b2cBilling['sdi'] ?? '') }}" data-invoice-input>
                                        @error('billing_sdi') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label for="billing_pec" class="form-label">PEC</label>
                                        <input type="text" name="billing_pec" id="billing_pec" form="checkout-place-form" class="form-control @error('billing_pec') is-invalid @enderror" value="{{ old('billing_pec', $b2cBilling['pec'] ?? '') }}" data-invoice-input>
                                        @error('billing_pec') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3">Costo spedizione</h5>

                        <div class="border rounded-3 p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Spedizione</span>
                                <span class="fw-semibold">
                                    @if(!$shippingAvailable)
                                        <span class="text-danger">Non disponibile</span>
                                    @elseif($shippingIsFree)
                                        Gratis
                                    @else
                                        € {{ number_format($shippingTotal, 3, ',', '.') }}
                                    @endif
                                </span>
                            </div>

                            <div class="small {{ $shippingAvailable ? 'text-muted' : 'text-danger' }}">
                                {{ $shippingMessage !== '' ? $shippingMessage : 'Il costo finale viene calcolato in base a destinazione e logica spedizione dello store.' }}
                            </div>
                        </div>
                    </div>
                </div>

                @if($isB2b)
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h5 class="mb-3">Fatturazione</h5>

                            @if($hasBillingData)
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <div class="fw-semibold mb-2">
                                        {{ $billing['name'] !== '' ? $billing['name'] : 'Dati intestazione non disponibili' }}
                                    </div>

                                    <div class="small text-muted d-flex flex-column gap-1">
                                        @if($billing['address'] !== '')
                                            <div><i class="fa-solid fa-location-dot me-2"></i>{{ $billing['address'] }}</div>
                                        @endif

                                        @if($billing['zip'] !== '' || $billing['city'] !== '' || $billing['province'] !== '')
                                            <div>
                                                <i class="fa-solid fa-city me-2"></i>
                                                {{ trim(($billing['zip'] !== '' ? $billing['zip'] . ' ' : '') . $billing['city'] . ($billing['province'] !== '' ? ' (' . $billing['province'] . ')' : '')) }}
                                            </div>
                                        @endif

                                        @if($billing['vat'] !== '')
                                            <div><strong>P. IVA:</strong> {{ $billing['vat'] }}</div>
                                        @endif

                                        @if($billing['tax_code'] !== '')
                                            <div><strong>Codice fiscale:</strong> {{ $billing['tax_code'] }}</div>
                                        @endif

                                        @if($billing['email'] !== '')
                                            <div><i class="fa-solid fa-envelope me-2"></i>{{ $billing['email'] }}</div>
                                        @endif

                                        @if($billing['pec'] !== '')
                                            <div><strong>PEC:</strong> {{ $billing['pec'] }}</div>
                                        @endif

                                        @if($billing['phone'] !== '')
                                            <div><i class="fa-solid fa-phone me-2"></i>{{ $billing['phone'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-secondary mb-0">
                                    Dati di fatturazione non disponibili per questo account.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h5 class="mb-3">Dati bancari</h5>

                            @if($hasBankData)
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <div class="small text-muted d-flex flex-column gap-2">
                                        @if($bank['name'] !== '')
                                            <div><strong>Banca:</strong> {{ $bank['name'] }}</div>
                                        @endif

                                        @if($bank['iban'] !== '')
                                            <div><strong>IBAN:</strong> {{ $bank['iban'] }}</div>
                                        @endif

                                        @if($bank['abi'] !== '' || $bank['cab'] !== '')
                                            <div>
                                                @if($bank['abi'] !== '')
                                                    <span><strong>ABI:</strong> {{ $bank['abi'] }}</span>
                                                @endif
                                                @if($bank['cab'] !== '')
                                                    <span class="ms-3"><strong>CAB:</strong> {{ $bank['cab'] }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-secondary mb-0">
                                    Dati bancari non disponibili per questo account.
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h5 class="mb-3">Pagamento</h5>

                            <div class="d-flex flex-column gap-3 mb-3">
                                <label class="border rounded-3 p-3 d-flex align-items-start gap-3 {{ $selectedPaymentGateway === 'stripe' ? 'border-primary bg-light-subtle' : '' }}" data-payment-card>
                                    <input class="form-check-input mt-1" type="radio" name="payment_gateway" value="stripe" form="checkout-place-form" required {{ $selectedPaymentGateway === 'stripe' ? 'checked' : '' }} data-payment-radio>
                                    <span>
                                        <span class="fw-semibold d-block">Carta / Stripe</span>
                                        <span class="small text-muted">Compila i dati carta direttamente nel checkout.</span>
                                    </span>
                                </label>

                                <label class="border rounded-3 p-3 d-flex align-items-start gap-3 {{ $selectedPaymentGateway === 'paypal' ? 'border-primary bg-light-subtle' : '' }}" data-payment-card>
                                    <input class="form-check-input mt-1" type="radio" name="payment_gateway" value="paypal" form="checkout-place-form" required {{ $selectedPaymentGateway === 'paypal' ? 'checked' : '' }} data-payment-radio>
                                    <span>
                                        <span class="fw-semibold d-block">PayPal</span>
                                        <span class="small text-muted">Pagamento PayPal integrato nel checkout.</span>
                                    </span>
                                </label>
                            </div>

                            @error('payment_gateway')
                                <div class="text-danger small mt-3">{{ $message }}</div>
                            @enderror

                            <div class="{{ $selectedPaymentGateway === 'stripe' ? '' : 'd-none' }}" data-payment-panel="stripe">
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <div class="mb-3">
                                        <label for="card_holder" class="form-label">Intestatario carta</label>
                                        <input type="text" name="card_holder" id="card_holder" form="checkout-place-form" class="form-control" autocomplete="cc-name" data-payment-input="stripe">
                                    </div>

                                    <div class="mb-3">
                                        <label for="card_number" class="form-label">Numero carta</label>
                                        <input type="text" name="card_number" id="card_number" form="checkout-place-form" class="form-control" inputmode="numeric" autocomplete="cc-number" placeholder="•••• •••• •••• ••••" data-payment-input="stripe">
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-6">
                                            <label for="card_expiry" class="form-label">Scadenza</label>
                                            <input type="text" name="card_expiry" id="card_expiry" form="checkout-place-form" class="form-control" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/AA" data-payment-input="stripe">
                                        </div>

                                        <div class="col-6">
                                            <label for="card_cvc" class="form-label">CVC</label>
                                            <input type="text" name="card_cvc" id="card_cvc" form="checkout-place-form" class="form-control" inputmode="numeric" autocomplete="cc-csc" placeholder="123" data-payment-input="stripe">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="{{ $selectedPaymentGateway === 'paypal' ? '' : 'd-none' }}" data-payment-panel="paypal">
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <label for="paypal_email" class="form-label">Email PayPal</label>
                                    <input type="email" name="paypal_email" id="paypal_email" form="checkout-place-form" class="form-control" autocomplete="email" placeholder="email@example.com" data-payment-input="paypal">

                                    <div class="small text-muted mt-2">
                                        Usa l’email associata al tuo account PayPal.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

            </div>

            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm sticky-xl-top" style="top: 1.5rem;">
                    <div class="card-body p-4">
                        <h5 class="mb-3">Riepilogo ordine</h5>

                        <div class="d-flex flex-column gap-3 mb-4">
                            @foreach($items as $item)
                                @php
                                    $finalPrice = $item->final_price !== null ? (float) $item->final_price : ($item->price !== null ? (float) $item->price : null);
                                    $finalRowTotal = $item->final_row_total !== null ? (float) $item->final_row_total : ($item->row_total !== null ? (float) $item->row_total : null);
                                    $webDiscountTotal = $item->web_discount_total !== null ? (float) $item->web_discount_total : 0.0;
                                    $thumbnailUrl = media_url($item->product_thumbnail_url);
                                @endphp

                                <div class="d-flex gap-3 pb-3 border-bottom">
                                    <div class="flex-shrink-0">
                                        @if($thumbnailUrl)
                                            <img src="{{ $thumbnailUrl }}" alt="{{ $item->product_name ?? $item->sku }}" class="rounded border" style="width: 58px; height: 58px; object-fit: cover;">
                                        @else
                                            <div class="rounded border d-flex align-items-center justify-content-center bg-light text-muted" style="width: 58px; height: 58px;">
                                                <i class="fa-solid fa-image"></i>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-semibold small">{{ $item->product_name ?? $item->sku }}</div>
                                        <div class="text-muted small">SKU: {{ $item->sku }}</div>
                                        <div class="small mt-1">Quantità: <strong>{{ number_format((float) $item->quantity, 0, ',', '.') }}</strong></div>

                                        @if($finalPrice !== null)
                                            <div class="small text-muted">€ {{ number_format($finalPrice, 3, ',', '.') }} cad.</div>
                                        @endif

                                        @if($webDiscountTotal > 0)
                                            <div class="small text-success">Sconto web: -€ {{ number_format($webDiscountTotal, 3, ',', '.') }}</div>
                                        @endif
                                    </div>

                                    <div class="text-end small fw-semibold text-nowrap">
                                        € {{ number_format((float) ($finalRowTotal ?? 0), 3, ',', '.') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mb-4">
                            @if($displayCouponCode)
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="small text-muted">Coupon applicato</div>
                                            <div class="fw-semibold">{{ $displayCouponCode }}</div>
                                        </div>

                                        <form method="POST" action="{{ route('storefront.cart.coupon.remove') }}" class="m-0">
                                            @csrf
                                            @method('DELETE')

                                            @if($isB2b)
                                                <input type="hidden" name="shipping_address_id" value="{{ $selectedShippingAddressId }}" data-shipping-address-hidden>
                                            @endif

                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Rimuovi
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                <form method="POST" action="{{ route('storefront.cart.coupon.apply') }}">
                                    @csrf

                                    @if($isB2b)
                                        <input type="hidden" name="shipping_address_id" value="{{ $selectedShippingAddressId }}" data-shipping-address-hidden>
                                    @endif

                                    <label for="checkout_coupon_code" class="form-label fw-semibold">Coupon</label>

                                    <div class="input-group">
                                        <input
                                            type="text"
                                            name="coupon_code"
                                            id="checkout_coupon_code"
                                            class="form-control @error('coupon_code') is-invalid @enderror"
                                            value="{{ old('coupon_code') }}"
                                            placeholder="Inserisci codice coupon"
                                            maxlength="80"
                                        >

                                        <button type="submit" class="btn btn-outline-primary">
                                            Applica
                                        </button>
                                    </div>

                                    @error('coupon_code')
                                        <div class="text-danger small mt-2">{{ $message }}</div>
                                    @enderror
                                </form>
                            @endif
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotale</span>
                            <span>€ {{ number_format($subtotal, 3, ',', '.') }}</span>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Sconti web</span>
                            <span class="text-success">-€ {{ number_format($discountTotal, 3, ',', '.') }}</span>
                        </div>

                        @if($appliedPromotions->isNotEmpty() || $appliedCoupons->isNotEmpty())
                            <div class="mb-3">
                                @foreach($appliedPromotions as $promotion)
                                    <div class="small text-success d-flex justify-content-between gap-2">
                                        <span>{{ $promotion['name'] ?? $promotion['code'] ?? 'Promozione' }}</span>
                                        <span>-€ {{ number_format((float) ($promotion['discount_total'] ?? 0), 3, ',', '.') }}</span>
                                    </div>
                                @endforeach

                                @foreach($appliedCoupons as $coupon)
                                    <div class="small text-success d-flex justify-content-between gap-2">
                                        <span>Coupon {{ $coupon['code'] ?? '' }}</span>
                                        <span>-€ {{ number_format((float) ($coupon['discount_total'] ?? 0), 3, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="d-flex justify-content-between mb-1">
                            <span>Spedizione</span>
                            <span>
                                @if(!$shippingAvailable)
                                    <span class="text-danger">Non disponibile</span>
                                @elseif($shippingIsFree)
                                    Gratis
                                @else
                                    € {{ number_format($shippingTotal, 3, ',', '.') }}
                                @endif
                            </span>
                        </div>

                        @if($shippingMessage !== '')
                            <div class="small {{ $shippingAvailable ? 'text-muted' : 'text-danger' }} mb-3">
                                {{ $shippingMessage }}
                            </div>
                        @else
                            <div class="mb-3"></div>
                        @endif

                        <hr>

                        <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                            <span>Totale finale</span>
                            <span>€ {{ number_format($grandTotal, 3, ',', '.') }}</span>
                        </div>

                        <div class="text-muted small mb-3">
                            {{ $isB2b
                                ? 'L\'ordine verrà registrato e gestito secondo le condizioni commerciali del tuo account.'
                                : 'Compila i dati e completa il pagamento direttamente nel checkout.' }}
                        </div>

                        <div class="mb-3">
                            <label for="checkout_notes" class="form-label fw-semibold">Note ordine</label>
                            <textarea
                                name="notes"
                                id="checkout_notes"
                                rows="4"
                                form="checkout-place-form"
                                class="form-control @error('notes') is-invalid @enderror"
                                placeholder="Inserisci eventuali note per l'ordine"
                            >{{ old('notes', $cart->notes) }}</textarea>

                            @error('notes')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid gap-2">
                            <button
                                type="submit"
                                form="checkout-place-form"
                                class="btn btn-success"
                                {{ (($isB2b && $shippingAddresses->isEmpty()) || !$shippingAvailable) ? 'disabled' : '' }}
                            >
                                <i class="fa-solid fa-check me-2"></i>
                                {{ $isB2b ? 'Conferma ordine' : 'Conferma e paga' }}
                            </button>

                            <a href="{{ route('storefront.cart.index') }}" class="btn btn-outline-secondary">
                                Modifica carrello
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
    @if($isB2b)
        <script src="{{ asset('js/checkout-b2b.js') }}" defer></script>
    @else
        <script src="{{ asset('js/checkout-b2c.js') }}" defer></script>
    @endif
@endpush
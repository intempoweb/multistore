<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="billing_same_as_shipping" name="billing_same_as_shipping" value="1" form="checkout-place-form" {{ $billingSameAsShipping ? 'checked' : '' }} data-billing-same-as-shipping>
            <label class="form-check-label fw-semibold" for="billing_same_as_shipping">
                {{ __('themes_b2c.checkout.billing_same_as_shipping') }}
            </label>
        </div>

        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="billing_request_invoice" name="billing_request_invoice" value="1" form="checkout-place-form" {{ $requestInvoice ? 'checked' : '' }} data-invoice-toggle>
            <label class="form-check-label fw-semibold" for="billing_request_invoice">
                {{ __('themes_b2c.checkout.request_invoice') }}
            </label>
        </div>

        <div data-billing-wrapper>
            <h5 class="mb-3">{{ __('themes_b2c.checkout.billing') }}</h5>

            <div class="row g-3" data-billing-fields>
                <div class="col-md-6">
                    <label for="billing_first_name" class="form-label">{{ __('themes_b2c.form.name') }}</label>
                    <input type="text" name="billing_first_name" id="billing_first_name" form="checkout-place-form" class="form-control @error('billing_first_name') is-invalid @enderror" value="{{ old('billing_first_name', $b2cBilling['first_name'] ?? '') }}" data-billing-input>
                    @error('billing_first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label for="billing_last_name" class="form-label">{{ __('themes_b2c.form.surname') }}</label>
                    <input type="text" name="billing_last_name" id="billing_last_name" form="checkout-place-form" class="form-control @error('billing_last_name') is-invalid @enderror" value="{{ old('billing_last_name', $b2cBilling['last_name'] ?? '') }}" data-billing-input>
                    @error('billing_last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label for="billing_email" class="form-label">{{ __('themes_b2c.checkout.billing_email') }}</label>
                    <input type="email" name="billing_email" id="billing_email" form="checkout-place-form" class="form-control @error('billing_email') is-invalid @enderror" value="{{ old('billing_email', $b2cBilling['email'] ?? '') }}" data-billing-input>
                    @error('billing_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label for="billing_address_line_1" class="form-label">{{ __('themes_b2c.checkout.billing_address') }}</label>
                    <input type="text" name="billing_address_line_1" id="billing_address_line_1" form="checkout-place-form" class="form-control @error('billing_address_line_1') is-invalid @enderror" value="{{ old('billing_address_line_1', $b2cBilling['address_line_1'] ?? '') }}" data-billing-input>
                    @error('billing_address_line_1') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-4">
                    <label for="billing_postcode" class="form-label">{{ __('themes_b2c.form.postcode') }}</label>
                    <input type="text" name="billing_postcode" id="billing_postcode" form="checkout-place-form" class="form-control @error('billing_postcode') is-invalid @enderror" value="{{ old('billing_postcode', $b2cBilling['postcode'] ?? '') }}" data-billing-input>
                    @error('billing_postcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-5">
                    <label for="billing_city" class="form-label">{{ __('themes_b2c.form.city') }}</label>
                    <input type="text" name="billing_city" id="billing_city" form="checkout-place-form" class="form-control @error('billing_city') is-invalid @enderror" value="{{ old('billing_city', $b2cBilling['city'] ?? '') }}" data-billing-input>
                    @error('billing_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label for="billing_province" class="form-label">{{ __('themes_b2c.form.province_abbr') }}</label>
                    <input type="text" name="billing_province" id="billing_province" form="checkout-place-form" class="form-control @error('billing_province') is-invalid @enderror" value="{{ old('billing_province', $b2cBilling['province'] ?? '') }}" data-billing-input>
                    @error('billing_province') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label for="billing_country" class="form-label">{{ __('themes_b2c.checkout.billing_country') }}</label>

                    @if($availableCountries->isNotEmpty())
                        <select name="billing_country" id="billing_country" form="checkout-place-form" class="form-select @error('billing_country') is-invalid @enderror" data-billing-input>
                            <option value="">{{ __('themes_b2c.checkout.use_shipping_country') }}</option>
                            @foreach($availableCountries as $country)
                                <option value="{{ $country['code'] }}" @selected(old('billing_country', $b2cBilling['country'] ?? '') === $country['code'])>
                                    {{ $country['label'] ?? $country['code'] }}
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

        <div id="invoice-fields" class="{{ $requestInvoice ? '' : 'd-none' }} mt-3" data-invoice-fields>
            <div class="row g-3">
                <div class="col-12">
                    <label for="billing_company" class="form-label">{{ __('themes_b2c.checkout.company_name') }}</label>
                    <input type="text" name="billing_company" id="billing_company" form="checkout-place-form" class="form-control @error('billing_company') is-invalid @enderror" value="{{ old('billing_company', $b2cBilling['company'] ?? '') }}" data-invoice-input>
                    @error('billing_company') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label for="billing_tax_code" class="form-label">{{ __('themes_b2c.checkout.tax_code') }}</label>
                    <input type="text" name="billing_tax_code" id="billing_tax_code" form="checkout-place-form" class="form-control @error('billing_tax_code') is-invalid @enderror" value="{{ old('billing_tax_code', $b2cBilling['tax_code'] ?? '') }}" data-invoice-input>
                    @error('billing_tax_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label for="billing_vat_number" class="form-label">{{ __('themes_b2c.checkout.vat_number') }}</label>
                    <input type="text" name="billing_vat_number" id="billing_vat_number" form="checkout-place-form" class="form-control @error('billing_vat_number') is-invalid @enderror" value="{{ old('billing_vat_number', $b2cBilling['vat_number'] ?? '') }}" data-invoice-input>
                    @error('billing_vat_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label for="billing_sdi" class="form-label">{{ __('themes_b2c.checkout.sdi_code') }}</label>
                    <input type="text" name="billing_sdi" id="billing_sdi" form="checkout-place-form" class="form-control @error('billing_sdi') is-invalid @enderror" value="{{ old('billing_sdi', $b2cBilling['sdi'] ?? '') }}" data-invoice-input>
                    @error('billing_sdi') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label for="billing_pec" class="form-label">{{ __('themes_b2c.checkout.pec') }}</label>
                    <input type="text" name="billing_pec" id="billing_pec" form="checkout-place-form" class="form-control @error('billing_pec') is-invalid @enderror" value="{{ old('billing_pec', $b2cBilling['pec'] ?? '') }}" data-invoice-input>
                    @error('billing_pec') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>
</div>
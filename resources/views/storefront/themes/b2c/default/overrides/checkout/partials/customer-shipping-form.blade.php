<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="mb-3">{{ __('themes_b2c.checkout.shipping_details') }}</h5>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="shipping_first_name" class="form-label">{{ __('themes_b2c.form.name') }}</label>
                <input type="text" name="shipping_first_name" id="shipping_first_name" form="checkout-place-form" class="form-control @error('shipping_first_name') is-invalid @enderror" value="{{ old('shipping_first_name', $b2cShipping['first_name'] ?? '') }}" required>
                @error('shipping_first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="shipping_last_name" class="form-label">{{ __('themes_b2c.form.surname') }}</label>
                <input type="text" name="shipping_last_name" id="shipping_last_name" form="checkout-place-form" class="form-control @error('shipping_last_name') is-invalid @enderror" value="{{ old('shipping_last_name', $b2cShipping['last_name'] ?? '') }}" required>
                @error('shipping_last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label for="shipping_email" class="form-label">{{ __('themes_b2c.form.email') }}</label>
                <input type="email" name="shipping_email" id="shipping_email" form="checkout-place-form" class="form-control @error('shipping_email') is-invalid @enderror" value="{{ old('shipping_email', $b2cShipping['email'] ?? '') }}" required data-checkout-account-email>
                @error('shipping_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label for="shipping_phone" class="form-label">{{ __('themes_b2c.form.phone') }}</label>
                <input type="text" name="shipping_phone" id="shipping_phone" form="checkout-place-form" class="form-control @error('shipping_phone') is-invalid @enderror" value="{{ old('shipping_phone', $b2cShipping['phone'] ?? '') }}">
                @error('shipping_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label for="shipping_address_line_1" class="form-label">{{ __('themes_b2c.checkout.shipping_address') }}</label>
                <input type="text" name="shipping_address_line_1" id="shipping_address_line_1" form="checkout-place-form" class="form-control @error('shipping_address_line_1') is-invalid @enderror" value="{{ old('shipping_address_line_1', $b2cShipping['address_line_1'] ?? '') }}" required>
                @error('shipping_address_line_1') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label for="shipping_postcode" class="form-label">{{ __('themes_b2c.form.postcode') }}</label>
                <input type="text" name="shipping_postcode" id="shipping_postcode" form="checkout-place-form" class="form-control @error('shipping_postcode') is-invalid @enderror" value="{{ old('shipping_postcode', $b2cShipping['postcode'] ?? '') }}" required>
                @error('shipping_postcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-5">
                <label for="shipping_city" class="form-label">{{ __('themes_b2c.form.city') }}</label>
                <input type="text" name="shipping_city" id="shipping_city" form="checkout-place-form" class="form-control @error('shipping_city') is-invalid @enderror" value="{{ old('shipping_city', $b2cShipping['city'] ?? '') }}" required>
                @error('shipping_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="shipping_province" class="form-label">{{ __('themes_b2c.form.province_abbr') }}</label>
                <input type="text" name="shipping_province" id="shipping_province" form="checkout-place-form" class="form-control @error('shipping_province') is-invalid @enderror" value="{{ old('shipping_province', $b2cShipping['province'] ?? '') }}">
                @error('shipping_province') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label for="shipping_country" class="form-label">{{ __('themes_b2c.checkout.shipping_country') }}</label>

                @if($availableCountries->isNotEmpty())
                    <select name="shipping_country" id="shipping_country" form="checkout-place-form" class="form-select @error('shipping_country') is-invalid @enderror" required>
                        @foreach($availableCountries as $country)
                            <option value="{{ $country['code'] }}" @selected(old('shipping_country', $b2cShipping['country'] ?? 'ITA') === $country['code'])>
                                {{ $country['label'] ?? $country['code'] }}
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

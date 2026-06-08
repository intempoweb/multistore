<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h5 class="mb-0">Spedizione</h5>
            <div class="small text-muted">{{ $shippingAddresses->count() }} indirizzi</div>
        </div>

        @if($shippingAddresses->isNotEmpty())
            <div class="d-flex flex-column gap-3">
                @foreach($shippingAddresses as $address)
                    <label
                        class="card border {{ $selectedShippingAddressId === (string) $address->id ? 'border-primary shadow-sm' : 'border-light-subtle' }} rounded-3 h-100 checkout-shipping-card"
                        data-shipping-card
                    >
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
                                        {{ $selectedShippingAddressId === (string) $address->id ? 'checked' : '' }}
                                        required
                                        data-shipping-radio
                                        data-shipping-address-id="{{ $address->id }}"
                                    >

                                    <span class="form-check-label fw-semibold">
                                        {{ $address->destragsoc_mg22 ?: $address->display_name ?: ('Destinazione ' . $address->coddestin_mg22) }}
                                    </span>
                                </div>

                                <span class="badge text-bg-primary {{ $selectedShippingAddressId === (string) $address->id ? '' : 'd-none' }}" data-shipping-selected-badge>
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
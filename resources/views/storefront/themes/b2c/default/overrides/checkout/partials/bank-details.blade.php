<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="mb-3">{{ __('themes_b2c.checkout.bank_details') }}</h5>

        @if($hasBankData)
            <div class="border rounded-3 p-3 bg-light-subtle">
                <div class="small text-muted d-flex flex-column gap-2">
                    @if($bank['name'] !== '')
                        <div><strong>{{ __('themes_b2c.checkout.bank') }}:</strong> {{ $bank['name'] }}</div>
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
                {{ __('themes_b2c.checkout.bank_details_unavailable') }}
            </div>
        @endif
    </div>
</div>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="mb-3">Fatturazione</h5>

        @if($hasBillingData)
            <div class="border rounded-3 p-3 bg-light-subtle h-100">
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
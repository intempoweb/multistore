@php
    /** @var \App\Models\Coupon|null $coupon */
    /** @var \Illuminate\Support\Collection|\App\Models\Promotion[] $promotions */

    $coupon = $coupon ?? null;
    $promotions = collect($promotions ?? []);
    $submitLabel = $submitLabel ?? 'Salva coupon';

    $selectedPromotionId = old('promotion_id', $coupon?->promotion_id);
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-2">Controlla i dati inseriti:</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Dati coupon</h2>

                <div class="alert alert-light border small">
                    <div><strong>Store:</strong> {{ $store->name ?? 'Store corrente' }}</div>
                    <div><strong>Ditta / Site:</strong> {{ $store->ditta_cg18 ?? '—' }} / {{ $store->erp_site_code ?? '—' }}</div>
                </div>

                <div class="mb-3">
                    <label for="code" class="form-label">Codice coupon</label>
                    <input
                        type="text"
                        name="code"
                        id="code"
                        class="form-control @error('code') is-invalid @enderror"
                        value="{{ old('code', $coupon?->code) }}"
                        placeholder="Es. MTBUONO50"
                        maxlength="80"
                        required
                    >
                    <div class="form-text">
                        Se non selezioni una promozione, verrà creata/riusata automaticamente una promo con lo stesso codice.
                    </div>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="promotion_id" class="form-label">Promozione collegata</label>
                    <select
                        name="promotion_id"
                        id="promotion_id"
                        class="form-select @error('promotion_id') is-invalid @enderror"
                    >
                        <option value="">Crea / riusa promozione dal codice coupon</option>

                        @foreach($promotions as $promotion)
                            <option value="{{ $promotion->id }}" @selected((string) $selectedPromotionId === (string) $promotion->id)>
                                #{{ $promotion->id }} — {{ $promotion->name }}
                                @if($promotion->code)
                                    ({{ $promotion->code }})
                                @endif
                                — {{ $promotion->discount_type }} {{ number_format((float) $promotion->discount_value, 3, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                    @error('promotion_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check form-switch mt-4">
                    <input type="hidden" name="is_active" value="0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="is_active"
                        id="is_active"
                        value="1"
                        @checked(old('is_active', $coupon?->is_active ?? true))
                    >
                    <label class="form-check-label fw-semibold" for="is_active">
                        Coupon attivo
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Limiti e validità</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="usage_limit" class="form-label">Limite utilizzi totale</label>
                        <input
                            type="number"
                            name="usage_limit"
                            id="usage_limit"
                            class="form-control @error('usage_limit') is-invalid @enderror"
                            value="{{ old('usage_limit', $coupon?->usage_limit) }}"
                            min="1"
                            step="1"
                            placeholder="Illimitato"
                        >
                        @error('usage_limit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="usage_limit_per_customer" class="form-label">Limite per cliente</label>
                        <input
                            type="number"
                            name="usage_limit_per_customer"
                            id="usage_limit_per_customer"
                            class="form-control @error('usage_limit_per_customer') is-invalid @enderror"
                            value="{{ old('usage_limit_per_customer', $coupon?->usage_limit_per_customer) }}"
                            min="1"
                            step="1"
                            placeholder="Default motore"
                        >
                        @error('usage_limit_per_customer')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="starts_at" class="form-label">Valido dal</label>
                        <input
                            type="datetime-local"
                            name="starts_at"
                            id="starts_at"
                            class="form-control @error('starts_at') is-invalid @enderror"
                            value="{{ old('starts_at', optional($coupon?->starts_at)->format('Y-m-d\TH:i')) }}"
                        >
                        @error('starts_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="expires_at" class="form-label">Valido fino al</label>
                        <input
                            type="datetime-local"
                            name="expires_at"
                            id="expires_at"
                            class="form-control @error('expires_at') is-invalid @enderror"
                            value="{{ old('expires_at', optional($coupon?->expires_at)->format('Y-m-d\TH:i')) }}"
                        >
                        @error('expires_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                @if($coupon?->exists)
                    <hr class="my-4">

                    <div class="small text-muted">
                        <div><strong>Utilizzi registrati:</strong> {{ (int) ($coupon->used_count ?? 0) }}</div>
                        <div><strong>ID coupon:</strong> {{ $coupon->id }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-4">
    <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-secondary">
        Annulla
    </a>

    <button type="submit" class="btn btn-primary">
        {{ $submitLabel }}
    </button>
</div>
@php
    /** @var \App\Models\Promotion|null $promotion */
    $promotion = $promotion ?? null;
    $submitLabel = $submitLabel ?? 'Salva promozione';
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Errore:</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">

    {{-- COLONNA SINISTRA --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">

                <h5 class="mb-3">Dati base</h5>

                {{-- NAME --}}
                <div class="mb-3">
                    <label class="form-label">Nome promozione *</label>
                    <input type="text" name="name"
                        class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name', $promotion?->name) }}" required>
                </div>

                {{-- CODE --}}
                <div class="mb-3">
                    <label class="form-label">Codice (opzionale)</label>
                    <input type="text" name="code"
                        class="form-control @error('code') is-invalid @enderror"
                        value="{{ old('code', $promotion?->code) }}">
                </div>

                {{-- DISCOUNT TYPE --}}
                <div class="mb-3">
                    <label class="form-label">Tipo sconto *</label>
                    <select name="discount_type"
                        class="form-select @error('discount_type') is-invalid @enderror" required>
                        <option value="fixed" @selected(old('discount_type', $promotion?->discount_type) === 'fixed')>
                            Importo fisso (€)
                        </option>
                        <option value="percent" @selected(old('discount_type', $promotion?->discount_type) === 'percent')>
                            Percentuale (%)
                        </option>
                    </select>
                </div>

                {{-- DISCOUNT VALUE --}}
                <div class="mb-3">
                    <label class="form-label">Valore sconto *</label>
                    <input type="number" step="0.001" name="discount_value"
                        class="form-control @error('discount_value') is-invalid @enderror"
                        value="{{ old('discount_value', $promotion?->discount_value) }}" required>
                </div>

                {{-- SCOPE --}}
                <div class="mb-3">
                    <label class="form-label">Ambito</label>
                    <select name="scope" class="form-select">
                        <option value="cart" @selected(old('scope', $promotion?->scope) === 'cart')>
                            Carrello
                        </option>
                        <option value="line" @selected(old('scope', $promotion?->scope) === 'line')>
                            Riga prodotto
                        </option>
                    </select>
                </div>

                {{-- ACTIVE --}}
                <div class="form-check form-switch mt-3">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1"
                        @checked(old('is_active', $promotion?->is_active ?? true))>
                    <label class="form-check-label">Attiva</label>
                </div>

            </div>
        </div>
    </div>

    {{-- COLONNA DESTRA --}}
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">

                <h5 class="mb-3">Condizioni</h5>

                {{-- MIN SUBTOTAL --}}
                <div class="mb-3">
                    <label class="form-label">Minimo carrello (€)</label>
                    <input type="number" step="0.01" name="minimum_subtotal"
                        class="form-control"
                        value="{{ old('minimum_subtotal', $promotion?->minimum_subtotal) }}">
                </div>

                {{-- COUPON REQUIRED --}}
                <div class="form-check mb-2">
                    <input type="checkbox" name="requires_coupon" value="1"
                        class="form-check-input"
                        @checked(old('requires_coupon'))>
                    <label class="form-check-label">
                        Richiede coupon
                    </label>
                </div>

                {{-- COUPON CODES --}}
                <div class="mb-3">
                    <label class="form-label">Codici coupon (uno per riga)</label>
                    <textarea name="coupon_codes" rows="3"
                        class="form-control">{{ old('coupon_codes') }}</textarea>
                </div>

                {{-- LIMIT PER CUSTOMER --}}
                <div class="mb-3">
                    <label class="form-label">Limite per cliente</label>
                    <input type="number" name="usage_limit_per_customer"
                        class="form-control"
                        value="{{ old('usage_limit_per_customer') }}">
                </div>

                {{-- PRIORITY --}}
                <div class="mb-3">
                    <label class="form-label">Priorità</label>
                    <input type="number" name="priority"
                        class="form-control"
                        value="{{ old('priority', $promotion?->priority ?? 0) }}">
                </div>

                {{-- DATES --}}
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Dal</label>
                        <input type="datetime-local" name="starts_at"
                            class="form-control"
                            value="{{ old('starts_at', optional($promotion?->starts_at)->format('Y-m-d\TH:i')) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Al</label>
                        <input type="datetime-local" name="ends_at"
                            class="form-control"
                            value="{{ old('ends_at', optional($promotion?->ends_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<div class="mt-4 d-flex justify-content-between">
    <a href="{{ route('admin.promotions.index') }}" class="btn btn-outline-secondary">
        Annulla
    </a>

    <button type="submit" class="btn btn-primary">
        {{ $submitLabel }}
    </button>
</div>
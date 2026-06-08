@php
    /** @var \App\Models\ShippingRule|null $rule */
    $rule = $rule ?? null;
    $submitLabel = $submitLabel ?? 'Salva';
    $isB2c = ($store->is_b2b ?? false) === false;
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
        <h2 class="h5 mb-3">Contesto corrente</h2>

        <div class="alert alert-light border">
            <div class="small text-muted mb-1">Store</div>
            <div class="fw-semibold">{{ $store->name ?? 'Store corrente' }}</div>

            <div class="small text-muted mt-3 mb-1">Ditta / Site</div>
            <div class="fw-semibold">
                {{ $store->ditta_cg18 ?? '—' }} / {{ $store->erp_site_code ?? '—' }}
                <span class="ms-2 badge {{ $isB2c ? 'text-bg-info' : 'text-bg-secondary' }}">
                    {{ $isB2c ? 'B2C' : 'B2B' }}
                </span>
            </div>
        </div>

        <h2 class="h5 mb-3 mt-4">Tipologia regola</h2>

        <div class="mb-3">
            <label for="type" class="form-label">Tipo</label>
            <select
                name="type"
                id="type"
                class="form-select @error('type') is-invalid @enderror"
                required
            >
                <option value="fixed" @selected(old('type', $rule?->type ?? 'fixed') === 'fixed')>fixed</option>
                <option value="free_over" @selected(old('type', $rule?->type) === 'free_over')>free_over</option>
                <option value="table" @selected(old('type', $rule?->type) === 'table')>table</option>
            </select>
            @error('type')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            <div class="form-text">
                <strong>free_over</strong> serve per spedizione gratuita sopra soglia.
            </div>
        </div>

        <div class="mb-3">
            <label for="country" class="form-label">Nazione</label>
            <input
                type="text"
                name="country"
                id="country"
                class="form-control @error('country') is-invalid @enderror"
                value="{{ old('country', $rule?->country) }}"
                placeholder="Es: ITA, IT, FRA oppure vuoto per ALL"
                maxlength="3"
            >
            @error('country')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label for="province" class="form-label">Provincia</label>
                <input
                    type="text"
                    name="province"
                    id="province"
                    class="form-control @error('province') is-invalid @enderror"
                    value="{{ old('province', $rule?->province) }}"
                    placeholder="Es: MI"
                    maxlength="20"
                >
                @error('province')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="cap" class="form-label">CAP</label>
                <input
                    type="text"
                    name="cap"
                    id="cap"
                    class="form-control @error('cap') is-invalid @enderror"
                    value="{{ old('cap', $rule?->cap) }}"
                    placeholder="Es: 201*"
                    maxlength="20"
                >
                @error('cap')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="weight_from" class="form-label">Peso ≥ kg</label>
                <input
                    type="number"
                    step="0.001"
                    min="0"
                    name="weight_from"
                    id="weight_from"
                    class="form-control @error('weight_from') is-invalid @enderror"
                    value="{{ old('weight_from', $rule?->weight_from) }}"
                >
                @error('weight_from')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <h2 class="h5 mb-3">Condizioni</h2>

        <div class="row g-3">
            <div class="col-md-4">
                <label for="min_amount" class="form-label">Min €</label>
                <input
                    type="number"
                    step="0.001"
                    min="0"
                    name="min_amount"
                    id="min_amount"
                    class="form-control @error('min_amount') is-invalid @enderror"
                    value="{{ old('min_amount', $rule?->min_amount) }}"
                    placeholder="Es: 60.000"
                >
                @error('min_amount')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="max_amount" class="form-label">Max €</label>
                <input
                    type="number"
                    step="0.001"
                    min="0"
                    name="max_amount"
                    id="max_amount"
                    class="form-control @error('max_amount') is-invalid @enderror"
                    value="{{ old('max_amount', $rule?->max_amount) }}"
                >
                @error('max_amount')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="amount" class="form-label">Costo spedizione €</label>
                <input
                    type="number"
                    step="0.001"
                    min="0"
                    name="amount"
                    id="amount"
                    class="form-control @error('amount') is-invalid @enderror"
                    value="{{ old('amount', $rule?->amount) }}"
                    placeholder="0.000 per gratis"
                >
                @error('amount')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <hr class="my-4">

        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="priority" class="form-label">Priorità</label>
                <input
                    type="number"
                    min="0"
                    name="priority"
                    id="priority"
                    class="form-control @error('priority') is-invalid @enderror"
                    value="{{ old('priority', $rule?->priority ?? 0) }}"
                >
                @error('priority')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <div class="form-check mt-4">
                    <input type="hidden" name="is_active" value="0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="is_active"
                        id="is_active"
                        value="1"
                        @checked(old('is_active', $rule?->is_active ?? true))
                    >
                    <label class="form-check-label" for="is_active">Attiva</label>
                </div>
            </div>
        </div>

        <div class="alert alert-info mt-4 mb-0 small">
            <strong>B2B fixed:</strong> usa min/max/importo.<br>
            <strong>B2C table:</strong> usa nazione/provincia/CAP/peso/costo e viene normalmente da CSV.<br>
            <strong>B2C free_over:</strong> usa nazione + min € + costo 0. Esempi:
            <code>ITA + 60.000</code>, Europa + <code>120.000</code>.
        </div>
    </div>
</div>

<div class="d-flex justify-content-between mt-4">
    <a href="{{ route('admin.shipping-rules.index') }}" class="btn btn-outline-secondary">
        Annulla
    </a>

    <button type="submit" class="btn btn-primary">
        {{ $submitLabel }}
    </button>
</div>
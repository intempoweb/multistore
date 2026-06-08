@extends('layouts.admin')

@section('title', 'Modifica regola di spedizione')
@section('breadcrumb', 'Modifica regola di spedizione')

@section('content')
@php
    $isB2c = ($store->is_b2b ?? false) === false;
@endphp

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">Modifica regola di spedizione</h1>
            <p class="text-muted mb-1">Aggiorna la configurazione della regola selezionata.</p>

            @isset($store)
                <div class="small text-muted">
                    <strong>{{ $store->name }}</strong>
                    <span class="mx-1">•</span>
                    Ditta {{ $store->ditta_cg18 }}
                    <span class="mx-1">•</span>
                    Site {{ $store->erp_site_code }}
                    <span class="mx-1">•</span>
                    {{ $isB2c ? 'B2C' : 'B2B' }}
                </div>
            @endisset
        </div>

        <a href="{{ route('admin.shipping-rules.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Torna alla lista
        </a>
    </div>

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

    @if($isB2c && $rule->type === 'table')
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            Regola <strong>B2C CSV</strong>.<br>
            Campi usati: <code>Nazione • Provincia • CAP • Peso ≥ • Costo</code><br>
            <span class="small">Un nuovo import CSV può sovrascrivere queste modifiche manuali.</span>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.shipping-rules.update', $rule) }}">
                @csrf
                @method('PUT')

                <div class="row g-4">
                    <div class="col-md-3">
                        <label for="type" class="form-label fw-bold small">Tipo</label>
                        <select
                            name="type"
                            id="type"
                            class="form-select @error('type') is-invalid @enderror"
                            required
                        >
                            <option value="fixed" @selected(old('type', $rule->type) === 'fixed')>fixed</option>
                            <option value="free_over" @selected(old('type', $rule->type) === 'free_over')>free_over</option>
                            <option value="table" @selected(old('type', $rule->type) === 'table')>table</option>
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="country" class="form-label fw-bold small">Nazione ISO</label>
                        <input
                            type="text"
                            name="country"
                            id="country"
                            class="form-control @error('country') is-invalid @enderror"
                            value="{{ old('country', $rule->country) }}"
                            placeholder="Es: IT oppure ITA"
                        >
                        @error('country')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="province" class="form-label fw-bold small">Provincia</label>
                        <input
                            type="text"
                            name="province"
                            id="province"
                            class="form-control @error('province') is-invalid @enderror"
                            value="{{ old('province', $rule->province) }}"
                            placeholder="Es: MI"
                        >
                        @error('province')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="cap" class="form-label fw-bold small">CAP</label>
                        <input
                            type="text"
                            name="cap"
                            id="cap"
                            class="form-control @error('cap') is-invalid @enderror"
                            value="{{ old('cap', $rule->cap) }}"
                            placeholder="Es: 20100 o 201*"
                        >
                        @error('cap')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <hr class="my-0">
                    </div>

                    <div class="col-md-3">
                        <label for="weight_from" class="form-label fw-bold small">Peso ≥ kg</label>
                        <input
                            type="number"
                            step="0.001"
                            min="0"
                            name="weight_from"
                            id="weight_from"
                            class="form-control @error('weight_from') is-invalid @enderror"
                            value="{{ old('weight_from', $rule->weight_from) }}"
                        >
                        @error('weight_from')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="min_amount" class="form-label fw-bold small">Min Carrello €</label>
                        <input
                            type="number"
                            step="0.001"
                            min="0"
                            name="min_amount"
                            id="min_amount"
                            class="form-control @error('min_amount') is-invalid @enderror"
                            value="{{ old('min_amount', $rule->min_amount) }}"
                        >
                        @error('min_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="max_amount" class="form-label fw-bold small">Max Carrello €</label>
                        <input
                            type="number"
                            step="0.001"
                            min="0"
                            name="max_amount"
                            id="max_amount"
                            class="form-control @error('max_amount') is-invalid @enderror"
                            value="{{ old('max_amount', $rule->max_amount) }}"
                        >
                        @error('max_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="amount" class="form-label fw-bold small text-primary">Costo spedizione €</label>
                        <input
                            type="number"
                            step="0.001"
                            min="0"
                            name="amount"
                            id="amount"
                            class="form-control border-primary @error('amount') is-invalid @enderror"
                            value="{{ old('amount', $rule->amount) }}"
                        >
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="priority" class="form-label fw-bold small">Priorità</label>
                        <input
                            type="number"
                            min="0"
                            name="priority"
                            id="priority"
                            class="form-control @error('priority') is-invalid @enderror"
                            value="{{ old('priority', $rule->priority ?? 0) }}"
                        >
                        @error('priority')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch p-3 border rounded">
                            <input type="hidden" name="is_active" value="0">
                            <input
                                type="checkbox"
                                name="is_active"
                                id="is_active"
                                value="1"
                                class="form-check-input ms-0 me-2"
                                @checked(old('is_active', $rule->is_active))
                            >
                            <label class="form-check-label fw-bold" for="is_active">Regola attiva</label>
                            <span class="d-block small text-muted">
                                Se disattivata, la regola non verrà applicata al checkout.
                            </span>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info mb-0 small">
                            <strong>fixed:</strong> usa costo spedizione e opzionalmente nazione/min/max.<br>
                            <strong>free_over:</strong> usa nazione e min carrello; costo normalmente <code>0.000</code>.<br>
                            <strong>table:</strong> usa nazione/provincia/CAP/peso/costo, normalmente importato da CSV.
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('admin.shipping-rules.index') }}" class="btn btn-outline-secondary">
                            Annulla
                        </a>

                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            Aggiorna regola
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
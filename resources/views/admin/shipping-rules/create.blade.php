@extends('layouts.admin')

@section('title', 'Nuova regola di spedizione')
@section('breadcrumb', 'Nuova regola di spedizione')

@section('content')
@php
    $isB2c = $store->isB2C();
@endphp

<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">Nuova regola di spedizione</h1>
        <p class="text-muted mb-1">Crea una nuova regola per lo store corrente.</p>

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

        @if($isB2c)
            <div class="alert alert-warning mt-3 mb-0">
                Store <strong>B2C</strong>:<br>
                le spedizioni principali vengono gestite da CSV (<code>table rate</code>).<br>
                Qui puoi creare regole <strong>aggiuntive</strong>, ad esempio:
                <strong>free_over</strong> per spedizione gratuita sopra una soglia.
            </div>
        @endif
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

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.shipping-rules.store') }}">
                @csrf

                <div class="row g-4">
                    <div class="col-md-3">
                        <label for="type" class="form-label fw-bold small">Tipo</label>
                        <select
                            name="type"
                            id="type"
                            class="form-select @error('type') is-invalid @enderror"
                            required
                        >
                            <option value="fixed" @selected(old('type', 'fixed') === 'fixed')>fixed</option>
                            <option value="free_over" @selected(old('type') === 'free_over')>free_over</option>
                            <option value="table" @selected(old('type') === 'table')>table</option>
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
                            value="{{ old('country') }}"
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
                            value="{{ old('province') }}"
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
                            value="{{ old('cap') }}"
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
                            value="{{ old('weight_from') }}"
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
                            value="{{ old('min_amount') }}"
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
                            value="{{ old('max_amount') }}"
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
                            value="{{ old('amount') }}"
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
                            value="{{ old('priority', 100) }}"
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
                                @checked(old('is_active', true))
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
                            Salva regola
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

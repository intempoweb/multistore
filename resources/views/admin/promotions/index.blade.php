@extends('layouts.admin')

@section('title', 'Promozioni')
@section('breadcrumb', 'Promozioni')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Promozioni</h1>
            <p class="text-muted mb-0">
                Gestisci sconti automatici e promozioni coupon.
            </p>
        </div>

        <a href="{{ route('admin.promotions.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>
            Nuova promozione
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.promotions.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Cerca</label>
                    <input
                        type="text"
                        name="search"
                        class="form-control form-control-sm"
                        value="{{ request('search') }}"
                        placeholder="Nome o codice"
                    >
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Tipo sconto</label>
                    <select name="discount_type" class="form-select form-select-sm">
                        <option value="">Tutti</option>
                        <option value="fixed" @selected(request('discount_type') === 'fixed')>Fisso</option>
                        <option value="percent" @selected(request('discount_type') === 'percent')>Percentuale</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Ambito</label>
                    <select name="scope" class="form-select form-select-sm">
                        <option value="">Tutti</option>
                        <option value="cart" @selected(request('scope') === 'cart')>Carrello</option>
                        <option value="line" @selected(request('scope') === 'line')>Riga</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Stato</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Tutti</option>
                        <option value="active" @selected(request('status') === 'active')>Attive</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Disattive</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button class="btn btn-sm btn-primary flex-grow-1" type="submit">
                        Filtra
                    </button>

                    @if(request()->query())
                        <a href="{{ route('admin.promotions.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($promotions->isEmpty())
                <div class="p-4 text-center text-muted">
                    Nessuna promozione trovata.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Codice</th>
                                <th>Tipo</th>
                                <th>Ambito</th>
                                <th class="text-end">Valore</th>
                                <th class="text-end">Min €</th>
                                <th class="text-center">Stato</th>
                                <th class="text-end px-4">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($promotions as $promotion)
                                <tr>
                                    <td>{{ $promotion->id }}</td>
                                    <td>
                                        <strong>{{ $promotion->name }}</strong>
                                        <div class="small text-muted">
                                            Priorità {{ $promotion->priority ?? 0 }}
                                        </div>
                                    </td>
                                    <td>{{ $promotion->code ?: '—' }}</td>
                                    <td>{{ $promotion->discount_type ?: '—' }}</td>
                                    <td>{{ $promotion->scope ?: '—' }}</td>
                                    <td class="text-end">
                                        @if($promotion->discount_type === 'percent')
                                            {{ number_format((float) $promotion->discount_value, 2, ',', '.') }}%
                                        @else
                                            € {{ number_format((float) $promotion->discount_value, 3, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        {{ $promotion->minimum_subtotal !== null ? '€ ' . number_format((float) $promotion->minimum_subtotal, 3, ',', '.') : '—' }}
                                    </td>
                                    <td class="text-center">
                                        @if($promotion->is_active)
                                            <span class="badge text-bg-success">Attiva</span>
                                        @else
                                            <span class="badge text-bg-secondary">Off</span>
                                        @endif
                                    </td>
                                    <td class="text-end px-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('admin.promotions.edit', $promotion) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fa-solid fa-pencil"></i>
                                            </a>

                                            <form method="POST" action="{{ route('admin.promotions.destroy', $promotion) }}" onsubmit="return confirm('Eliminare questa promozione?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if($promotions->hasPages())
        <div class="mt-3">
            {{ $promotions->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
@extends('layouts.admin')

@section('title', 'Coupon')
@section('breadcrumb', 'Coupon')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Coupon</h1>
            <p class="text-muted mb-0">
                Gestisci i codici coupon per lo store corrente.
                @isset($store)
                    <span class="d-block d-md-inline">
                        <strong>{{ $store->name }}</strong>
                        <span class="mx-1">•</span>
                        Ditta {{ $store->ditta_cg18 }}
                        <span class="mx-1">•</span>
                        Site {{ $store->erp_site_code }}
                        <span class="mx-1">•</span>
                        {{ $store->channelLabel() }}
                    </span>
                @endisset
            </p>
        </div>

        <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>
            Nuovo coupon
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.coupons.index') }}" class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="search" class="form-label small fw-bold">Cerca codice</label>
                    <input
                        type="text"
                        name="search"
                        id="search"
                        class="form-control form-control-sm"
                        value="{{ request('search') }}"
                        placeholder="Es. MTBUONO50"
                    >
                </div>

                <div class="col-12 col-md-3">
                    <label for="status" class="form-label small fw-bold">Stato</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">Tutti</option>
                        <option value="active" @selected(request('status') === 'active')>Attivi</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Disattivi</option>
                    </select>
                </div>

                <div class="col-12 col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>
                        Filtra
                    </button>

                    @if(request()->anyFilled(['search', 'status']))
                        <a href="{{ route('admin.coupons.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($coupons->isEmpty())
                <div class="p-4 text-muted text-center">
                    <i class="fa-solid fa-ticket fa-2x mb-3 d-block opacity-25"></i>
                    Nessun coupon trovato.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Codice</th>
                                <th>Promozione</th>
                                <th class="text-end">Limite totale</th>
                                <th class="text-end">Usati</th>
                                <th class="text-end">Per cliente</th>
                                <th>Validità</th>
                                <th class="text-center">Stato</th>
                                <th class="text-end px-4">Azioni</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($coupons as $coupon)
                                <tr>
                                    <td>{{ $coupon->id }}</td>

                                    <td>
                                        <span class="fw-semibold">{{ $coupon->code }}</span>
                                    </td>

                                    <td>
                                        @if($coupon->promotion)
                                            <div class="fw-semibold">
                                                {{ $coupon->promotion->name }}
                                            </div>
                                            <div class="small text-muted">
                                                {{ $coupon->promotion->discount_type }}
                                                {{ number_format((float) $coupon->promotion->discount_value, 3, ',', '.') }}
                                            </div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td class="text-end">
                                        {{ $coupon->usage_limit !== null ? number_format((int) $coupon->usage_limit, 0, ',', '.') : '—' }}
                                    </td>

                                    <td class="text-end">
                                        {{ number_format((int) ($coupon->used_count ?? 0), 0, ',', '.') }}
                                    </td>

                                    <td class="text-end">
                                        {{ $coupon->usage_limit_per_customer !== null ? number_format((int) $coupon->usage_limit_per_customer, 0, ',', '.') : '—' }}
                                    </td>

                                    <td>
                                        <div class="small">
                                            <div>
                                                <span class="text-muted">Da:</span>
                                                {{ $coupon->starts_at ? $coupon->starts_at->format('d/m/Y H:i') : 'subito' }}
                                            </div>
                                            <div>
                                                <span class="text-muted">A:</span>
                                                {{ $coupon->expires_at ? $coupon->expires_at->format('d/m/Y H:i') : 'nessuna scadenza' }}
                                            </div>
                                        </div>
                                    </td>

                                    <td class="text-center">
                                        @if($coupon->is_active)
                                            <span class="badge text-bg-success">Attivo</span>
                                        @else
                                            <span class="badge text-bg-secondary">Off</span>
                                        @endif
                                    </td>

                                    <td class="text-end px-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a
                                                href="{{ route('admin.coupons.edit', $coupon) }}"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Modifica"
                                            >
                                                <i class="fa-solid fa-pencil"></i>
                                            </a>

                                            <form
                                                method="POST"
                                                action="{{ route('admin.coupons.destroy', $coupon) }}"
                                                onsubmit="return confirm('Sei sicuro di voler eliminare questo coupon?')"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Elimina">
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

    @if($coupons->hasPages())
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-footer bg-white">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div class="text-muted small">
                        Mostrati {{ $coupons->firstItem() ?: 0 }}–{{ $coupons->lastItem() ?: 0 }}
                        di {{ number_format($coupons->total(), 0, ',', '.') }} coupon
                    </div>

                    <div>
                        {{ $coupons->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

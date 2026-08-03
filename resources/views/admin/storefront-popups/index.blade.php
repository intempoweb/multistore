@extends('layouts.admin')

@section('title', 'Popup storefront')
@section('breadcrumb', 'Marketing / Popup storefront')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Popup storefront</h1>
            <p class="text-muted mb-0">
                Gestisci popup marketing per {{ $store->name }} · {{ $store->channelLabel() }} · Ditta {{ $store->ditta_cg18 }} / Site {{ $store->erp_site_code }}.
            </p>
        </div>

        <a href="{{ route('admin.storefront-popups.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>
            Nuovo popup
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
            <form method="GET" action="{{ route('admin.storefront-popups.index') }}" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label small fw-bold">Cerca</label>
                    <input
                        type="text"
                        name="search"
                        class="form-control form-control-sm"
                        value="{{ request('search') }}"
                        placeholder="Nome interno o titolo"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Visibilità</label>
                    <select name="scope" class="form-select form-select-sm">
                        <option value="">Tutte</option>
                        @foreach($scopeLabels as $scope => $label)
                            <option value="{{ $scope }}" @selected(request('scope') === $scope)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Stato</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Tutti</option>
                        <option value="active" @selected(request('status') === 'active')>Attivi</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Disattivi</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button class="btn btn-sm btn-primary flex-grow-1" type="submit">Filtra</button>

                    @if(request()->query())
                        <a href="{{ route('admin.storefront-popups.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($popups->isEmpty())
                <div class="p-4 text-center text-muted">
                    Nessun popup configurato per questo store.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Popup</th>
                                <th>Store</th>
                                <th>Visibilità</th>
                                <th>Frequenza</th>
                                <th class="text-end">Ritardo</th>
                                <th class="text-end">Priorità</th>
                                <th class="text-center">Stato</th>
                                <th class="text-end px-4">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($popups as $popup)
                                @php
                                    $localized = $popup->localized($store->defaultLocale('it'));
                                    $startsAt = $popup->starts_at?->timezone('Europe/Rome');
                                    $endsAt = $popup->ends_at?->timezone('Europe/Rome');
                                    $isScheduled = $popup->is_active && $popup->starts_at && $popup->starts_at->isFuture();
                                    $isExpired = $popup->is_active && $popup->ends_at && $popup->ends_at->isPast();
                                @endphp
                                <tr>
                                    <td>{{ $popup->id }}</td>
                                    <td>
                                        <strong>{{ $popup->name }}</strong>
                                        <div class="small text-muted">{{ $localized['title'] ?: 'Titolo non compilato' }}</div>
                                    </td>
                                    <td>
                                        @php($popupStores = $popup->stores->isNotEmpty() ? $popup->stores : collect([$store]))
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($popupStores as $popupStore)
                                                <span class="badge text-bg-light border">
                                                    {{ $popupStore->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>{{ $scopeLabels[$popup->display_scope] ?? $popup->display_scope }}</td>
                                    <td>{{ $frequencyLabels[$popup->frequency] ?? $popup->frequency }}</td>
                                    <td class="text-end">{{ number_format((int) $popup->delay_ms, 0, ',', '.') }} ms</td>
                                    <td class="text-end">{{ $popup->priority }}</td>
                                    <td class="text-center">
                                        @if(! $popup->is_active)
                                            <span class="badge text-bg-secondary">Off</span>
                                        @elseif($isScheduled)
                                            <span class="badge text-bg-warning">Programmato</span>
                                            <div class="small text-muted mt-1">
                                                Dal {{ $startsAt?->format('d/m/Y H:i') }}
                                            </div>
                                        @elseif($isExpired)
                                            <span class="badge text-bg-secondary">Scaduto</span>
                                            <div class="small text-muted mt-1">
                                                Al {{ $endsAt?->format('d/m/Y H:i') }}
                                            </div>
                                        @else
                                            <span class="badge text-bg-success">Attivo</span>
                                        @endif
                                    </td>
                                    <td class="text-end px-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('admin.storefront-popups.edit', $popup) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fa-solid fa-pencil"></i>
                                            </a>

                                            <form method="POST" action="{{ route('admin.storefront-popups.destroy', $popup) }}" onsubmit="return confirm('Eliminare questo popup?')">
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

    @if($popups->hasPages())
        <div class="mt-3">
            {{ $popups->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection

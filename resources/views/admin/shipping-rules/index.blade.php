@extends('layouts.admin')

@section('title', 'Regole di spedizione')
@section('breadcrumb', 'Regole di spedizione')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Regole di spedizione</h1>
            <p class="text-muted mb-0">
                Gestisci le regole di spedizione per lo store corrente.
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

        <div class="d-flex gap-2">
            <a href="{{ route('admin.shipping-rules.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i>
                Nuova regola
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- BARRA DI RICERCA E FILTRI --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ url()->current() }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search_country" class="form-label small fw-bold">Nazione (ISO)</label>
                    <input type="text" name="country" id="search_country" class="form-control form-control-sm"
                           placeholder="Es: IT" value="{{ request('country') }}">
                </div>
                <div class="col-md-3">
                    <label for="search_province" class="form-label small fw-bold">Provincia</label>
                    <input type="text" name="province" id="search_province" class="form-control form-control-sm"
                           placeholder="Es: MI" value="{{ request('province') }}">
                </div>
                <div class="col-md-3">
                    <label for="search_cap" class="form-label small fw-bold">CAP</label>
                    <input type="text" name="cap" id="search_cap" class="form-control form-control-sm"
                           placeholder="Cerca CAP..." value="{{ request('cap') }}">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Filtra
                    </button>
                    @if(request()->anyFilled(['country', 'province', 'cap']))
                        <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if($store->isB2C())
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5 mb-2">Condivisione listino spedizioni</h2>
                <div class="text-muted small mb-3">
                    Seleziona gli altri siti B2C della stessa ditta che devono usare il listino
                    <strong>table rate</strong> dello store corrente.
                </div>

                <form method="POST" action="{{ route('admin.shipping-rules.share.update') }}">
                    @csrf

                    @if(collect($shareableStores ?? [])->isEmpty())
                        <div class="alert alert-secondary mb-0">
                            Nessun altro store B2C disponibile per la condivisione del listino.
                        </div>
                    @else
                        <div class="row g-3">
                            @foreach($shareableStores as $shareableStore)
                                <div class="col-12 col-lg-6">
                                    <label class="border rounded p-3 d-flex align-items-start gap-3 w-100">
                                        <input
                                            class="form-check-input mt-1"
                                            type="checkbox"
                                            name="shared_store_ids[]"
                                            value="{{ $shareableStore->id }}"
                                            {{ in_array((int) $shareableStore->id, $sharedStoreIds ?? [], true) ? 'checked' : '' }}
                                        >

                                        <span>
                                            <span class="fw-semibold d-block">{{ $shareableStore->name }}</span>
                                            <span class="small text-muted d-block">
                                                Ditta {{ $shareableStore->ditta_cg18 }} • Site {{ $shareableStore->erp_site_code }}
                                            </span>
                                            @if(!empty($shareableStore->domain))
                                                <span class="small text-muted d-block">{{ $shareableStore->domain }}</span>
                                            @endif
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3 d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fa-solid fa-share-nodes me-1"></i>
                                Salva condivisione listino
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-2">Tabella spedizioni CSV</h2>
                    <div class="text-muted small">
                        CSV B2C: <code>Nazione | Provincia | CAP | Peso (e superiore) | Prezzo</code>
                    </div>
                </div>

                @if($store->isB2C())
                    <a
                        href="{{ route('admin.shipping-rules.import.export') }}"
                        class="btn btn-outline-secondary flex-shrink-0 {{ ($hasExportableTableRules ?? false) ? '' : 'disabled' }}"
                        @if(!($hasExportableTableRules ?? false)) aria-disabled="true" tabindex="-1" @endif
                    >
                        <i class="fa-solid fa-file-export me-1" aria-hidden="true"></i>
                        Esporta CSV
                    </a>
                @endif
            </div>
            @include('admin.shipping-rules._import_form')
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($rules->isEmpty())
                <div class="p-4 text-muted text-center">
                    <i class="fa-solid fa-inbox fa-2x mb-3 d-block opacity-25"></i>
                    Nessuna regola di spedizione trovata per i criteri selezionati.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Tipo</th>
                                <th>Nazione</th>
                                <th>Prov.</th>
                                <th>CAP</th>
                                <th class="text-end">Peso ≥</th>
                                <th class="text-end">Min €</th>
                                <th class="text-end">Max €</th>
                                <th class="text-end">Costo €</th>
                                <th class="text-center">Stato</th>
                                <th class="text-end px-4">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rules as $rule)
                                <tr>
                                    <td>{{ $rule->id }}</td>
                                    <td>
                                        <span class="badge text-bg-secondary">
                                            {{ $rule->type }}
                                        </span>
                                    </td>
                                    <td><strong>{{ $rule->country ?: 'ALL' }}</strong></td>
                                    <td>{{ $rule->province ?: '—' }}</td>
                                    <td>{{ $rule->cap ?: '—' }}</td>
                                    <td class="text-end">{{ $rule->weight_from !== null ? number_format($rule->weight_from, 3, ',', '.') : '—' }}</td>
                                    <td class="text-end">{{ $rule->min_amount !== null ? number_format($rule->min_amount, 3, ',', '.') : '—' }}</td>
                                    <td class="text-end">{{ $rule->max_amount !== null ? number_format($rule->max_amount, 3, ',', '.') : '—' }}</td>
                                    <td class="text-end">{{ $rule->amount !== null ? number_format($rule->amount, 3, ',', '.') : '—' }}</td>
                                    <td class="text-center">
                                        @if($rule->is_active)
                                            <span class="badge text-bg-success">Attiva</span>
                                        @else
                                            <span class="badge text-bg-secondary">Off</span>
                                        @endif
                                    </td>
                                    <td class="text-end px-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('admin.shipping-rules.edit', $rule) }}"
                                               class="btn btn-sm btn-outline-primary"
                                               title="Modifica">
                                                <i class="fa-solid fa-pencil"></i>
                                            </a>

                                            <form action="{{ route('admin.shipping-rules.destroy', $rule) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Sei sicuro di voler eliminare questa regola?')">
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
</div>

@if($rules->hasPages())
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-footer bg-white">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="text-muted small">
                    Mostrati {{ $rules->firstItem() ?: 0 }}–{{ $rules->lastItem() ?: 0 }}
                    di {{ number_format($rules->total(), 0, ',', '.') }} regole
                </div>
                <div>
                    {{ $rules->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

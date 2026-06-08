@extends('layouts.admin')

@section('title', 'Catalogo')
@section('breadcrumb', 'Catalogo')

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">Catalogo store</div>
        <h1 class="h3 mb-1">Categorie principali</h1>
        <div class="text-muted small">
            <strong>{{ $store->name }}</strong>
            <span class="mx-1">•</span>
            Ditta {{ $store->ditta_cg18 }}
            <span class="mx-1">•</span>
            Site {{ $store->erp_site_code }}
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <span class="badge rounded-pill text-bg-light border px-3 py-2">
            {{ number_format($famiglie->count(), 0, ',', '.') }} categorie
        </span>

        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-box me-1"></i>
            Prodotti
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0">
        <div>
            <h2 class="h5 mb-1">Categorie</h2>
            <div class="text-muted small">
                Vista commerciale del catalogo per lo store selezionato.
            </div>
        </div>
    </div>

    <div class="card-body">
        @if($famiglie->isEmpty())
            <div class="alert alert-light border mb-0">
                Nessuna categoria disponibile per lo store corrente.
            </div>
        @else
            <div class="row g-3">
                @foreach($famiglie as $fam)
                    @php
                        $label = trim((string) ($fam->description ?? ''));
                        $levelLabel = trim((string) ($fam->level_label ?? 'Categoria'));
                        $level = trim((string) ($fam->level ?? 'Livello 1'));
                        $url = $fam->url ?? '#';
                        $prodotti = (int) ($fam->prodotti ?? 0);

                        if ($label === '') {
                            $label = trim((string) ($fam->code ?? ''));
                        }

                        if ($label === '') {
                            $label = 'Categoria senza descrizione';
                        }
                    @endphp

                    <div class="col-12 col-md-6 col-xl-4">
                        <a href="{{ $url }}" class="text-decoration-none text-reset d-block h-100">
                            <div class="card border-0 shadow-sm h-100 catalog-node-card">
                                <div class="card-body d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                            <div>
                                                <div class="text-muted small text-uppercase mb-1">{{ $levelLabel }}</div>
                                                <div class="fs-5 fw-semibold">{{ $label }}</div>
                                                @if(!empty($fam->code))
                                                    <div class="text-muted small mt-1">Codice ERP: {{ $fam->code }}</div>
                                                @endif
                                            </div>

                                            <span class="badge rounded-pill text-bg-light border">{{ $level }}</span>
                                        </div>

                                        <div class="text-muted small mb-1">Prodotti attivi nello store</div>
                                        <div class="fs-4 fw-bold">{{ number_format($prodotti, 0, ',', '.') }}</div>
                                    </div>

                                    <div class="pt-3 mt-3 border-top d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">Apri categoria</span>
                                        <span class="btn btn-primary btn-sm">
                                            Apri
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection


@extends('layouts.admin')

@section('title', 'CMS Storefront')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">CMS Storefront</h1>
            <div class="text-muted">
                Gestisci contenuti, media e SEO delle pagine statiche B2C. I layout restano nei Blade.
            </div>
            @if(isset($store))
                <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge {{ $store->isB2C() ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $store->isB2C() ? 'Store B2C' : 'Store B2B' }}
                    </span>
                    <span class="small text-muted">
                        Store selezionato: <strong>{{ $store->name }}</strong>
                    </span>
                    @if($usesTranslations ?? false)
                        <span class="small text-muted">
                            Lingua contenuto: <strong>{{ strtoupper($contentLocale ?? app()->getLocale()) }}</strong>
                        </span>
                    @endif
                </div>
            @endif
        </div>

        @if(($editorAvailable ?? true) && ($canManageStructure ?? false))
        <div>
            <a href="{{ route('admin.storefront-pages.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus me-2"></i>
                Nuova pagina
            </a>
        </div>
        @endif
    </div>

    @if(!($editorAvailable ?? true))
        <div class="alert alert-warning border-0 shadow-sm">
            <div class="fw-semibold mb-1">Editor pagine statiche disponibile solo per store B2C.</div>
            <div>
                Ora è selezionato <strong>{{ $store->name ?? 'uno store B2B' }}</strong>.
                Usa il selettore store in alto per scegliere un sito B2C prima di modificare testi, immagini e SEO.
            </div>
        </div>
    @endif

    @if(session('status'))
        <div class="alert alert-success border-0 shadow-sm">
            {{ session('status') }}
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">

            @if($pages->count() > 0)
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Pagina</th>
                                <th>Slug</th>
                                <th>Template codice</th>
                                <th>Slot</th>
                                <th>Stato</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($pages as $page)
                                <tr>
                                    <td class="fw-semibold text-muted">
                                        #{{ $page->id }}
                                    </td>

                                    <td>
                                        <div class="fw-semibold">
                                            {{ $page->title }}
                                        </div>

                                        @if($page->meta_title)
                                            <div class="small text-muted">
                                                SEO: {{ $page->meta_title }}
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        <code>{{ $page->slug }}</code>
                                    </td>

                                    <td>
                                        <span class="badge text-bg-secondary">
                                            {{ $page->template }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge text-bg-dark">
                                            {{ $page->blocks_count }} slot
                                        </span>
                                    </td>

                                    <td>
                                        @if($page->is_active)
                                            <span class="badge text-bg-success">
                                                Attiva
                                            </span>
                                        @else
                                            <span class="badge text-bg-secondary">
                                                Disattivata
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="d-flex justify-content-end gap-2">
                                            <a
                                                href="{{ route('admin.storefront-pages.edit', $page) }}"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                <i class="fa-solid fa-pen me-1"></i>
                                                Modifica
                                            </a>

                                            @if($canManageStructure ?? false)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.storefront-pages.destroy', $page) }}"
                                                    onsubmit="return confirm('Eliminare questa pagina?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fa-solid fa-trash me-1"></i>
                                                        Elimina
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-top">
                    {{ $pages->links() }}
                </div>
            @else
                <div class="p-5 text-center">
                    <div class="admin-empty-state-icon mb-3 text-muted">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>

                    <h2 class="h5 mb-2">Nessuna pagina storefront</h2>

                    <div class="text-muted mb-4">
                        @if($editorAvailable ?? true)
                            Crea la prima pagina contenuto per questo store.
                        @else
                            Nessuna pagina modificabile per lo store selezionato.
                        @endif
                    </div>

                    @if(($editorAvailable ?? true) && ($canManageStructure ?? false))
                        <a href="{{ route('admin.storefront-pages.create') }}" class="btn btn-primary">
                            <i class="fa-solid fa-plus me-2"></i>
                            Crea pagina
                        </a>
                    @endif
                </div>
            @endif

        </div>
    </div>
</div>
@endsection

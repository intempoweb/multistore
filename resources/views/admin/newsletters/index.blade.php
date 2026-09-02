@extends('layouts.admin')

@section('title', 'Newsletter')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Newsletter</h1>
            <p class="text-muted mb-0">
                Bozze commerciali per {{ $store->name }} · {{ $store->channelLabel() }} · Ditta {{ $store->ditta_cg18 }} / Site {{ $store->erp_site_code }}.
            </p>
        </div>

        <a href="{{ route('admin.newsletters.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>
            Nuova newsletter
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.newsletters.index') }}" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Cerca</label>
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Nome o oggetto">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Provider</label>
                    <select name="provider" class="form-select form-select-sm">
                        <option value="">Tutti</option>
                        @foreach($providerLabels as $provider => $label)
                            <option value="{{ $provider }}" @selected(request('provider') === $provider)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-sm btn-primary flex-grow-1" type="submit">Filtra</button>
                    @if(request()->query())
                        <a href="{{ route('admin.newsletters.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-xmark"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($newsletters->isEmpty())
                <div class="p-4 text-center text-muted">Nessuna newsletter creata per questo store.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Newsletter</th>
                                <th>Provider</th>
                                <th>Listino</th>
                                <th class="text-end">Prodotti</th>
                                <th>Stato</th>
                                <th class="text-end px-4">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($newsletters as $newsletter)
                                <tr>
                                    <td>{{ $newsletter->id }}</td>
                                    <td>
                                        <strong>{{ $newsletter->name }}</strong>
                                        <div class="small text-muted">{{ $newsletter->subject }}</div>
                                    </td>
                                    <td>{{ $newsletter->providerLabel() }}</td>
                                    <td>{{ $newsletter->listino_id ?: '-' }}</td>
                                    <td class="text-end">{{ number_format((int) $newsletter->products_count, 0, ',', '.') }}</td>
                                    <td>
                                        <span class="badge {{ $newsletter->status === 'error' ? 'text-bg-danger' : ($newsletter->status === 'synced' ? 'text-bg-success' : 'text-bg-secondary') }}">
                                            {{ $newsletter->status }}
                                        </span>
                                        @if($newsletter->provider_synced_at)
                                            <div class="small text-muted">{{ $newsletter->provider_synced_at->timezone('Europe/Rome')->format('d/m/Y H:i') }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end px-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('admin.newsletters.preview', $newsletter) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.newsletters.edit', $newsletter) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fa-solid fa-pencil"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.newsletters.duplicate', $newsletter) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fa-solid fa-copy"></i></button>
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

    @if($newsletters->hasPages())
        <div class="mt-3">{{ $newsletters->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection

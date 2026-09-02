@extends('layouts.admin')

@section('title', 'Modifica newsletter')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $newsletter->name }}</h1>
            <p class="text-muted mb-0">
                {{ $newsletter->providerLabel() }} · {{ $newsletter->selectionLabel() }} · Stato {{ $newsletter->status }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.newsletters.preview', $newsletter) }}" class="btn btn-outline-secondary" target="_blank">
                <i class="fa-solid fa-eye me-1"></i>
                Preview
            </a>
            <form method="POST" action="{{ route('admin.newsletters.sync', $newsletter) }}">
                @csrf
                <button class="btn btn-success" type="submit">
                    <i class="fa-solid fa-cloud-arrow-up me-1"></i>
                    Sincronizza bozza
                </button>
            </form>
            <a href="{{ route('admin.newsletters.index') }}" class="btn btn-outline-secondary">Elenco</a>
        </div>
    </div>

    @if($newsletter->provider_campaign_id)
        <div class="alert alert-info">
            Campagna provider: <strong>{{ $newsletter->provider_campaign_id }}</strong>
            @if($newsletter->provider_edit_url)
                · <a href="{{ $newsletter->provider_edit_url }}" target="_blank" rel="noopener">apri nel provider</a>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('admin.newsletters.update', $newsletter) }}">
        @csrf
        @method('PUT')
        @include('admin.newsletters._form')
        <div class="mt-4 d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">Salva modifiche</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.newsletters.destroy', $newsletter) }}" class="mt-3" onsubmit="return confirm('Eliminare questa newsletter?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger" type="submit">Elimina</button>
    </form>
</div>
@endsection

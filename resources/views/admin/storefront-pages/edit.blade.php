

@extends('layouts.admin')

@section('title', 'Modifica pagina storefront')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Modifica pagina storefront</h1>
            <div class="text-muted">
                Aggiorna contenuti modificabili, media e SEO. Il layout resta gestito dai Blade.
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">

            @if($page->slug)
                <a
                    href="{{ url($page->slug === 'home' ? '/' : '/' . ltrim($page->slug, '/')) }}"
                    target="_blank"
                    class="btn btn-outline-dark"
                >
                    <i class="fa-solid fa-up-right-from-square me-2"></i>
                    Apri pagina
                </a>
            @endif

            <a href="{{ route('admin.storefront-pages.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-2"></i>
                Torna alle pagine
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success border-0 shadow-sm">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-2">Controlla i dati inseriti:</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.storefront-pages.update', $page) }}"
    >
        @csrf
        @method('PUT')

        @include('admin.storefront-pages._form', [
            'page' => $page,
        ])
    </form>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 pb-0">
            <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3">
                <div>
                    <h2 class="h5 mb-1">Slot contenuto Blade</h2>
                    <div class="text-muted small">
                        Modifica testi, immagini e video usati dai template Blade. La struttura resta nel codice.
                    </div>
                </div>

                <span class="badge text-bg-light border">
                    {{ $page->blocks->count() }} slot
                </span>
            </div>
        </div>

        <div class="card-body">
            @if($page->blocks->isEmpty())
                <div class="alert alert-light border mb-0">
                    Nessuno slot configurato per questa pagina.
                    @if($page->slug === 'login')
                        Ricarica questa pagina: gli slot login verranno inizializzati automaticamente.
                    @endif
                </div>
            @else
                <form
                    method="POST"
                    action="{{ route('admin.storefront-pages.blocks.update', $page) }}"
                    enctype="multipart/form-data"
                >
                    @csrf
                    @method('PUT')

                    <div class="row g-4">
                        @foreach($page->blocks as $index => $block)
                            @php
                                $imageUrl = $block->image_path
                                    ? (\Illuminate\Support\Str::startsWith($block->image_path, ['http://', 'https://', '/'])
                                        ? $block->image_path
                                        : asset('storage/' . ltrim($block->image_path, '/')))
                                    : null;

                                $mobileImageUrl = $block->mobile_image_path
                                    ? (\Illuminate\Support\Str::startsWith($block->mobile_image_path, ['http://', 'https://', '/'])
                                        ? $block->mobile_image_path
                                        : asset('storage/' . ltrim($block->mobile_image_path, '/')))
                                    : null;
                            @endphp

                            <div class="col-12 col-xl-6">
                                <div class="border rounded-4 p-3 h-100 bg-light-subtle">
                                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                        <div>
                                            <div class="fw-semibold">
                                                {{ $block->name ?: 'Slot #' . ($index + 1) }}
                                            </div>
                                            <div class="small text-muted">
                                                Tipo: <code>{{ $block->type }}</code>
                                            </div>
                                        </div>

                                        <div class="form-check form-switch">
                                            <input type="hidden" name="blocks[{{ $index }}][is_active]" value="0">
                                            <input
                                                type="checkbox"
                                                name="blocks[{{ $index }}][is_active]"
                                                value="1"
                                                class="form-check-input"
                                                id="block_active_{{ $block->id }}"
                                                @checked(old("blocks.$index.is_active", $block->is_active))
                                            >
                                            <label class="form-check-label small" for="block_active_{{ $block->id }}">
                                                Attivo
                                            </label>
                                        </div>
                                    </div>

                                    <input type="hidden" name="blocks[{{ $index }}][id]" value="{{ $block->id }}">
                                    <input type="hidden" name="blocks[{{ $index }}][type]" value="{{ old("blocks.$index.type", $block->type) }}">
                                    <input type="hidden" name="blocks[{{ $index }}][name]" value="{{ old("blocks.$index.name", $block->name) }}">
                                    <input type="hidden" name="blocks[{{ $index }}][image_path]" value="{{ old("blocks.$index.image_path", $block->image_path) }}">
                                    <input type="hidden" name="blocks[{{ $index }}][mobile_image_path]" value="{{ old("blocks.$index.mobile_image_path", $block->mobile_image_path) }}">
                                    <input type="hidden" name="blocks[{{ $index }}][video_path]" value="{{ old("blocks.$index.video_path", $block->video_path) }}">

                                    <div class="row g-3">
                                        <div class="col-12 col-lg-8">
                                            <label class="form-label fw-semibold" for="block_title_{{ $block->id }}">
                                                Titolo
                                            </label>
                                            <input
                                                type="text"
                                                name="blocks[{{ $index }}][title]"
                                                id="block_title_{{ $block->id }}"
                                                class="form-control"
                                                value="{{ old("blocks.$index.title", $block->title) }}"
                                            >
                                        </div>

                                        <div class="col-12 col-lg-4">
                                            <label class="form-label fw-semibold" for="block_sort_{{ $block->id }}">
                                                Ordine
                                            </label>
                                            <input
                                                type="number"
                                                name="blocks[{{ $index }}][sort_order]"
                                                id="block_sort_{{ $block->id }}"
                                                class="form-control"
                                                value="{{ old("blocks.$index.sort_order", $block->sort_order) }}"
                                                min="0"
                                            >
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-semibold" for="block_subtitle_{{ $block->id }}">
                                                Sottotitolo
                                            </label>
                                            <input
                                                type="text"
                                                name="blocks[{{ $index }}][subtitle]"
                                                id="block_subtitle_{{ $block->id }}"
                                                class="form-control"
                                                value="{{ old("blocks.$index.subtitle", $block->subtitle) }}"
                                            >
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-semibold" for="block_content_{{ $block->id }}">
                                                Contenuto
                                            </label>
                                            <textarea
                                                name="blocks[{{ $index }}][content]"
                                                id="block_content_{{ $block->id }}"
                                                rows="3"
                                                class="form-control"
                                            >{{ old("blocks.$index.content", $block->content) }}</textarea>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-semibold" for="block_image_{{ $block->id }}">
                                                Immagine desktop
                                            </label>

                                            @if($imageUrl)
                                                <div class="mb-2 rounded-3 overflow-hidden border bg-white" style="max-width: 240px;">
                                                    <img src="{{ $imageUrl }}" alt="{{ $block->title }}" class="img-fluid d-block">
                                                </div>
                                            @endif

                                            <input
                                                type="file"
                                                name="blocks[{{ $index }}][image_file]"
                                                id="block_image_{{ $block->id }}"
                                                class="form-control"
                                                accept="image/*"
                                            >

                                            <div class="form-text">
                                                Se carichi una nuova immagine, sostituisce quella attuale per questo slot.
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-semibold" for="block_mobile_image_{{ $block->id }}">
                                                Immagine mobile
                                            </label>

                                            @if($mobileImageUrl)
                                                <div class="mb-2 rounded-3 overflow-hidden border bg-white" style="max-width: 180px;">
                                                    <img src="{{ $mobileImageUrl }}" alt="{{ $block->title }}" class="img-fluid d-block">
                                                </div>
                                            @endif

                                            <input
                                                type="file"
                                                name="blocks[{{ $index }}][mobile_image_file]"
                                                id="block_mobile_image_{{ $block->id }}"
                                                class="form-control"
                                                accept="image/*"
                                            >
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-semibold" for="block_video_{{ $block->id }}">
                                                Video
                                            </label>
                                            <input
                                                type="file"
                                                name="blocks[{{ $index }}][video_file]"
                                                id="block_video_{{ $block->id }}"
                                                class="form-control"
                                                accept="video/mp4,video/webm,video/quicktime"
                                            >
                                            @if($block->video_path)
                                                <div class="form-text">
                                                    Video attuale: <code>{{ $block->video_path }}</code>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="col-12 col-lg-6">
                                            <label class="form-label fw-semibold" for="block_button_label_{{ $block->id }}">
                                                Label bottone/link
                                            </label>
                                            <input
                                                type="text"
                                                name="blocks[{{ $index }}][button_label]"
                                                id="block_button_label_{{ $block->id }}"
                                                class="form-control"
                                                value="{{ old("blocks.$index.button_label", $block->button_label) }}"
                                            >
                                        </div>

                                        <div class="col-12 col-lg-6">
                                            <label class="form-label fw-semibold" for="block_button_url_{{ $block->id }}">
                                                URL bottone/link
                                            </label>
                                            <input
                                                type="text"
                                                name="blocks[{{ $index }}][button_url]"
                                                id="block_button_url_{{ $block->id }}"
                                                class="form-control"
                                                value="{{ old("blocks.$index.button_url", $block->button_url) }}"
                                            >
                                        </div>

                                        <div class="col-12">
                                            <div class="form-check">
                                                <input type="hidden" name="blocks[{{ $index }}][button_new_tab]" value="0">
                                                <input
                                                    type="checkbox"
                                                    name="blocks[{{ $index }}][button_new_tab]"
                                                    id="block_new_tab_{{ $block->id }}"
                                                    value="1"
                                                    class="form-check-input"
                                                    @checked(old("blocks.$index.button_new_tab", $block->button_new_tab))
                                                >
                                                <label class="form-check-label" for="block_new_tab_{{ $block->id }}">
                                                    Apri link in nuova scheda
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fa-solid fa-images me-2"></i>
                            Salva slot contenuto
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

</div>
@endsection
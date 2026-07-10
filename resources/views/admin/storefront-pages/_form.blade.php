@php
    $page = $page ?? null;
    $usesTranslations = $usesTranslations ?? false;
    $contentLocale = $contentLocale ?? app()->getLocale();
    $supportedLocales = $supportedLocales ?? [$contentLocale];
    $localeNames = [
        'it' => 'Italiano',
        'en' => 'Inglese',
        'es' => 'Spagnolo',
    ];
@endphp

@if($usesTranslations)
    <div class="alert alert-info border-0 shadow-sm d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-4">
        <div>
            <div class="fw-semibold">Stai modificando i contenuti in {{ $localeNames[$contentLocale] ?? strtoupper($contentLocale) }}</div>
            <div class="small">
                Titolo, slug, descrizione e SEO sono specifici per lingua. Pubblicazione e ordinamento restano condivisi.
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @foreach($supportedLocales as $locale)
                <span class="badge {{ $locale === $contentLocale ? 'text-bg-dark' : 'text-bg-light border text-dark' }}">
                    {{ strtoupper($locale) }}
                </span>
            @endforeach
        </div>
    </div>
@endif

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pb-0">
                <h2 class="h5 mb-1">Informazioni pagina</h2>
                <div class="text-muted small">
                    {{ $usesTranslations
                        ? 'Questi campi cambiano in base alla lingua selezionata.'
                        : 'Gestisci contenuti modificabili e SEO. Struttura e template restano nel codice Blade.' }}
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold" for="title">Titolo pagina</label>
                        <input
                            type="text"
                            name="title"
                            id="title"
                            class="form-control @error('title') is-invalid @enderror"
                            value="{{ old('title', $page?->title) }}"
                            required
                        >
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold" for="slug">Slug</label>
                        <div class="input-group">
                            <span class="input-group-text">/</span>
                            <input
                                type="text"
                                name="slug"
                                id="slug"
                                class="form-control @error('slug') is-invalid @enderror"
                                value="{{ old('slug', $page?->slug) }}"
                                placeholder="login"
                                required
                            >
                        </div>
                        @error('slug')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        @if($usesTranslations)
                            <div class="form-text">
                                URL pubblico per {{ $localeNames[$contentLocale] ?? strtoupper($contentLocale) }}.
                            </div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold" for="sort_order">Ordinamento</label>
                        <input
                            type="number"
                            name="sort_order"
                            id="sort_order"
                            class="form-control @error('sort_order') is-invalid @enderror"
                            value="{{ old('sort_order', $page?->sort_order ?? 0) }}"
                            min="0"
                        >
                        @error('sort_order')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold" for="description">Descrizione editoriale</label>
                        <textarea
                            name="description"
                            id="description"
                            rows="4"
                            class="form-control @error('description') is-invalid @enderror"
                        >{{ old('description', $page?->description) }}</textarea>

                        <div class="form-text">
                            Usata solo dai Blade che leggono la descrizione pagina. Non modifica il layout.
                        </div>

                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pb-0">
                <h2 class="h5 mb-1">SEO</h2>
                <div class="text-muted small">
                    Configurazione meta tag e indicizzazione.
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="meta_title">Meta title</label>
                        <input
                            type="text"
                            name="meta_title"
                            id="meta_title"
                            class="form-control @error('meta_title') is-invalid @enderror"
                            value="{{ old('meta_title', $page?->meta_title) }}"
                        >
                        @error('meta_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold" for="meta_description">Meta description</label>
                        <textarea
                            name="meta_description"
                            id="meta_description"
                            rows="4"
                            class="form-control @error('meta_description') is-invalid @enderror"
                        >{{ old('meta_description', $page?->meta_description) }}</textarea>
                        @error('meta_description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pb-0">
                    <h2 class="h5 mb-1">Pubblicazione</h2>
                    @if($usesTranslations)
                        <div class="text-muted small">Impostazioni condivise da tutte le lingue.</div>
                    @endif
            </div>

            <div class="card-body d-flex flex-column gap-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input
                        type="checkbox"
                        name="is_active"
                        id="is_active"
                        value="1"
                        class="form-check-input"
                        @checked(old('is_active', $page?->is_active ?? true))
                    >
                    <label class="form-check-label fw-semibold" for="is_active">
                        Pagina attiva
                    </label>
                </div>

                <div class="small text-muted">
                    Le pagine disattivate non saranno usate nello storefront.
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pb-0">
                <h2 class="h5 mb-1">Template codice</h2>
            </div>

            <div class="card-body">
                <div class="alert alert-light border small mb-0">
                    Il template non si sceglie da BO.
                    <hr>
                    Viene risolto dal codice in base allo slug:
                    <ul class="mb-0 mt-2">
                        <li><code>login</code> → pagina login</li>
                        <li><code>home</code> → homepage</li>
                        <li>altro → Blade dedicato</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-floppy-disk me-2"></i>
                Salva pagina
            </button>
        </div>
    </div>
</div>

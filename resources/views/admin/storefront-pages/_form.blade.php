@php
    $page = $page ?? null;
    $usesTranslations = $usesTranslations ?? false;
    $contentLocale = $contentLocale ?? app()->getLocale();
    $supportedLocales = $supportedLocales ?? [$contentLocale];
    $storefrontBaseUrl = rtrim((string) ($storefrontBaseUrl ?? config('app.url')), '/');
    $currentSlug = trim((string) old('slug', $page?->slug), '/');
    $publicLocalePrefix = $usesTranslations ? '/' . trim((string) $contentLocale, '/') : '';
    $previewPath = $currentSlug === '' || $currentSlug === 'home'
        ? ($publicLocalePrefix ?: '/')
        : $publicLocalePrefix . '/' . $currentSlug;
    $previewUrl = $storefrontBaseUrl . ($previewPath === '/' ? '/' : $previewPath);
    $pageEditorSchema = $pageEditorSchema ?? [
        'content_label' => 'Testo principale',
        'content_help' => 'Testo usato dalla pagina pubblica.',
        'show_sort_order' => false,
        'allow_slug_edit' => true,
    ];
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
                <h2 class="h5 mb-1">Contenuto pagina</h2>
                <div class="text-muted small">
                    {{ $usesTranslations
                        ? 'Questi campi cambiano in base alla lingua selezionata.'
                        : 'Modifica solo testi e informazioni pubbliche. La struttura della pagina resta protetta.' }}
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label fw-semibold" for="title">Nome pagina</label>
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
                        <label class="form-label fw-semibold" for="slug">Indirizzo pubblico</label>
                        <div class="input-group">
                            <span class="input-group-text">/</span>
                            <input
                                type="text"
                                name="slug"
                                id="slug"
                                class="form-control @error('slug') is-invalid @enderror"
                                value="{{ old('slug', $page?->slug) }}"
                                placeholder="chi-siamo"
                                required
                                @disabled(!($pageEditorSchema['allow_slug_edit'] ?? true))
                            >
                        </div>
                        @if(!($pageEditorSchema['allow_slug_edit'] ?? true))
                            <input type="hidden" name="slug" value="{{ old('slug', $page?->slug) }}">
                        @endif
                        @error('slug')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            È la parte finale dell'URL. Esempio: <code>chi-siamo</code>.
                            @if($usesTranslations)
                                Lingua corrente: {{ $localeNames[$contentLocale] ?? strtoupper($contentLocale) }}.
                            @endif
                        </div>
                    </div>

                    <div class="col-12 col-lg-6 {{ ($pageEditorSchema['show_sort_order'] ?? false) ? '' : 'd-none' }}">
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
                    @unless($pageEditorSchema['show_sort_order'] ?? false)
                        <input type="hidden" name="sort_order" value="{{ old('sort_order', $page?->sort_order ?? 0) }}">
                    @endunless

                    <div class="col-12">
                        <label class="form-label fw-semibold" for="description">{{ $pageEditorSchema['content_label'] ?? 'Testo principale' }}</label>
                        <textarea
                            name="description"
                            id="description"
                            rows="4"
                            class="form-control @error('description') is-invalid @enderror"
                        >{{ old('description', $page?->description) }}</textarea>

                        <div class="form-text">
                            {{ $pageEditorSchema['content_help'] ?? 'Non modifica il layout.' }}
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
                    Testi usati da Google e dalle anteprime quando la pagina viene condivisa.
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
                            maxlength="190"
                            data-character-counter
                            data-character-counter-target="meta_title_counter"
                        >
                        <div class="form-text">
                            Consigliato: 50-60 caratteri. <span id="meta_title_counter"></span>
                        </div>
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
                            data-character-counter
                            data-character-counter-target="meta_description_counter"
                        >{{ old('meta_description', $page?->meta_description) }}</textarea>
                        <div class="form-text">
                            Consigliato: 140-160 caratteri. <span id="meta_description_counter"></span>
                        </div>
                        @error('meta_description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <div class="border rounded-3 bg-light p-3" data-seo-preview>
                            <div class="small text-muted mb-2">Anteprima indicativa su Google</div>
                            <div class="storefront-seo-preview-title" data-seo-preview-title>
                                {{ old('meta_title', $page?->meta_title) ?: old('title', $page?->title) ?: 'Titolo pagina' }}
                            </div>
                            <div
                                class="storefront-seo-preview-url"
                                data-seo-preview-url
                                data-storefront-base-url="{{ $storefrontBaseUrl }}"
                                data-content-locale="{{ $usesTranslations ? $contentLocale : '' }}"
                            >
                                {{ $previewUrl }}
                            </div>
                            <div class="storefront-seo-preview-description" data-seo-preview-description>
                                {{ old('meta_description', $page?->meta_description) ?: 'Aggiungi una meta description chiara e utile per descrivere questa pagina.' }}
                            </div>
                            <div class="small mt-2" data-seo-preview-warning></div>
                        </div>
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
                <h2 class="h5 mb-1">Struttura protetta</h2>
            </div>

            <div class="card-body">
                <div class="alert alert-light border small mb-0">
                    Questa pagina usa una struttura già approvata. Da qui puoi aggiornare testi, immagini e SEO senza cambiare layout o sezioni.
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

@once
    @push('scripts')
        <script src="{{ asset('js/admin/storefront-pages.js') }}"></script>
    @endpush
@endonce

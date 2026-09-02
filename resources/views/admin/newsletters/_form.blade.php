@php
    $filters = old('filters', $newsletter->filters ?? []);
    $selectedTypes = old('selection_types', data_get($newsletter->settings, 'selection_types', []));
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Controlla i dati inseriti.</div>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Contenuto</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome interno</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $newsletter->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Oggetto email</label>
                        <input type="text" name="subject" class="form-control" value="{{ old('subject', $newsletter->subject) }}" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Preview text</label>
                        <input type="text" name="preview_text" class="form-control" value="{{ old('preview_text', $newsletter->preview_text) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Lingua</label>
                        <select name="locale" class="form-select">
                            @foreach($locales as $locale)
                                <option value="{{ $locale }}" @selected(old('locale', $newsletter->locale) === $locale)>{{ strtoupper($locale) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Titolo hero</label>
                        <input type="text" name="hero_title" class="form-control" value="{{ old('hero_title', $newsletter->hero_title) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Immagine hero HTTPS</label>
                        <input type="text" name="hero_image_url" class="form-control" value="{{ old('hero_image_url', $newsletter->hero_image_url) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Testo hero</label>
                        <textarea name="hero_text" class="form-control" rows="3">{{ old('hero_text', $newsletter->hero_text) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Intro HTML</label>
                        <textarea name="intro_html" class="form-control" rows="4">{{ old('intro_html', $newsletter->intro_html) }}</textarea>
                        <div class="form-text">Usato nel template email. Evita script e CSS complessi.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm" data-newsletter-products-preview-root data-preview-url="{{ route('admin.newsletters.products-preview') }}">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h5 mb-0">Prodotti</h2>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-newsletter-products-preview-button>
                        <i class="fa-solid fa-arrows-rotate me-1"></i>
                        Aggiorna tabella
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tipo selezione</label>
                        <select name="selection_type" class="form-select">
                            @foreach($selectionLabels as $type => $label)
                                <option value="{{ $type }}" @selected(old('selection_type', $newsletter->selection_type) === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Listino newsletter</label>
                        <input type="number" name="listino_id" class="form-control" value="{{ old('listino_id', $newsletter->listino_id) }}" min="1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Criteri mix</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach([
                                \App\Models\Newsletter::SELECTION_OFFERS => 'Offerte',
                                \App\Models\Newsletter::SELECTION_PROMOTIONS => 'Promozioni',
                                \App\Models\Newsletter::SELECTION_NEW_PRODUCTS => 'Novita',
                                \App\Models\Newsletter::SELECTION_CAMPAIGNS => 'Campagne',
                            ] as $type => $label)
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="selection_types[]" value="{{ $type }}" @checked(in_array($type, (array) $selectedTypes, true))>
                                    <span class="form-check-label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Famiglia</label>
                        <input type="text" name="filters[fam_99]" class="form-control" value="{{ $filters['fam_99'] ?? '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sottofamiglia</label>
                        <input type="text" name="filters[sfam_99]" class="form-control" value="{{ $filters['sfam_99'] ?? '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gruppo</label>
                        <input type="text" name="filters[gruppo_99]" class="form-control" value="{{ $filters['gruppo_99'] ?? '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sottogruppo</label>
                        <input type="text" name="filters[sgruppo_99]" class="form-control" value="{{ $filters['sgruppo_99'] ?? '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Marca</label>
                        <input type="text" name="filters[marca_mg64]" class="form-control" value="{{ $filters['marca_mg64'] ?? '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Brand</label>
                        <input type="text" name="filters[codbrand_w58]" class="form-control" value="{{ $filters['codbrand_w58'] ?? '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">SKU inclusi</label>
                        <textarea name="manual_skus" class="form-control font-monospace" rows="10" placeholder="Uno SKU per riga. Se vuoto, usa la selezione ERP automatica.">{{ old('manual_skus', $manualSkus ?? '') }}</textarea>
                        <div class="form-text">Inserisci gli SKU figli/simple, uno per riga. I prodotti padre/configurabili vengono segnalati e non inclusi.</div>
                    </div>
                    <div class="col-12">
                        <div class="small fw-semibold mb-2">Corrispondenza prodotti</div>
                        <div data-newsletter-products-preview>
                            <div class="border rounded p-3 text-muted small">
                                Aggiorna la tabella per verificare SKU, prodotto figlio e prezzo del listino.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Provider</h2>
                <div class="mb-3">
                    <label class="form-label">Provider newsletter</label>
                    <select name="provider" class="form-select">
                        @foreach($providerLabels as $provider => $label)
                            <option value="{{ $provider }}" @selected(old('provider', $newsletter->provider) === $provider)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="small text-muted">
                    Store: {{ $store->name }}<br>
                    Ditta/Site: {{ $store->ditta_cg18 }} / {{ $store->erp_site_code }}
                </div>
            </div>
        </div>

        @if($newsletter->exists)
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Prodotti selezionati</h2>
                    @if($newsletter->products->isEmpty())
                        <div class="text-muted small">Nessun prodotto selezionato.</div>
                    @else
                        <ol class="small mb-0">
                            @foreach($newsletter->products as $product)
                                <li><span class="font-monospace">{{ $product->sku }}</span></li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-newsletter-products-preview-root]');
    if (!root) {
        return;
    }

    const form = root.closest('form');
    const target = root.querySelector('[data-newsletter-products-preview]');
    const button = root.querySelector('[data-newsletter-products-preview-button]');
    const previewUrl = root.dataset.previewUrl;
    const token = form ? form.querySelector('input[name="_token"]')?.value : null;
    let timer = null;
    let controller = null;

    const fieldsSelector = [
        '[name="manual_skus"]',
        '[name="listino_id"]',
        '[name="selection_type"]',
        '[name="locale"]',
        '[name="provider"]',
        '[name^="filters"]',
        '[name="selection_types[]"]'
    ].join(',');

    function setLoading() {
        if (!target) {
            return;
        }

        target.innerHTML = '<div class="border rounded p-3 text-muted small">Caricamento corrispondenza prodotti...</div>';
    }

    function refreshPreview() {
        if (!form || !target || !previewUrl || !token) {
            return;
        }

        if (controller) {
            controller.abort();
        }

        controller = new AbortController();
        setLoading();

        const formData = new FormData(form);
        formData.delete('_method');

        fetch(previewUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData,
            signal: controller.signal
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Preview non disponibile');
                }

                return response.json();
            })
            .then(function (payload) {
                target.innerHTML = payload.html;
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                target.innerHTML = '<div class="border rounded p-3 text-danger small">Impossibile aggiornare la tabella prodotti.</div>';
            });
    }

    function scheduleRefresh() {
        window.clearTimeout(timer);
        timer = window.setTimeout(refreshPreview, 450);
    }

    button?.addEventListener('click', refreshPreview);

    form?.querySelectorAll(fieldsSelector).forEach(function (field) {
        field.addEventListener('change', scheduleRefresh);
        field.addEventListener('input', scheduleRefresh);
    });

    window.setTimeout(refreshPreview, 200);
});
</script>
@endpush

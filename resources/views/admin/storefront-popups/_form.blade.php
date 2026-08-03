@php
    /** @var \App\Models\StorefrontPopup $popup */
    $submitLabel = $submitLabel ?? 'Salva popup';
    $defaultLocale = $store->defaultLocale('it');
    $translations = $popup->relationLoaded('translations') ? $popup->translations->keyBy('locale') : collect();
    $defaultTranslation = $translations->get($defaultLocale);
    $previewTitle = old("translations.$defaultLocale.title", $defaultTranslation?->title) ?: 'Titolo popup';
    $previewSubtitle = old("translations.$defaultLocale.subtitle", $defaultTranslation?->subtitle) ?: 'Sottotitolo';
    $previewBody = old("translations.$defaultLocale.body", $defaultTranslation?->body) ?: 'Testo del popup visibile nello storefront.';
    $previewCta = old("translations.$defaultLocale.cta_label", $defaultTranslation?->cta_label) ?: 'Scopri di piu';
    $previewBg = old('background_color', $popup->background_color ?: '#ffffff');
    $previewColor = old('text_color', $popup->text_color ?: '#111111');
    $previewButtonBg = old('button_background_color', $popup->button_background_color ?: '#111111');
    $previewButtonColor = old('button_text_color', $popup->button_text_color ?: '#ffffff');
    $scheduledAt = $popup->exists && $popup->is_active && $popup->starts_at && $popup->starts_at->isFuture()
        ? $popup->starts_at->timezone('Europe/Rome')
        : null;
    $availableStores = $availableStores ?? collect([$store]);
    $selectedStoreIds = collect(old('store_ids', $selectedStoreIds ?? [$store->id]))
        ->push($store->id)
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values();
@endphp

@push('styles')
<style>
    .popup-editor-shell {
        max-width: 1480px;
    }

    .popup-editor-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 10px 30px rgba(18, 24, 31, 0.06);
    }

    .popup-editor-card .card-header {
        background: #fff;
        border-bottom: 1px solid #eef1f4;
        padding: 18px 20px;
    }

    .popup-editor-card .card-body {
        padding: 20px;
    }

    .popup-editor-kicker {
        font-size: 0.74rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #6c757d;
    }

    .popup-editor-tabs .nav-link {
        border-radius: 999px;
        border: 1px solid #dce2e8;
        color: #495057;
        font-weight: 700;
        padding: 0.48rem 0.9rem;
    }

    .popup-editor-tabs .nav-link.active {
        background: #212529;
        border-color: #212529;
        color: #fff;
    }

    .popup-preview-wrap {
        position: sticky;
        top: 92px;
    }

    .popup-preview {
        overflow: hidden;
        border-radius: 18px;
        border: 1px solid #e4e8ed;
        background: #101216;
        min-height: 360px;
        display: grid;
        place-items: center;
        padding: 22px;
    }

    .popup-preview-dialog {
        width: min(100%, 520px);
        display: grid;
        grid-template-columns: 42% 58%;
        overflow: hidden;
        border-radius: 16px;
        background: var(--popup-preview-bg);
        color: var(--popup-preview-color);
        box-shadow: 0 22px 54px rgba(0, 0, 0, 0.24);
    }

    .popup-preview-media {
        min-height: 260px;
        background: linear-gradient(135deg, #e9edf2, #cfd7df);
        display: grid;
        place-items: center;
        color: #6c757d;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.76rem;
    }

    .popup-preview-copy {
        padding: 28px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 10px;
    }

    .popup-preview-copy small {
        font-weight: 800;
        text-transform: uppercase;
    }

    .popup-preview-copy h3 {
        margin: 0;
        font-size: 1.7rem;
        font-weight: 800;
        line-height: 1.05;
    }

    .popup-preview-copy p {
        margin: 0;
        color: inherit;
        opacity: 0.76;
        line-height: 1.5;
    }

    .popup-preview-cta {
        width: fit-content;
        margin-top: 8px;
        border-radius: 999px;
        padding: 0.58rem 1rem;
        background: var(--popup-preview-button-bg);
        color: var(--popup-preview-button-color);
        font-size: 0.74rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .popup-editor-actions {
        position: sticky;
        bottom: 0;
        z-index: 10;
        margin-top: 24px;
        padding: 14px 0 0;
        background: linear-gradient(180deg, rgba(248, 249, 250, 0), #f8f9fa 42%);
    }
</style>
@endpush

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Errore:</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($scheduledAt)
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="fa-solid fa-clock mt-1"></i>
        <div>
            <strong>Popup programmato.</strong>
            Sara visibile nello storefront dal {{ $scheduledAt->format('d/m/Y H:i') }}.
        </div>
    </div>
@endif

<div class="popup-editor-shell">
    <div class="row g-4 align-items-start">
        <div class="col-xl-8">
            <div class="card popup-editor-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="popup-editor-kicker">Contenuto</div>
                        <h2 class="h5 mb-0">Testi multilingua</h2>
                    </div>
                    <span class="badge text-bg-light border">{{ strtoupper($defaultLocale) }} principale</span>
                </div>
                <div class="card-body">
                    <ul class="nav popup-editor-tabs gap-2 mb-4" role="tablist">
                        @foreach($locales as $locale)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link @if($locale === $defaultLocale) active @endif" data-bs-toggle="tab" data-bs-target="#popup-locale-{{ $locale }}" type="button" role="tab">
                                    {{ strtoupper($locale) }}
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content">
                        @foreach($locales as $locale)
                            @php($translation = $translations->get($locale))
                            <div class="tab-pane fade @if($locale === $defaultLocale) show active @endif" id="popup-locale-{{ $locale }}" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label fw-semibold">Titolo @if($locale === $defaultLocale)*@endif</label>
                                        <input type="text" name="translations[{{ $locale }}][title]" class="form-control form-control-lg" value="{{ old("translations.$locale.title", $translation?->title) }}" placeholder="Es. Pausa estiva" @if($locale === $defaultLocale) required @endif>
                                        <div class="form-text">Puoi usare tag semplici: &lt;strong&gt;, &lt;em&gt;, &lt;u&gt;, &lt;br&gt;.</div>
                                    </div>

                                    <div class="col-md-5">
                                        <label class="form-label fw-semibold">Sottotitolo</label>
                                        <input type="text" name="translations[{{ $locale }}][subtitle]" class="form-control form-control-lg" value="{{ old("translations.$locale.subtitle", $translation?->subtitle) }}" placeholder="Es. Avviso spedizioni">
                                        <div class="form-text">Stessi tag semplici del titolo.</div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Testo</label>
                                        <textarea name="translations[{{ $locale }}][body]" class="form-control" rows="8" placeholder="Scrivi il messaggio mostrato nel popup.">{{ old("translations.$locale.body", $translation?->body) }}</textarea>
                                        <div class="form-text">Tag consentiti: &lt;p&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;u&gt;, &lt;br&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;.</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Etichetta CTA</label>
                                        <input type="text" name="translations[{{ $locale }}][cta_label]" class="form-control" value="{{ old("translations.$locale.cta_label", $translation?->cta_label) }}" placeholder="Es. Vai al catalogo">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card popup-editor-card mb-4">
                <div class="card-header">
                    <div class="popup-editor-kicker">Impostazioni</div>
                    <h2 class="h5 mb-0">Regole di pubblicazione</h2>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nome interno *</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $popup->name) }}" placeholder="Es. Chiusura agosto 2026" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Store di pubblicazione</label>
                            <input type="hidden" name="store_ids[]" value="{{ $store->id }}">
                            <div class="row g-2">
                                @foreach($availableStores as $availableStore)
                                    @php($isCurrentStore = (int) $availableStore->id === (int) $store->id)
                                    <div class="col-md-6 col-xxl-4">
                                        <label class="border rounded-3 px-3 py-2 d-flex align-items-start gap-2 h-100 bg-white">
                                            <input
                                                type="checkbox"
                                                name="store_ids[]"
                                                value="{{ $availableStore->id }}"
                                                class="form-check-input mt-1"
                                                @checked($selectedStoreIds->contains((int) $availableStore->id))
                                                @disabled($isCurrentStore)
                                            >
                                            <span>
                                                <span class="fw-semibold">{{ $availableStore->name }}</span>
                                                <span class="badge text-bg-light border ms-1">{{ $availableStore->channelLabel() }}</span>
                                                @if($isCurrentStore)
                                                    <span class="badge text-bg-primary ms-1">corrente</span>
                                                @endif
                                                <span class="d-block small text-muted">
                                                    {{ $availableStore->site_code }} · {{ $availableStore->domain }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-text">
                                Lo stesso popup verra mostrato su tutti gli store selezionati, rispettando visibilita, date e frequenza.
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Visibilità *</label>
                            <select name="display_scope" class="form-select" required>
                                @foreach($scopeLabels as $scope => $label)
                                    <option value="{{ $scope }}" @selected(old('display_scope', $popup->display_scope) === $scope)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Frequenza *</label>
                            <select name="frequency" class="form-select" required>
                                @foreach($frequencyLabels as $frequency => $label)
                                    <option value="{{ $frequency }}" @selected(old('frequency', $popup->frequency) === $frequency)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Ritardo (ms)</label>
                            <input type="number" min="0" max="60000" name="delay_ms" class="form-control" value="{{ old('delay_ms', $popup->delay_ms ?? 0) }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Priorità</label>
                            <input type="number" name="priority" class="form-control" value="{{ old('priority', $popup->priority ?? 0) }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Dal</label>
                            <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', optional($popup->starts_at)->format('Y-m-d\TH:i')) }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Al</label>
                            <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', optional($popup->ends_at)->format('Y-m-d\TH:i')) }}">
                        </div>

                        <div class="col-12">
                            <div class="form-text">
                                Se lasci vuoto <strong>Dal</strong>, il popup parte subito. Se imposti una data futura, resta programmato e non appare fino a quel momento.
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $popup->is_active ?? true))>
                                <label class="form-check-label fw-semibold">Popup attivo</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card popup-editor-card">
                <div class="card-header">
                    <div class="popup-editor-kicker">Media e CTA</div>
                    <h2 class="h5 mb-0">Aspetto e destinazione</h2>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Immagine</label>
                            <input type="text" name="image_url" class="form-control" value="{{ old('image_url', $popup->image_url) }}" placeholder="storefront/popup.jpg oppure URL completo">
                            <div class="form-text">Accetta path S3 del media store o URL completo.</div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Link CTA</label>
                            <input type="text" name="cta_url" class="form-control" value="{{ old('cta_url', $popup->cta_url) }}" placeholder="https://... oppure /it/catalog">
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input type="hidden" name="open_in_new_tab" value="0">
                                <input class="form-check-input" type="checkbox" name="open_in_new_tab" value="1" @checked(old('open_in_new_tab', $popup->open_in_new_tab ?? false))>
                                <label class="form-check-label">Apri CTA in nuova scheda</label>
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold">Sfondo</label>
                            <input type="color" name="background_color" class="form-control form-control-color w-100" value="{{ $previewBg }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold">Testo</label>
                            <input type="color" name="text_color" class="form-control form-control-color w-100" value="{{ $previewColor }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold">Bottone</label>
                            <input type="color" name="button_background_color" class="form-control form-control-color w-100" value="{{ $previewButtonBg }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fw-semibold">Testo bottone</label>
                            <input type="color" name="button_text_color" class="form-control form-control-color w-100" value="{{ $previewButtonColor }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="popup-preview-wrap">
                <div class="card popup-editor-card">
                    <div class="card-header">
                        <div class="popup-editor-kicker">Anteprima</div>
                        <h2 class="h5 mb-0">Popup storefront</h2>
                    </div>
                    <div class="card-body">
                        <div class="popup-preview">
                            <div class="popup-preview-dialog" style="--popup-preview-bg: {{ $previewBg }}; --popup-preview-color: {{ $previewColor }}; --popup-preview-button-bg: {{ $previewButtonBg }}; --popup-preview-button-color: {{ $previewButtonColor }};">
                                <div class="popup-preview-media">Immagine</div>
                                <div class="popup-preview-copy">
                                    <small>{{ $previewSubtitle }}</small>
                                    <h3>{{ $previewTitle }}</h3>
                                    <p>{{ \Illuminate\Support\Str::limit($previewBody, 170) }}</p>
                                    <span class="popup-preview-cta">{{ $previewCta }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="small text-muted mt-3">
                            L'anteprima usa la lingua principale dello store. Il popup reale usa la lingua scelta dal cliente.
                        </div>
                    </div>
                </div>

                <div class="popup-editor-actions">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body d-flex gap-2 justify-content-between">
                            <a href="{{ route('admin.storefront-popups.index') }}" class="btn btn-outline-secondary">
                                Annulla
                            </a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fa-solid fa-floppy-disk me-1"></i>
                                {{ $submitLabel }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

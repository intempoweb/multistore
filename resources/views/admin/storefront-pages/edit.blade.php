@extends('layouts.admin')

@section('title', 'Modifica contenuti pagina')

@section('content')
@php
    $usesTranslations = $usesTranslations ?? false;
    $contentLocale = $contentLocale ?? app()->getLocale();
    $pageEditorSchema = $pageEditorSchema ?? [];
    $blockEditorSchemas = $blockEditorSchemas ?? collect();
    $editableBlocks = $page->blocks->filter(
        fn ($block) => (bool) data_get($blockEditorSchemas->get($block->id, []), 'editable', true)
    );
    $storefrontBaseUrl = rtrim((string) ($storefrontBaseUrl ?? config('app.url')), '/');
    $publicLocalePrefix = $usesTranslations ? '/' . trim((string) $contentLocale, '/') : '';
    $publicSlug = trim((string) $page->slug, '/');
    $publicPath = $publicSlug === '' || $publicSlug === 'home'
        ? ($publicLocalePrefix ?: '/')
        : $publicLocalePrefix . '/' . $publicSlug;
    $publicPageUrl = $storefrontBaseUrl . ($publicPath === '/' ? '/' : $publicPath);
    $localeNames = [
        'it' => 'Italiano',
        'en' => 'Inglese',
        'es' => 'Spagnolo',
    ];
@endphp

<div class="container-fluid py-4">

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <div class="text-uppercase text-muted small fw-semibold mb-1">Pagine statiche</div>
            <h1 class="h3 mb-1">{{ $pageEditorSchema['title'] ?? 'Modifica contenuti pagina' }}</h1>
            <div class="text-muted">
                {{ $pageEditorSchema['description'] ?? 'Aggiorna testi, immagini e SEO senza modificare la struttura della pagina.' }}
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            @if($page->slug)
                <a
                    href="{{ $publicPageUrl }}"
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

    @if($usesTranslations)
        <div class="alert alert-info border-0 shadow-sm d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-4">
            <div>
                <div class="fw-semibold">Lingua contenuto: {{ $localeNames[$contentLocale] ?? strtoupper($contentLocale) }}</div>
                <div class="small">I testi cambiano per lingua. Immagini, ordine e visibilità restano condivisi.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @foreach(($supportedLocales ?? [$contentLocale]) as $locale)
                    <span class="badge {{ $locale === $contentLocale ? 'text-bg-dark' : 'text-bg-light border text-dark' }}">
                        {{ strtoupper($locale) }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.storefront-pages.update', $page) }}"
        class="mb-4"
    >
        @csrf
        @method('PUT')

        @include('admin.storefront-pages._form', [
            'page' => $page,
            'pageEditorSchema' => $pageEditorSchema,
            'storefrontBaseUrl' => $storefrontBaseUrl,
            'usesTranslations' => $usesTranslations,
            'contentLocale' => $contentLocale,
            'supportedLocales' => $supportedLocales ?? [$contentLocale],
        ])
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pb-0">
            <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3">
                <div>
                    <h2 class="h5 mb-1">Sezioni della pagina</h2>
                    <div class="text-muted small">
                        Modifica solo testi, immagini e bottoni già previsti. Non puoi creare o spostare sezioni da qui.
                    </div>
                </div>

                <span class="badge text-bg-light border">
                    {{ $editableBlocks->count() }} sezioni modificabili
                </span>
            </div>
        </div>

        <div class="card-body">
            @if($editableBlocks->isEmpty())
                <div class="alert alert-light border mb-0">
                    Nessuna sezione modificabile per questa pagina.
                </div>
            @else
                <form
                    method="POST"
                    action="{{ route('admin.storefront-pages.blocks.update', $page) }}"
                    enctype="multipart/form-data"
                >
                    @csrf
                    @method('PUT')

                    <div class="row g-4 align-items-start">
                        <div class="col-12 col-xl-8">
                            <div class="accordion d-grid gap-3" id="staticPageSections">
                                @foreach($editableBlocks as $index => $block)
                            @php
                                $schema = $blockEditorSchemas->get($block->id, []);
                                $fields = $schema['fields'] ?? [];
                                $labels = $schema['labels'] ?? [];
                                $titleVisible = $fields['title'] ?? true;
                                $subtitleVisible = $fields['subtitle'] ?? true;
                                $contentVisible = $fields['content'] ?? true;
                                $imageVisible = $fields['image'] ?? true;
                                $mobileImageVisible = $fields['mobile_image'] ?? true;
                                $videoVisible = $fields['video'] ?? false;
                                $buttonVisible = $fields['button'] ?? true;
                                $galleryVisible = $fields['media_gallery'] ?? false;
                                $imageAltVisible = $fields['image_alt'] ?? ($imageVisible || $mobileImageVisible);
                                $activeVisible = $fields['active'] ?? true;
                                $settings = is_array($block->settings ?? null) ? $block->settings : [];
                                $imageAlt = old("blocks.$index.image_alt", data_get($settings, 'image_alt', ''));
                                $mobileImageAlt = old("blocks.$index.mobile_image_alt", data_get($settings, 'mobile_image_alt', ''));
                                $imageUrl = media_url($block->image_path);
                                $fallbackImageUrl = filled($schema['fallback_image'] ?? null) ? asset($schema['fallback_image']) : null;
                                $imagePreviewUrl = $imageUrl ?: $fallbackImageUrl;
                                $mobileImageUrl = media_url($block->mobile_image_path);
                                $sectionId = 'static_page_section_' . $block->id;
                                $headingId = 'static_page_section_heading_' . $block->id;
                            @endphp

                                    <div class="accordion-item border rounded-4 overflow-hidden">
                                <h3 class="accordion-header" id="{{ $headingId }}">
                                    <button
                                        class="accordion-button {{ $index === 0 ? '' : 'collapsed' }}"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#{{ $sectionId }}"
                                        aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                                        aria-controls="{{ $sectionId }}"
                                    >
                                        <span class="me-3">
                                            <span class="d-block fw-semibold">{{ $schema['label'] ?? ($block->title ?: 'Sezione contenuto') }}</span>
                                            <span class="d-block small text-muted">{{ $schema['help'] ?? 'Aggiorna i contenuti previsti per questa sezione.' }}</span>
                                        </span>
                                    </button>
                                </h3>

                                <div
                                    id="{{ $sectionId }}"
                                    class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}"
                                    aria-labelledby="{{ $headingId }}"
                                    data-bs-parent="#staticPageSections"
                                >
                                    <div class="accordion-body">
                                        <input type="hidden" name="blocks[{{ $index }}][id]" value="{{ $block->id }}">
                                        <input type="hidden" name="blocks[{{ $index }}][type]" value="{{ old("blocks.$index.type", $block->type) }}">
                                        <input type="hidden" name="blocks[{{ $index }}][name]" value="{{ old("blocks.$index.name", $block->name) }}">
                                        <input type="hidden" name="blocks[{{ $index }}][sort_order]" value="{{ old("blocks.$index.sort_order", $block->sort_order) }}">
                                        <input type="hidden" name="blocks[{{ $index }}][image_path]" value="{{ old("blocks.$index.image_path", $block->image_path) }}">
                                        <input type="hidden" name="blocks[{{ $index }}][mobile_image_path]" value="{{ old("blocks.$index.mobile_image_path", $block->mobile_image_path) }}">
                                        <input type="hidden" name="blocks[{{ $index }}][video_path]" value="{{ old("blocks.$index.video_path", $block->video_path) }}">

                                        <div class="row g-4">
                                            <div class="col-12">
                                                <div class="storefront-editor-section-preview border rounded-3 p-3 bg-light">
                                                    <div class="small text-muted mb-2">Anteprima contenuto sezione</div>
                                                    <div class="d-flex flex-column flex-lg-row gap-3">
                                                        @if($imagePreviewUrl)
                                                            <div class="storefront-editor-section-thumb rounded-3 overflow-hidden bg-white border">
                                                                <img src="{{ $imagePreviewUrl }}" alt="{{ $imageAlt ?: $block->title }}" class="w-100 h-100 object-fit-cover">
                                                            </div>
                                                        @endif
                                                        <div class="flex-fill">
                                                            @if($block->subtitle)
                                                                <div class="small text-uppercase text-muted fw-semibold">{{ $block->subtitle }}</div>
                                                            @endif
                                                            <div class="fw-semibold">{{ $block->title ?: ($schema['label'] ?? 'Sezione contenuto') }}</div>
                                                            @if($block->content)
                                                                <div class="small text-muted mt-1">{{ \Illuminate\Support\Str::limit(strip_tags((string) $block->content), 180) }}</div>
                                                            @endif
                                                            @if($block->button_label)
                                                                <span class="badge text-bg-light border mt-2">{{ $block->button_label }}</span>
                                                            @endif
                                                            @if(!$imageUrl && $fallbackImageUrl)
                                                                <span class="badge text-bg-light border mt-2">{{ $schema['fallback_image_label'] ?? 'Immagine del tema' }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            @if($activeVisible)
                                                <div class="col-12">
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
                                                        <label class="form-check-label fw-semibold" for="block_active_{{ $block->id }}">
                                                            Mostra questa sezione nella pagina
                                                        </label>
                                                    </div>
                                                </div>
                                            @else
                                                <input type="hidden" name="blocks[{{ $index }}][is_active]" value="{{ old("blocks.$index.is_active", (int) $block->is_active) }}">
                                            @endif

                                            @if($titleVisible)
                                                <div class="col-12 col-lg-6">
                                                    <label class="form-label fw-semibold" for="block_title_{{ $block->id }}">
                                                        {{ $labels['title'] ?? 'Titolo' }}
                                                        @if($usesTranslations)
                                                            <span class="text-muted small">({{ strtoupper($contentLocale) }})</span>
                                                        @endif
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="blocks[{{ $index }}][title]"
                                                        id="block_title_{{ $block->id }}"
                                                        class="form-control"
                                                        value="{{ old("blocks.$index.title", $block->title) }}"
                                                    >
                                                </div>
                                            @else
                                                <input type="hidden" name="blocks[{{ $index }}][title]" value="{{ old("blocks.$index.title", $block->title) }}">
                                            @endif

                                            @if($subtitleVisible)
                                                <div class="col-12 col-lg-6">
                                                    <label class="form-label fw-semibold" for="block_subtitle_{{ $block->id }}">
                                                        {{ $labels['subtitle'] ?? 'Sottotitolo' }}
                                                        @if($usesTranslations)
                                                            <span class="text-muted small">({{ strtoupper($contentLocale) }})</span>
                                                        @endif
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="blocks[{{ $index }}][subtitle]"
                                                        id="block_subtitle_{{ $block->id }}"
                                                        class="form-control"
                                                        value="{{ old("blocks.$index.subtitle", $block->subtitle) }}"
                                                    >
                                                </div>
                                            @else
                                                <input type="hidden" name="blocks[{{ $index }}][subtitle]" value="{{ old("blocks.$index.subtitle", $block->subtitle) }}">
                                            @endif

                                            @if($contentVisible)
                                                <div class="col-12">
                                                    <label class="form-label fw-semibold" for="block_content_{{ $block->id }}">
                                                        {{ $labels['content'] ?? 'Testo' }}
                                                        @if($usesTranslations)
                                                            <span class="text-muted small">({{ strtoupper($contentLocale) }})</span>
                                                        @endif
                                                    </label>
                                                    <textarea
                                                        name="blocks[{{ $index }}][content]"
                                                        id="block_content_{{ $block->id }}"
                                                        rows="4"
                                                        class="form-control"
                                                    >{{ old("blocks.$index.content", $block->content) }}</textarea>
                                                </div>
                                            @else
                                                <input type="hidden" name="blocks[{{ $index }}][content]" value="{{ old("blocks.$index.content", $block->content) }}">
                                            @endif

                                            @if($imageVisible)
                                                <div class="col-12 col-lg-6">
                                                    <label class="form-label fw-semibold" for="block_image_{{ $block->id }}">
                                                        {{ $labels['image'] ?? 'Immagine principale' }}
                                                    </label>

                                                    @if($imageUrl)
                                                        <div class="mb-2 rounded-3 overflow-hidden border bg-white storefront-page-editor-preview">
                                                            <img src="{{ $imageUrl }}" alt="{{ $imageAlt ?: $block->title }}" class="img-fluid d-block">
                                                        </div>
                                                    @elseif($fallbackImageUrl)
                                                        <div class="mb-2">
                                                            <div class="rounded-3 overflow-hidden border bg-white storefront-page-editor-preview">
                                                                <img src="{{ $fallbackImageUrl }}" alt="{{ $imageAlt ?: $block->title }}" class="img-fluid d-block">
                                                            </div>
                                                            <div class="form-text">
                                                                {{ $schema['fallback_image_label'] ?? 'Immagine del tema' }}. Carica un file solo se vuoi sostituirla da back office.
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <input
                                                        type="file"
                                                        name="blocks[{{ $index }}][image_file]"
                                                        id="block_image_{{ $block->id }}"
                                                        class="form-control"
                                                        accept="image/*"
                                                    >
                                                    <div class="form-text">{{ $schema['media_help'] ?? 'Carica un nuovo file solo se vuoi sostituire l’immagine attuale.' }}</div>
                                                </div>
                                            @endif

                                            @if($mobileImageVisible)
                                                <div class="col-12 col-lg-6">
                                                    <label class="form-label fw-semibold" for="block_mobile_image_{{ $block->id }}">
                                                        {{ $labels['mobile_image'] ?? 'Immagine mobile' }}
                                                    </label>

                                                    @if($mobileImageUrl)
                                                        <div class="mb-2 rounded-3 overflow-hidden border bg-white storefront-page-editor-preview storefront-page-editor-preview-sm">
                                                            <img src="{{ $mobileImageUrl }}" alt="{{ $mobileImageAlt ?: $imageAlt ?: $block->title }}" class="img-fluid d-block">
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
                                            @endif

                                            @if($imageAltVisible)
                                                <div class="col-12 col-lg-6">
                                                    <label class="form-label fw-semibold" for="block_image_alt_{{ $block->id }}">
                                                        {{ $labels['image_alt'] ?? 'Testo alternativo immagine' }}
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="blocks[{{ $index }}][image_alt]"
                                                        id="block_image_alt_{{ $block->id }}"
                                                        class="form-control"
                                                        value="{{ $imageAlt }}"
                                                        maxlength="255"
                                                        placeholder="Descrivi l'immagine per accessibilità e SEO"
                                                    >
                                                    <div class="form-text">Descrivi cosa si vede nell'immagine. Evita testi generici come “foto”.</div>
                                                </div>

                                                @if($mobileImageVisible)
                                                    <div class="col-12 col-lg-6">
                                                        <label class="form-label fw-semibold" for="block_mobile_image_alt_{{ $block->id }}">
                                                            Testo alternativo immagine mobile
                                                        </label>
                                                        <input
                                                            type="text"
                                                            name="blocks[{{ $index }}][mobile_image_alt]"
                                                            id="block_mobile_image_alt_{{ $block->id }}"
                                                            class="form-control"
                                                            value="{{ $mobileImageAlt }}"
                                                            maxlength="255"
                                                            placeholder="Lascia vuoto se uguale all'immagine principale"
                                                        >
                                                    </div>
                                                @else
                                                    <input type="hidden" name="blocks[{{ $index }}][mobile_image_alt]" value="{{ $mobileImageAlt }}">
                                                @endif
                                            @else
                                                <input type="hidden" name="blocks[{{ $index }}][image_alt]" value="{{ $imageAlt }}">
                                                <input type="hidden" name="blocks[{{ $index }}][mobile_image_alt]" value="{{ $mobileImageAlt }}">
                                            @endif

                                            @if($videoVisible)
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
                                                        <div class="form-text">È già presente un video per questa sezione.</div>
                                                    @endif
                                                </div>
                                            @endif

                                            @if($galleryVisible)
                                                @php
                                                    $heroMediaRows = collect($block->media ?? []);
                                                    $nextMediaIndex = $heroMediaRows->count();
                                                @endphp
                                                <div class="col-12">
                                                    <div class="border-top pt-3 mt-2">
                                                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                                            <div>
                                                                <div class="fw-semibold">Immagini e video della sezione</div>
                                                                <div class="small text-muted">Aggiungi o sostituisci media già previsti dal layout.</div>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-outline-primary"
                                                                data-add-hero-media
                                                                data-block-index="{{ $index }}"
                                                            >
                                                                <i class="fa-solid fa-plus me-1"></i>
                                                                Aggiungi media
                                                            </button>
                                                        </div>

                                                        <div class="d-grid gap-3" data-hero-media-list="{{ $index }}" data-next-index="{{ $nextMediaIndex }}">
                                                            @foreach($heroMediaRows as $mediaIndex => $media)
                                                                @include('admin.storefront-pages.partials.hero-media-row', [
                                                                    'blockIndex' => $index,
                                                                    'mediaIndex' => $mediaIndex,
                                                                    'media' => $media,
                                                                ])
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            @if($buttonVisible)
                                                <div class="col-12 col-lg-6">
                                                    <label class="form-label fw-semibold" for="block_button_label_{{ $block->id }}">
                                                        {{ $labels['button_label'] ?? 'Testo del bottone' }}
                                                        @if($usesTranslations)
                                                            <span class="text-muted small">({{ strtoupper($contentLocale) }})</span>
                                                        @endif
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
                                                        {{ $labels['button_url'] ?? 'Link del bottone' }}
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="blocks[{{ $index }}][button_url]"
                                                        id="block_button_url_{{ $block->id }}"
                                                        class="form-control"
                                                        value="{{ old("blocks.$index.button_url", $block->button_url) }}"
                                                        placeholder="/catalog"
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
                                                            Apri il link in una nuova scheda
                                                        </label>
                                                    </div>
                                                </div>
                                            @else
                                                <input type="hidden" name="blocks[{{ $index }}][button_label]" value="{{ old("blocks.$index.button_label", $block->button_label) }}">
                                                <input type="hidden" name="blocks[{{ $index }}][button_url]" value="{{ old("blocks.$index.button_url", $block->button_url) }}">
                                                <input type="hidden" name="blocks[{{ $index }}][button_new_tab]" value="{{ old("blocks.$index.button_new_tab", (int) $block->button_new_tab) }}">
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-12 col-xl-4">
                            <aside class="storefront-editor-actions card border shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                        <div>
                                            <h3 class="h6 mb-1">Salvataggio sezioni</h3>
                                            <div class="small text-muted">
                                                Salva testi, immagini e bottoni delle sezioni modificabili.
                                            </div>
                                        </div>
                                        <span class="badge text-bg-light border">{{ $editableBlocks->count() }}</span>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fa-solid fa-floppy-disk me-2"></i>
                                        Salva contenuti
                                    </button>

                                    <div class="small text-muted mt-3">
                                        Il salvataggio vale per tutte le sezioni aperte o chiuse di questa pagina.
                                    </div>

                                    <hr>

                                    <div class="fw-semibold small text-uppercase text-muted mb-2">Vai alla sezione</div>
                                    <div class="storefront-editor-section-nav d-grid gap-2">
                                        @foreach($editableBlocks as $navIndex => $navBlock)
                                            @php
                                                $navSchema = $blockEditorSchemas->get($navBlock->id, []);
                                                $navSectionId = 'static_page_section_' . $navBlock->id;
                                            @endphp
                                            <a
                                                href="#{{ $navSectionId }}"
                                                class="btn btn-sm btn-light border text-start"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#{{ $navSectionId }}"
                                                aria-controls="{{ $navSectionId }}"
                                            >
                                                <span class="d-block fw-semibold text-truncate">
                                                    {{ $navSchema['label'] ?? ($navBlock->title ?: 'Sezione contenuto') }}
                                                </span>
                                                @if($navBlock->title)
                                                    <span class="d-block small text-muted text-truncate">{{ $navBlock->title }}</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>

</div>

<template id="hero-media-row-template">
    @include('admin.storefront-pages.partials.hero-media-row', [
        'blockIndex' => '__BLOCK__',
        'mediaIndex' => '__MEDIA__',
        'media' => new \App\Models\StorefrontPageBlockMedia(['media_type' => 'image', 'is_active' => true]),
    ])
</template>
@endsection

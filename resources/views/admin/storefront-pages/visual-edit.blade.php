@extends('layouts.admin')

@section('title', 'Editor visuale contenuti')

@section('content')
@php
    $usesTranslations = $usesTranslations ?? false;
    $contentLocale = $contentLocale ?? app()->getLocale();
    $pageEditorSchema = $pageEditorSchema ?? [];
    $blockEditorSchemas = $blockEditorSchemas ?? collect();
    $editableBlocks = $page->blocks->filter(
        fn ($block) => (bool) data_get($blockEditorSchemas->get($block->id, []), 'editable', true)
    )->values();
    $storefrontBaseUrl = rtrim((string) ($storefrontBaseUrl ?? config('app.url')), '/');
    $publicLocalePrefix = $usesTranslations ? '/' . trim((string) $contentLocale, '/') : '';
    $publicSlug = trim((string) $page->slug, '/');
    $publicPath = $publicSlug === '' || $publicSlug === 'home'
        ? ($publicLocalePrefix ?: '/')
        : $publicLocalePrefix . '/' . $publicSlug;
    $publicPageUrl = $storefrontBaseUrl . ($publicPath === '/' ? '/' : $publicPath);
    $previewFrameUrl = route('admin.storefront-pages.preview-frame', $page);
    $localeNames = [
        'it' => 'Italiano',
        'en' => 'Inglese',
        'es' => 'Spagnolo',
    ];
@endphp

<div class="storefront-visual-editor">
    <div class="storefront-visual-editor-bar">
        <div>
            <div class="text-uppercase text-muted small fw-semibold">Editor visuale controllato</div>
            <h1 class="h4 mb-1">{{ $pageEditorSchema['title'] ?? $page->title }}</h1>
            <div class="text-muted small">
                {{ $store->name }} · {{ $localeNames[$contentLocale] ?? strtoupper($contentLocale) }} · puoi modificare solo testi, immagini e CTA già previsti.
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ $publicPageUrl }}" target="_blank" class="btn btn-outline-dark">
                <i class="fa-solid fa-up-right-from-square me-2"></i>
                Apri FE
            </a>
            <a href="{{ route('admin.storefront-pages.edit', $page) }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-sliders me-2"></i>
                BO avanzato
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

    <div class="storefront-visual-editor-grid">
        <section class="storefront-visual-preview card border-0 shadow-sm">
            <div class="storefront-visual-preview-header">
                <div>
                    <div class="fw-semibold">Anteprima sito</div>
                    <div class="small text-muted text-truncate">{{ $publicPageUrl }}</div>
                </div>
                <span class="badge text-bg-light border">{{ $editableBlocks->count() }} sezioni</span>
            </div>
            <iframe
                src="{{ $previewFrameUrl }}"
                title="Anteprima {{ $page->title }}"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
            ></iframe>
        </section>

        <aside class="storefront-visual-panel card border-0 shadow-sm">
            <div class="storefront-visual-panel-header">
                <div>
                    <div class="fw-semibold">Modifica contenuti</div>
                    <div class="small text-muted">Salva una sezione alla volta.</div>
                </div>
            </div>

            <div class="storefront-visual-panel-body">
                @if($editableBlocks->isEmpty())
                    <div class="alert alert-light border mb-0">
                        Nessuna sezione modificabile per questa pagina.
                    </div>
                @else
                    <div class="accordion" id="visualEditorSections">
                        @foreach($editableBlocks as $index => $block)
                            @php
                                $schema = $blockEditorSchemas->get($block->id, []);
                                $fields = $schema['fields'] ?? [];
                                $labels = $schema['labels'] ?? [];
                                $settings = is_array($block->settings ?? null) ? $block->settings : [];
                                $imageAlt = data_get($settings, 'image_alt', '');
                                $mobileImageAlt = data_get($settings, 'mobile_image_alt', '');
                                $specs = data_get($settings, 'specs.' . strtolower((string) $contentLocale));
                                if ($specs === null) {
                                    $specs = data_get($settings, 'specs', []);
                                }
                                if (is_array($specs) && ! array_is_list($specs)) {
                                    $specs = [];
                                }
                                $specsValue = is_array($specs) ? implode("\n", $specs) : (string) $specs;
                                $imageUrl = media_url($block->image_path) ?: (filled($schema['fallback_image'] ?? null) ? b2c_theme_asset_url($schema['fallback_image']) : null);
                                $mobileImageUrl = media_url($block->mobile_image_path);
                                $sectionId = 'visual_section_' . $block->id;
                                $headingId = 'visual_section_heading_' . $block->id;
                                $blockIndex = 0;
                            @endphp

                            <div class="accordion-item storefront-visual-section">
                                <h2 class="accordion-header" id="{{ $headingId }}">
                                    <button
                                        class="accordion-button {{ $index === 0 ? '' : 'collapsed' }}"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#{{ $sectionId }}"
                                        aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                                        aria-controls="{{ $sectionId }}"
                                    >
                                        <span class="min-w-0">
                                            <span class="d-block fw-semibold text-truncate">{{ $schema['label'] ?? ($block->title ?: 'Sezione contenuto') }}</span>
                                            <span class="d-block small text-muted text-truncate">{{ $block->title ?: ($schema['help'] ?? 'Contenuto editoriale') }}</span>
                                        </span>
                                    </button>
                                </h2>

                                <div
                                    id="{{ $sectionId }}"
                                    class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}"
                                    aria-labelledby="{{ $headingId }}"
                                    data-bs-parent="#visualEditorSections"
                                >
                                    <div class="accordion-body">
                                        <form
                                            method="POST"
                                            action="{{ route('admin.storefront-pages.blocks.update', $page) }}"
                                            enctype="multipart/form-data"
                                            class="d-grid gap-3"
                                        >
                                            @csrf
                                            @method('PUT')

                                            <input type="hidden" name="blocks[{{ $blockIndex }}][id]" value="{{ $block->id }}">
                                            <input type="hidden" name="blocks[{{ $blockIndex }}][type]" value="{{ $block->type }}">
                                            <input type="hidden" name="blocks[{{ $blockIndex }}][name]" value="{{ $block->name }}">
                                            <input type="hidden" name="blocks[{{ $blockIndex }}][sort_order]" value="{{ $block->sort_order }}">
                                            <input type="hidden" name="blocks[{{ $blockIndex }}][is_active]" value="{{ (int) $block->is_active }}">
                                            <input type="hidden" name="blocks[{{ $blockIndex }}][image_path]" value="{{ $block->image_path }}">
                                            <input type="hidden" name="blocks[{{ $blockIndex }}][mobile_image_path]" value="{{ $block->mobile_image_path }}">
                                            <input type="hidden" name="blocks[{{ $blockIndex }}][video_path]" value="{{ $block->video_path }}">

                                            @if($imageUrl)
                                                <div class="storefront-visual-thumb">
                                                    <img src="{{ $imageUrl }}" alt="{{ $imageAlt ?: $block->title }}">
                                                </div>
                                            @endif

                                            @if($fields['title'] ?? true)
                                                <div>
                                                    <label class="form-label fw-semibold" for="visual_title_{{ $block->id }}">
                                                        {{ $labels['title'] ?? 'Titolo' }}
                                                    </label>
                                                    <input id="visual_title_{{ $block->id }}" class="form-control" type="text" name="blocks[{{ $blockIndex }}][title]" value="{{ $block->title }}">
                                                </div>
                                            @else
                                                <input type="hidden" name="blocks[{{ $blockIndex }}][title]" value="{{ $block->title }}">
                                            @endif

                                            @if($fields['subtitle'] ?? true)
                                                <div>
                                                    <label class="form-label fw-semibold" for="visual_subtitle_{{ $block->id }}">
                                                        {{ $labels['subtitle'] ?? 'Sottotitolo' }}
                                                    </label>
                                                    <input id="visual_subtitle_{{ $block->id }}" class="form-control" type="text" name="blocks[{{ $blockIndex }}][subtitle]" value="{{ $block->subtitle }}">
                                                </div>
                                            @else
                                                <input type="hidden" name="blocks[{{ $blockIndex }}][subtitle]" value="{{ $block->subtitle }}">
                                            @endif

                                            @if($fields['content'] ?? true)
                                                <div>
                                                    <label class="form-label fw-semibold" for="visual_content_{{ $block->id }}">
                                                        {{ $labels['content'] ?? 'Testo' }}
                                                    </label>
                                                    <textarea id="visual_content_{{ $block->id }}" class="form-control" rows="5" name="blocks[{{ $blockIndex }}][content]">{{ $block->content }}</textarea>
                                                </div>
                                            @else
                                                <input type="hidden" name="blocks[{{ $blockIndex }}][content]" value="{{ $block->content }}">
                                            @endif

                                            @if($fields['specs'] ?? false)
                                                <div>
                                                    <label class="form-label fw-semibold" for="visual_specs_{{ $block->id }}">
                                                        {{ $labels['specs'] ?? 'Tag / caratteristiche' }}
                                                    </label>
                                                    <textarea id="visual_specs_{{ $block->id }}" class="form-control" rows="3" name="blocks[{{ $blockIndex }}][specs]" placeholder="Un tag per riga">{{ $specsValue }}</textarea>
                                                </div>
                                            @endif

                                            @if($fields['image'] ?? true)
                                                <div>
                                                    <label class="form-label fw-semibold" for="visual_image_{{ $block->id }}">
                                                        {{ $labels['image'] ?? 'Immagine principale' }}
                                                    </label>
                                                    <input id="visual_image_{{ $block->id }}" class="form-control" type="file" name="blocks[{{ $blockIndex }}][image_file]" accept="image/*">
                                                    <div class="form-text">{{ $schema['media_help'] ?? 'Carica un file solo se vuoi sostituire l’immagine attuale.' }}</div>
                                                </div>
                                            @endif

                                            @if($fields['mobile_image'] ?? true)
                                                <div>
                                                    <label class="form-label fw-semibold" for="visual_mobile_image_{{ $block->id }}">
                                                        {{ $labels['mobile_image'] ?? 'Immagine mobile' }}
                                                    </label>
                                                    @if($mobileImageUrl)
                                                        <div class="small text-muted mb-1">È già presente un’immagine mobile.</div>
                                                    @endif
                                                    <input id="visual_mobile_image_{{ $block->id }}" class="form-control" type="file" name="blocks[{{ $blockIndex }}][mobile_image_file]" accept="image/*">
                                                </div>
                                            @endif

                                            @if($fields['image_alt'] ?? (($fields['image'] ?? true) || ($fields['mobile_image'] ?? true)))
                                                <div>
                                                    <label class="form-label fw-semibold" for="visual_image_alt_{{ $block->id }}">
                                                        {{ $labels['image_alt'] ?? 'Testo alternativo immagine' }}
                                                    </label>
                                                    <input id="visual_image_alt_{{ $block->id }}" class="form-control" type="text" name="blocks[{{ $blockIndex }}][image_alt]" value="{{ $imageAlt }}" maxlength="255">
                                                </div>
                                                <input type="hidden" name="blocks[{{ $blockIndex }}][mobile_image_alt]" value="{{ $mobileImageAlt }}">
                                            @else
                                                <input type="hidden" name="blocks[{{ $blockIndex }}][image_alt]" value="{{ $imageAlt }}">
                                                <input type="hidden" name="blocks[{{ $blockIndex }}][mobile_image_alt]" value="{{ $mobileImageAlt }}">
                                            @endif

                                            @if($fields['button'] ?? true)
                                                <div class="row g-2">
                                                    <div class="col-12">
                                                        <label class="form-label fw-semibold" for="visual_button_label_{{ $block->id }}">
                                                            {{ $labels['button_label'] ?? 'Testo del bottone' }}
                                                        </label>
                                                        <input id="visual_button_label_{{ $block->id }}" class="form-control" type="text" name="blocks[{{ $blockIndex }}][button_label]" value="{{ $block->button_label }}">
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label fw-semibold" for="visual_button_url_{{ $block->id }}">
                                                            {{ $labels['button_url'] ?? 'Link del bottone' }}
                                                        </label>
                                                        <input id="visual_button_url_{{ $block->id }}" class="form-control" type="text" name="blocks[{{ $blockIndex }}][button_url]" value="{{ $block->button_url }}" placeholder="/catalog">
                                                    </div>
                                                </div>

                                                <div class="form-check">
                                                    <input type="hidden" name="blocks[{{ $blockIndex }}][button_new_tab]" value="0">
                                                    <input id="visual_new_tab_{{ $block->id }}" class="form-check-input" type="checkbox" name="blocks[{{ $blockIndex }}][button_new_tab]" value="1" @checked($block->button_new_tab)>
                                                    <label class="form-check-label" for="visual_new_tab_{{ $block->id }}">Apri in nuova scheda</label>
                                                </div>
                                            @else
                                                <input type="hidden" name="blocks[{{ $blockIndex }}][button_label]" value="{{ $block->button_label }}">
                                                <input type="hidden" name="blocks[{{ $blockIndex }}][button_url]" value="{{ $block->button_url }}">
                                                <input type="hidden" name="blocks[{{ $blockIndex }}][button_new_tab]" value="{{ (int) $block->button_new_tab }}">
                                            @endif

                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fa-solid fa-floppy-disk me-2"></i>
                                                Salva questa sezione
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection

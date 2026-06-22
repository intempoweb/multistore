@extends('layouts.admin')

@section('title', 'SEO storefront')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">SEO catalogo e collezioni</h1>
        <p class="text-muted mb-0">Contenuti editoriali separati dall'ERP per {{ $store->name }}.</p>
    </div>

    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('admin.storefront-seo.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @php
            $entryIndex = 0;
        @endphp

        @foreach($locales as $locale)
            <section class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Lingua {{ strtoupper($locale) }}</h2>
                    <span class="badge text-bg-light border">{{ $locale }}</span>
                </div>
                <div class="card-body">
                    @php
                        $rows = collect([['type' => 'catalog', 'key' => 'catalog', 'label' => 'Catalogo']])
                            ->merge(collect($categoryRowsByLocale[$locale] ?? [])->map(fn ($row) => [
                                'type' => 'collection', 'key' => $row['key'], 'label' => $row['label'],
                            ]));
                    @endphp

                    <div class="accordion" id="seoAccordion{{ $locale }}">
                        @foreach($rows as $row)
                            @php
                                $entry = $entries->get(implode('|', [$locale, $row['type'], $row['key']]));
                                $prefix = "entries[{$entryIndex}]";
                            @endphp
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#seo{{ $locale }}{{ $entryIndex }}">
                                        {{ $row['label'] }}
                                        <code class="ms-2 small">{{ $row['key'] }}</code>
                                    </button>
                                </h3>
                                <div id="seo{{ $locale }}{{ $entryIndex }}" class="accordion-collapse collapse" data-bs-parent="#seoAccordion{{ $locale }}">
                                    <div class="accordion-body">
                                        <input type="hidden" name="{{ $prefix }}[locale]" value="{{ $locale }}">
                                        <input type="hidden" name="{{ $prefix }}[entity_type]" value="{{ $row['type'] }}">
                                        <input type="hidden" name="{{ $prefix }}[entity_key]" value="{{ $row['key'] }}">
                                        <input type="hidden" name="{{ $prefix }}[og_image_path]" value="{{ $entry?->og_image_path }}">
                                        <div class="row g-3">
                                            <div class="col-12 col-lg-6"><label class="form-label">Meta title</label><input class="form-control" name="{{ $prefix }}[meta_title]" maxlength="190" value="{{ $entry?->meta_title }}"></div>
                                            <div class="col-12 col-lg-6"><label class="form-label">H1</label><input class="form-control" name="{{ $prefix }}[heading]" maxlength="190" value="{{ $entry?->heading }}"></div>
                                            <div class="col-12"><label class="form-label">Meta description</label><textarea class="form-control" rows="2" name="{{ $prefix }}[meta_description]">{{ $entry?->meta_description }}</textarea></div>
                                            <div class="col-12"><label class="form-label">Testo introduttivo</label><textarea class="form-control" rows="3" name="{{ $prefix }}[intro]">{{ $entry?->intro }}</textarea></div>
                                            <div class="col-12 col-lg-6"><label class="form-label">Canonical URL</label><input type="url" class="form-control" name="{{ $prefix }}[canonical_url]" value="{{ $entry?->canonical_url }}"></div>
                                            <div class="col-12 col-lg-6"><label class="form-label">Robots</label><select class="form-select" name="{{ $prefix }}[robots]"><option value="index,follow" @selected(($entry?->robots ?? '') === 'index,follow')>index,follow</option><option value="noindex,follow" @selected($entry?->robots === 'noindex,follow')>noindex,follow</option><option value="noindex,nofollow" @selected($entry?->robots === 'noindex,nofollow')>noindex,nofollow</option></select></div>
                                            <div class="col-12 col-lg-6"><label class="form-label">Open Graph title</label><input class="form-control" name="{{ $prefix }}[og_title]" value="{{ $entry?->og_title }}"></div>
                                            <div class="col-12 col-lg-6"><label class="form-label">Immagine social</label><input type="file" class="form-control" name="{{ $prefix }}[og_image_file]" accept="image/*"></div>
                                            <div class="col-12"><label class="form-label">Open Graph description</label><textarea class="form-control" rows="2" name="{{ $prefix }}[og_description]">{{ $entry?->og_description }}</textarea></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @php
                                $entryIndex++;
                            @endphp
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach

        <div class="d-flex justify-content-end"><button class="btn btn-primary btn-lg" type="submit">Salva SEO</button></div>
    </form>
</div>
@endsection

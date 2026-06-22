@extends($storefrontLayout)

@section('title', $storefrontPage?->meta_title ?: ($storefrontPage?->title ?: $store->name))
@section('meta_description', $storefrontPage?->meta_description ?: $storefrontPage?->description)

@section('content')
@php
    $blocks = collect($storefrontPageBlocks ?? []);
    $hero = $blocks->first(fn ($block) => $block->type === 'hero' || $block->name === 'home_hero');
    $editorial = $blocks->first(fn ($block) => $block->type === 'editorial' || $block->name === 'home_story');
    $banner = $blocks->first(fn ($block) => $block->type === 'editorial_banner' || $block->name === 'home_banner');
    $categories = collect($rootCategories ?? []);
    $listingCardsByProductSku = collect($listingCardsByProductSku ?? []);
    $productCollection = collect(method_exists($products, 'items') ? $products->items() : $products);
    $featured = $productCollection->filter(fn ($product) => (bool) ($product->flgnovita_webt01 ?? false))->take(4);
    if ($featured->isEmpty()) $featured = $productCollection->shuffle()->take(4);

    $buttonUrl = function ($block) {
        $url = trim((string) ($block?->button_url ?? ''));
        if ($url === '' || in_array($url, ['/catalog', 'catalog'], true)) return route('storefront.catalog.index');
        return str_starts_with($url, '/') ? url($url) : $url;
    };

    $heroMedia = collect($hero?->activeMedia ?? [])->map(function ($media) {
        return [
            'type' => $media->media_type ?: 'image',
            'desktop' => media_url($media->desktop_path),
            'mobile' => media_url($media->mobile_path),
            'poster' => media_url($media->poster_path),
            'alt' => $media->alt_text,
        ];
    })->filter(fn ($media) => filled($media['desktop']))->values();
    if ($heroMedia->isEmpty() && (filled($hero?->image_path) || filled($hero?->video_path))) {
        $heroMedia = collect([[
            'type' => filled($hero?->video_path) ? 'video' : 'image',
            'desktop' => media_url($hero?->video_path ?: $hero?->image_path),
            'mobile' => media_url($hero?->mobile_image_path),
            'poster' => media_url($hero?->image_path),
            'alt' => $hero?->title,
        ]]);
    }

    $findCategory = function (array $terms) use ($categories) {
        return $categories->first(function ($category) use ($terms) {
            $haystack = mb_strtolower(trim(($category['label'] ?? '') . ' ' . ($category['slug'] ?? '')));
            return collect($terms)->contains(fn ($term) => str_contains($haystack, $term));
        });
    };
    $agendaCategory = $findCategory(['agenda']);
    $notebookCategory = $findCategory(['taccuin', 'quadern']);
    $formatGroups = collect([
        'agende' => [
            'label' => __('Agende'),
            'visible' => (bool) $agendaCategory,
            'items' => collect([
                ['label' => __('Agenda giornaliera'), 'terms' => ['giornal'], 'image' => asset('images/themes/b2c/ciak/formats/agenda-giornaliera.jpg')],
                ['label' => __('Agenda settimanale'), 'terms' => ['settiman'], 'image' => asset('images/themes/b2c/ciak/formats/agenda-settimanale.jpg')],
            ]),
        ],
        'taccuini' => [
            'label' => __('Taccuini'),
            'visible' => (bool) $notebookCategory,
            'items' => collect([
                ['label' => __('Pagine a puntini'), 'terms' => ['puntin'], 'image' => asset('images/themes/b2c/ciak/formats/taccuino-puntini.jpg')],
                ['label' => __('Pagine a righe'), 'terms' => ['righe'], 'image' => asset('images/themes/b2c/ciak/formats/taccuino-righe.jpg')],
                ['label' => __('Pagine bianche'), 'terms' => ['bianch', 'vuote'], 'image' => asset('images/themes/b2c/ciak/formats/taccuino-pagine-bianche.jpg')],
            ]),
        ],
    ])->filter(fn ($group) => $group['visible']);

    $editorialImage = media_url($editorial?->image_path);
    $editorialMobileImage = media_url($editorial?->mobile_image_path);
@endphp

<div class="ciak-home">
    <section class="ciak-hero" data-ciak-hero>
        <div class="ciak-hero-copy">
            @if(filled($hero?->subtitle))<p class="ciak-eyebrow">{{ $hero->subtitle }}</p>@endif
            <h1>{{ $hero?->title ?: $storefrontPage?->title ?: $store->name }}</h1>
            @if(filled($hero?->content))<p class="ciak-lead">{{ $hero->content }}</p>@endif
            @if(filled($hero?->button_label))
                <a class="ciak-primary-link" href="{{ $buttonUrl($hero) }}" @if($hero?->button_new_tab) target="_blank" rel="noopener" @endif>{{ $hero->button_label }}<i data-lucide="arrow-right"></i></a>
            @endif
        </div>
        <div class="ciak-hero-media {{ $heroMedia->isEmpty() ? 'is-empty' : '' }}">
            @foreach($heroMedia as $index => $media)
                <div class="ciak-hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-ciak-hero-slide>
                    @if($media['type'] === 'video')
                        <video muted loop playsinline preload="metadata" poster="{{ $media['poster'] }}"><source src="{{ $media['desktop'] }}"></video>
                    @else
                        <picture>@if($media['mobile'])<source media="(max-width: 767px)" srcset="{{ $media['mobile'] }}">@endif<img src="{{ $media['desktop'] }}" alt="{{ $media['alt'] ?: ($hero?->title ?: $store->name) }}" fetchpriority="high"></picture>
                    @endif
                </div>
            @endforeach
            @if($heroMedia->count() > 1)
                <div class="ciak-hero-controls"><button type="button" data-ciak-hero-prev aria-label="{{ __('Precedente') }}"><i data-lucide="arrow-left"></i></button><span><b data-ciak-hero-current>1</b> / {{ $heroMedia->count() }}</span><button type="button" data-ciak-hero-next aria-label="{{ __('Successivo') }}"><i data-lucide="arrow-right"></i></button></div>
            @endif
        </div>
    </section>

    @if($formatGroups->isNotEmpty())
        <section class="ciak-format-section ciak-shell" data-ciak-formats>
            <header class="ciak-section-heading ciak-section-heading-centered"><p class="ciak-eyebrow">{{ __('Trova quello giusto') }}</p></header>
            <div class="ciak-format-tabs" role="tablist">
                @foreach($formatGroups as $key => $group)<button type="button" class="{{ $loop->first ? 'is-active' : '' }}" data-ciak-format-tab="{{ $key }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $group['label'] }}</button>@endforeach
            </div>
            @foreach($formatGroups as $key => $group)
                <div class="ciak-format-track {{ $loop->first ? 'is-active' : '' }}" data-ciak-format-panel="{{ $key }}">
                    @foreach($group['items'] as $item)
                        @php
                            $category = $categories->first(function ($candidate) use ($item) {
                                $haystack = mb_strtolower(trim(($candidate['label'] ?? '') . ' ' . ($candidate['slug'] ?? '')));
                                return collect($item['terms'])->contains(fn ($term) => str_contains($haystack, $term));
                            });
                            $fallbackCategory = $key === 'agende' ? $agendaCategory : $notebookCategory;
                            $target = $category ?: $fallbackCategory;
                        @endphp
                        @if($target)
                            <a class="ciak-format-card" href="{{ route('storefront.category.show', $target['slug']) }}">
                                <span><img src="{{ $item['image'] }}" alt="" loading="lazy"></span><strong>{{ $item['label'] }}</strong>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </section>
    @endif

    @if($featured->isNotEmpty())
        <section class="ciak-products-section ciak-shell">
            <header class="ciak-section-heading"><div><p class="ciak-eyebrow">{{ __('In evidenza') }}</p><h2>{{ __('Scelti per te') }}</h2></div><a href="{{ route('storefront.catalog.index') }}">{{ __('Vedi tutto') }}<i data-lucide="arrow-right"></i></a></header>
            <div class="ciak-products-grid">
                @foreach($featured as $product)
                    @php($listingCard = collect($listingCardsByProductSku->get((string) $product->sku, [])))
                    @include('storefront.base.partials.product-card', ['product' => $product, 'listingCard' => $listingCard])
                @endforeach
            </div>
        </section>
    @endif

    @if($editorial && $editorialImage)
        <section class="ciak-editorial">
            <div class="ciak-editorial-media"><picture>@if($editorialMobileImage)<source media="(max-width:767px)" srcset="{{ $editorialMobileImage }}">@endif<img src="{{ $editorialImage }}" alt="{{ $editorial->title ?: $store->name }}" loading="lazy"></picture></div>
            <div class="ciak-editorial-copy">@if($editorial->subtitle)<p class="ciak-eyebrow">{{ $editorial->subtitle }}</p>@endif<h2>{{ $editorial->title }}</h2>@if($editorial->content)<p>{{ $editorial->content }}</p>@endif @if($editorial->button_label)<a href="{{ $buttonUrl($editorial) }}">{{ $editorial->button_label }}<i data-lucide="arrow-right"></i></a>@endif</div>
        </section>
    @elseif($banner && media_url($banner?->image_path))
        <section class="ciak-editorial"><div class="ciak-editorial-media"><img src="{{ media_url($banner->image_path) }}" alt="{{ $banner->title ?: $store->name }}" loading="lazy"></div><div class="ciak-editorial-copy">@if($banner->subtitle)<p class="ciak-eyebrow">{{ $banner->subtitle }}</p>@endif<h2>{{ $banner->title }}</h2>@if($banner->content)<p>{{ $banner->content }}</p>@endif @if($banner->button_label)<a href="{{ $buttonUrl($banner) }}">{{ $banner->button_label }}<i data-lucide="arrow-right"></i></a>@endif</div></section>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const hero = document.querySelector('[data-ciak-hero]');
    const slides = hero ? Array.from(hero.querySelectorAll('[data-ciak-hero-slide]')) : [];
    let current = 0;
    const show = function (next) {
        if (!slides.length) return;
        current = (next + slides.length) % slides.length;
        slides.forEach(function (slide, index) { slide.classList.toggle('is-active', index === current); const video = slide.querySelector('video'); if (video) index === current ? video.play().catch(function(){}) : video.pause(); });
        const label = hero.querySelector('[data-ciak-hero-current]'); if (label) label.textContent = current + 1;
    };
    hero?.querySelector('[data-ciak-hero-prev]')?.addEventListener('click', function () { show(current - 1); });
    hero?.querySelector('[data-ciak-hero-next]')?.addEventListener('click', function () { show(current + 1); });
    show(0);

    document.querySelectorAll('[data-ciak-format-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            const key = tab.dataset.ciakFormatTab;
            document.querySelectorAll('[data-ciak-format-tab]').forEach(function (item) { const active = item === tab; item.classList.toggle('is-active', active); item.setAttribute('aria-selected', active ? 'true' : 'false'); });
            document.querySelectorAll('[data-ciak-format-panel]').forEach(function (panel) { panel.classList.toggle('is-active', panel.dataset.ciakFormatPanel === key); });
        });
    });
});
</script>
@endpush

@extends($storefrontLayout)

@php
    use App\Models\StorefrontPage;
    use App\Repositories\Storefront\CatalogRepository;

    $locale = $locale ?? app()->getLocale();
    $page = $store
        ? StorefrontPage::query()
            ->where('store_id', $store->id)
            ->where('slug', 'home')
            ->where('is_active', true)
            ->with('activeBlocks.activeMedia')
            ->first()
        : null;

    $blocks = collect($page?->activeBlocks ?? []);
    $heroBlock = $blocks->firstWhere('name', 'home_hero') ?? $blocks->firstWhere('type', 'hero');
    $storyBlock = $blocks->firstWhere('name', 'home_story') ?? $blocks->firstWhere('type', 'editorial');
    $bannerBlock = $blocks->firstWhere('name', 'home_banner') ?? $blocks->firstWhere('type', 'editorial_banner');
    $editorialBlock = $storyBlock ?: $bannerBlock;
    $catalogUrl = route('storefront.catalog.index');

    $resolveBlockUrl = static function ($block, string $fallback) {
        $url = trim((string) ($block?->button_url ?? ''));

        if ($url === '') {
            return $fallback;
        }

        if ($url === '/catalog' || $url === 'catalog') {
            return route('storefront.catalog.index');
        }

        return str_starts_with($url, '/') ? url($url) : $url;
    };

    $rootCategories = collect();
    $navigationTree = collect();

    if ($store) {
        try {
            $repository = app(CatalogRepository::class);
            $rootCategories = collect($repository->getRootCategories($store, $locale));
            $navigationTree = collect($repository->getNavigationTree($store, $locale));
        } catch (Throwable) {
            $rootCategories = collect();
            $navigationTree = collect();
        }
    }

    $flattenCategories = function ($categories) use (&$flattenCategories) {
        return collect($categories)->flatMap(function ($category) use (&$flattenCategories) {
            return collect([$category])->concat($flattenCategories($category['children'] ?? []));
        })->values();
    };

    $allCategories = $flattenCategories($navigationTree);

    $findCategoryUrl = static function (array $keywords) use ($allCategories, $catalogUrl) {
        $match = $allCategories->first(function (array $category) use ($keywords) {
            $label = mb_strtolower((string) ($category['label'] ?? ''));

            return collect($keywords)->contains(fn ($keyword) => str_contains($label, mb_strtolower($keyword)));
        });

        return !empty($match['slug'])
            ? route('storefront.category.show', ['slug' => $match['slug']])
            : $catalogUrl;
    };

    $formatGroups = collect([
        'agende' => [
            'label' => __('Agende'),
            'cards' => collect([
                [
                    'label' => __('Agenda giornaliera'),
                    'image' => asset('images/themes/b2c/ciak/formats/agenda-giornaliera.jpg'),
                    'url' => $findCategoryUrl(['giornalier', 'daily']),
                ],
                [
                    'label' => __('Agenda settimanale'),
                    'image' => asset('images/themes/b2c/ciak/formats/agenda-settimanale.jpg'),
                    'url' => $findCategoryUrl(['settiman', 'weekly']),
                ],
            ]),
        ],
        'taccuini' => [
            'label' => __('Taccuini'),
            'cards' => collect([
                [
                    'label' => __('Pagine a puntini'),
                    'image' => asset('images/themes/b2c/ciak/formats/taccuino-puntini.jpg'),
                    'url' => $findCategoryUrl(['puntini', 'dotted']),
                ],
                [
                    'label' => __('Pagine a righe'),
                    'image' => asset('images/themes/b2c/ciak/formats/taccuino-righe.jpg'),
                    'url' => $findCategoryUrl(['righe', 'lined']),
                ],
                [
                    'label' => __('Pagine vuote'),
                    'image' => asset('images/themes/b2c/ciak/formats/taccuino-pagine-bianche.jpg'),
                    'url' => $findCategoryUrl(['bianche', 'blank', 'vuote']),
                ],
            ]),
        ],
    ]);

    $heroImage = media_url($heroBlock?->image_path);
    $heroMobileImage = media_url($heroBlock?->mobile_image_path);
    $heroVideo = media_url($heroBlock?->video_path);

    $heroMedia = collect($heroBlock?->activeMedia ?? [])->map(fn ($media) => [
        'type' => $media->media_type,
        'desktop' => media_url($media->desktop_path),
        'mobile' => media_url($media->mobile_path),
        'poster' => media_url($media->poster_path),
        'alt' => $media->alt_text ?: ($heroBlock?->title ?: 'CIAK'),
    ])->filter(fn ($media) => !empty($media['desktop']))->values();

    if ($heroMedia->isEmpty() && ($heroImage || $heroVideo)) {
        $heroMedia = collect([[
            'type' => $heroVideo ? 'video' : 'image',
            'desktop' => $heroVideo ?: $heroImage,
            'mobile' => $heroMobileImage,
            'poster' => $heroImage,
            'alt' => $heroBlock?->title ?: 'CIAK',
        ]]);
    }

    $editorialImage = media_url($editorialBlock?->image_path);
@endphp

@section('title', $page?->meta_title ?: ($store->name ?? 'CIAK'))
@section('meta_description', $page?->meta_description ?: __('Agende, taccuini e accessori CIAK.'))

@section('fullwidth')
<section class="ciak-home-hero" data-ciak-hero>
    <div class="ciak-home-hero-copy">
        <span class="ciak-home-kicker">{{ $heroBlock?->subtitle ?: __('Scegli per utilizzo') }}</span>
        <h1>{{ $heroBlock?->title ?: __('Trova quello giusto per te.') }}</h1>
        <p>{{ $heroBlock?->content ?: __('Agende e taccuini progettati per accompagnare le tue idee, ogni giorno.') }}</p>

        <a href="{{ $resolveBlockUrl($heroBlock, $catalogUrl) }}" class="ciak-home-button">
            <span>{{ $heroBlock?->button_label ?: __('Scopri la collezione') }}</span>
            <i data-lucide="arrow-right" aria-hidden="true"></i>
        </a>
    </div>

    <div class="ciak-home-hero-media">
        @if($heroMedia->isNotEmpty())
            @foreach($heroMedia as $media)
                <div class="ciak-home-hero-slide {{ $loop->first ? 'is-active' : '' }}" data-ciak-hero-slide>
                    @if($media['type'] === 'video')
                        <video muted playsinline loop preload="{{ $loop->first ? 'metadata' : 'none' }}" @if($media['poster']) poster="{{ $media['poster'] }}" @endif @if($loop->first) autoplay @endif>
                            <source src="{{ $media['desktop'] }}">
                        </video>
                    @else
                        <picture>
                            @if($media['mobile'])<source media="(max-width: 767px)" srcset="{{ $media['mobile'] }}">@endif
                            <img src="{{ $media['desktop'] }}" alt="{{ $media['alt'] }}" {{ $loop->first ? 'fetchpriority=high' : 'loading=lazy' }} decoding="async">
                        </picture>
                    @endif
                </div>
            @endforeach
        @else
            <div class="ciak-home-hero-placeholder" aria-hidden="true"><span>CIAK</span></div>
        @endif
    </div>
</section>
@endsection

@section('content')
<div class="ciak-home">
    <section class="ciak-home-formats" data-ciak-formats aria-labelledby="ciak-formats-title">
        <div class="ciak-home-section-head is-center">
            <h2 id="ciak-formats-title">{{ __('Trova quello giusto') }}</h2>
        </div>

        <div class="ciak-format-tabs" role="tablist" aria-label="{{ __('Tipologia prodotto') }}">
            @foreach($formatGroups as $key => $group)
                <button
                    type="button"
                    class="{{ $loop->first ? 'is-active' : '' }}"
                    role="tab"
                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    data-ciak-format-tab="{{ $key }}"
                >
                    {{ $group['label'] }}
                </button>
            @endforeach
        </div>

        <div class="ciak-format-carousel">
            <button type="button" class="ciak-format-arrow is-prev" data-ciak-format-prev aria-label="{{ __('Precedente') }}">
                <i data-lucide="chevron-left" aria-hidden="true"></i>
            </button>

            @foreach($formatGroups as $key => $group)
                <div class="ciak-format-panel" data-ciak-format-panel="{{ $key }}" @if(!$loop->first) hidden @endif>
                    <div class="ciak-format-track" data-ciak-format-track>
                        @foreach($group['cards'] as $card)
                            <a href="{{ $card['url'] }}" class="ciak-format-card">
                                <span class="ciak-format-card-image">
                                    <img src="{{ $card['image'] }}" alt="{{ $card['label'] }}" loading="lazy" decoding="async">
                                </span>
                                <strong>{{ $card['label'] }}</strong>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <button type="button" class="ciak-format-arrow is-next" data-ciak-format-next aria-label="{{ __('Successivo') }}">
                <i data-lucide="chevron-right" aria-hidden="true"></i>
            </button>
        </div>
    </section>

    <section class="ciak-home-editorial">
        <div class="ciak-home-editorial-media">
            @if($editorialImage)
                <img src="{{ $editorialImage }}" alt="{{ $editorialBlock?->title ?: 'CIAK' }}" loading="lazy" decoding="async">
            @else
                <div class="ciak-home-editorial-placeholder" aria-hidden="true"><span>CIAK</span></div>
            @endif
        </div>
        <div class="ciak-home-editorial-copy">
            <span>{{ $editorialBlock?->subtitle ?: __('Qualità italiana') }}</span>
            <h2>{{ $editorialBlock?->title ?: __('Essenziale, funzionale, senza tempo.') }}</h2>
            <p>{{ $editorialBlock?->content ?: __('Materiali selezionati e design minimal danno forma a strumenti pensati per durare.') }}</p>
            <a href="{{ $resolveBlockUrl($editorialBlock, $catalogUrl) }}" class="ciak-home-link">
                {{ $editorialBlock?->button_label ?: __('Scopri di più') }}
                <i data-lucide="arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <section class="ciak-home-service-strip" aria-label="{{ __('Vantaggi') }}">
        <div>
            <i data-lucide="truck" aria-hidden="true"></i>
            <span>{{ __('Spedizione gratuita per ordini sopra i 50€') }}</span>
        </div>
        <div>
            <i data-lucide="rotate-ccw" aria-hidden="true"></i>
            <span>{{ __('Reso facile entro 30 giorni') }}</span>
        </div>
        <div>
            <i data-lucide="lock-keyhole" aria-hidden="true"></i>
            <span>{{ __('Pagamenti sicuri 100% protetti') }}</span>
        </div>
        <div>
            <i data-lucide="headphones" aria-hidden="true"></i>
            <span>{{ __('Assistenza dedicata siamo qui per te') }}</span>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const hero = document.querySelector('[data-ciak-hero]');
    const slides = Array.from(hero?.querySelectorAll('[data-ciak-hero-slide]') || []);

    if (slides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        let current = 0;
        window.setInterval(function () {
            slides[current].classList.remove('is-active');
            current = (current + 1) % slides.length;
            slides[current].classList.add('is-active');
        }, 6500);
    }

    const formats = document.querySelector('[data-ciak-formats]');
    if (!formats) return;

    const tabs = Array.from(formats.querySelectorAll('[data-ciak-format-tab]'));
    const panels = Array.from(formats.querySelectorAll('[data-ciak-format-panel]'));
    const prev = formats.querySelector('[data-ciak-format-prev]');
    const next = formats.querySelector('[data-ciak-format-next]');

    const activeTrack = function () {
        return formats.querySelector('[data-ciak-format-panel]:not([hidden]) [data-ciak-format-track]');
    };

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = tab.dataset.ciakFormatTab;

            tabs.forEach(function (item) {
                const active = item === tab;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            panels.forEach(function (panel) {
                panel.hidden = panel.dataset.ciakFormatPanel !== target;
            });
        });
    });

    prev?.addEventListener('click', function () {
        activeTrack()?.scrollBy({ left: -320, behavior: 'smooth' });
    });

    next?.addEventListener('click', function () {
        activeTrack()?.scrollBy({ left: 320, behavior: 'smooth' });
    });
})();
</script>
@endpush

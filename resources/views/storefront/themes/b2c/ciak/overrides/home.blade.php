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
    $heroImage = media_url($heroBlock?->image_path);
    $heroMobileImage = media_url($heroBlock?->mobile_image_path);
    $heroVideo = media_url($heroBlock?->video_path);
    $storyImage = media_url($storyBlock?->image_path);
    $bannerImage = media_url($bannerBlock?->image_path);
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

    $rootCategories = collect($childrenCategories ?? []);

    if ($rootCategories->isEmpty() && $store) {
        try {
            $rootCategories = app(CatalogRepository::class)->getRootCategories($store, $locale);
        } catch (Throwable) {
            $rootCategories = collect();
        }
    }

    $findCategoryUrl = static function (array $keywords) use ($rootCategories, $store, $locale, $catalogUrl) {
        $match = $rootCategories->first(function (array $category) use ($keywords) {
            $label = mb_strtolower((string) ($category['label'] ?? ''));

            return collect($keywords)->contains(fn ($keyword) => str_contains($label, mb_strtolower($keyword)));
        });

        if (!$match && $store) {
            foreach ($rootCategories as $category) {
                try {
                    $match = app(CatalogRepository::class)
                        ->getChildrenCategories($store, $locale, $category['fam_code'] ?? null)
                        ->first(function (array $child) use ($keywords) {
                            $label = mb_strtolower((string) ($child['label'] ?? ''));

                            return collect($keywords)->contains(fn ($keyword) => str_contains($label, mb_strtolower($keyword)));
                        });
                } catch (Throwable) {
                    $match = null;
                }

                if ($match) {
                    break;
                }
            }
        }

        return !empty($match['slug'])
            ? route('storefront.category.show', ['slug' => $match['slug']])
            : $catalogUrl;
    };

    $useCards = [
        ['label' => __('Agenda giornaliera'), 'text' => __('Un giorno per pagina'), 'icon' => 'fa-regular fa-calendar', 'url' => $findCategoryUrl(['giornalier', 'daily'])],
        ['label' => __('Agenda settimanale'), 'text' => __('La settimana a colpo d’occhio'), 'icon' => 'fa-regular fa-calendar-check', 'url' => $findCategoryUrl(['settiman', 'weekly'])],
        ['label' => __('Taccuino a righe'), 'text' => __('Per note, studio e lavoro'), 'icon' => 'fa-solid fa-align-left', 'url' => $findCategoryUrl(['righe', 'lined'])],
        ['label' => __('Taccuino a puntini'), 'text' => __('Libertà di organizzare le idee'), 'icon' => 'fa-solid fa-braille', 'url' => $findCategoryUrl(['puntini', 'dotted'])],
        ['label' => __('Pagine bianche'), 'text' => __('Per disegnare e immaginare'), 'icon' => 'fa-regular fa-file', 'url' => $findCategoryUrl(['bianche', 'blank'])],
    ];

    $listingCardsByProductSku = collect($listingCardsByProductSku ?? []);
    $featuredProducts = collect($products?->items() ?? [])->shuffle()->take(4)->values();
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
@endphp

@section('title', $page?->meta_title ?: ($store->name ?? 'CIAK'))
@section('meta_description', $page?->meta_description ?: __('Agende, taccuini e accessori CIAK.'))

@section('fullwidth')
    <section class="ciak-home-hero {{ $heroMedia->isNotEmpty() ? 'has-image' : 'without-image' }}" aria-labelledby="ciak-home-title" data-ciak-hero>
        @if($heroMedia->isNotEmpty())
            <div class="ciak-home-hero-slides">
                @foreach($heroMedia as $media)
                    <div class="ciak-home-hero-slide {{ $loop->first ? 'is-active' : '' }}" data-ciak-hero-slide>
                        @if($media['type'] === 'video')
                            <video
                                muted
                                playsinline
                                loop
                                preload="{{ $loop->first ? 'metadata' : 'none' }}"
                                @if($media['poster']) poster="{{ $media['poster'] }}" @endif
                                @if($loop->first) autoplay @endif
                            >
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
            </div>
        @endif
        <div class="ciak-home-hero-content">
            <span class="ciak-home-overline">{{ $heroBlock?->subtitle ?: __('Fatto a Firenze dal 1977') }}</span>
            <h1 id="ciak-home-title">{{ $heroBlock?->title ?: __('Scrivi il tuo tempo.') }}</h1>
            <p>{{ $heroBlock?->content ?: __('Agende e taccuini italiani, essenziali nelle forme e pieni di colore.') }}</p>
            <a
                href="{{ $resolveBlockUrl($heroBlock, $catalogUrl) }}"
                class="ciak-home-primary-action"
                @if($heroBlock?->button_new_tab) target="_blank" rel="noopener noreferrer" @endif
            >
                <span>{{ $heroBlock?->button_label ?: __('Scopri CIAK') }}</span>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
        @if($heroMedia->count() > 1)
            <div class="ciak-home-hero-controls" aria-label="{{ __('Controlli hero') }}">
                <button type="button" data-ciak-hero-prev aria-label="{{ __('Contenuto precedente') }}"><i class="fa-solid fa-arrow-left"></i></button>
                <span><strong data-ciak-hero-current>1</strong> / {{ $heroMedia->count() }}</span>
                <button type="button" data-ciak-hero-next aria-label="{{ __('Contenuto successivo') }}"><i class="fa-solid fa-arrow-right"></i></button>
            </div>
        @endif
    </section>
@endsection

@section('content')
<div class="ciak-home-v2">

    <section class="ciak-home-use" aria-labelledby="ciak-use-title">
        <div class="ciak-home-section-heading">
            <span>{{ __('Scegli per utilizzo') }}</span>
            <h2 id="ciak-use-title">{{ __('Trova quello giusto per te.') }}</h2>
        </div>
        <div class="ciak-home-use-grid">
            @foreach($useCards as $card)
                <a href="{{ $card['url'] }}" class="ciak-home-use-item">
                    <i class="{{ $card['icon'] }}" aria-hidden="true"></i>
                    <strong>{{ $card['label'] }}</strong>
                    <small>{{ $card['text'] }}</small>
                    <span class="ciak-home-use-arrow"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="ciak-home-collections" aria-labelledby="ciak-collections-title">
        <div class="ciak-home-section-heading is-row">
            <div>
                <span>{{ __('Collezioni') }}</span>
                <h2 id="ciak-collections-title">{{ __('Il mondo CIAK.') }}</h2>
            </div>
            <a href="{{ $catalogUrl }}" class="ciak-home-text-link">{{ __('Vedi tutto') }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>

        <div class="ciak-home-collection-grid">
            @forelse($rootCategories->take(4) as $category)
                <a href="{{ route('storefront.category.show', ['slug' => $category['slug']]) }}" class="ciak-home-collection-item">
                    <span class="ciak-home-collection-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <div>
                        <strong>{{ $category['label'] ?? __('Collezione') }}</strong>
                        <small>{{ __('Esplora la collezione') }}</small>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>
            @empty
                <a href="{{ $catalogUrl }}" class="ciak-home-collection-item is-empty">
                    <div>
                        <strong>{{ __('Scopri lo shop CIAK') }}</strong>
                        <small>{{ __('Tutti i prodotti disponibili online') }}</small>
                    </div>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            @endforelse
        </div>
    </section>

    @if($bannerBlock && ($bannerImage || $bannerBlock->title || $bannerBlock->content))
        <section class="ciak-home-campaign {{ $bannerImage ? 'has-image' : '' }}">
            @if($bannerImage)
                <img src="{{ $bannerImage }}" alt="{{ $bannerBlock->title ?: 'CIAK' }}" loading="lazy" decoding="async">
            @endif
            <div>
                @if($bannerBlock->subtitle)<span>{{ $bannerBlock->subtitle }}</span>@endif
                @if($bannerBlock->title)<h2>{{ $bannerBlock->title }}</h2>@endif
                @if($bannerBlock->content)<p>{{ $bannerBlock->content }}</p>@endif
                @if($bannerBlock->button_label)
                    <a href="{{ $resolveBlockUrl($bannerBlock, $catalogUrl) }}">{{ $bannerBlock->button_label }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                @endif
            </div>
        </section>
    @endif

    <section class="ciak-home-products" aria-labelledby="ciak-products-title">
        <div class="ciak-home-section-heading is-row">
            <div>
                <span>{{ __('In evidenza') }}</span>
                <h2 id="ciak-products-title">{{ __('Scelti per te.') }}</h2>
            </div>
            <a href="{{ $catalogUrl }}" class="ciak-home-text-link">{{ __('Vai allo shop') }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>

        @if($featuredProducts->isEmpty())
            <div class="ciak-empty-state">{{ __('Nessun prodotto disponibile al momento.') }}</div>
        @else
            <div class="row g-3 g-xl-4">
                @foreach($featuredProducts as $product)
                    <div class="col-12 col-sm-6 col-xl-3">
                        @include('storefront.base.partials.product-card', [
                            'product' => $product,
                            'listingCard' => collect($listingCardsByProductSku->get((string) $product->sku, [])),
                        ])
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if($storyImage || $storyBlock?->title || $storyBlock?->content)
        <section class="ciak-home-story">
            <div class="ciak-home-story-copy">
                <span>{{ $storyBlock?->subtitle ?: __('Dettagli CIAK') }}</span>
                <h2>{{ $storyBlock?->title ?: __('Pensati per essere usati, ogni giorno.') }}</h2>
                @if($storyBlock?->content)<p>{{ $storyBlock->content }}</p>@endif
            </div>
            @if($storyImage)
                <div class="ciak-home-story-media">
                    <img src="{{ $storyImage }}" alt="{{ $storyBlock?->title ?: 'CIAK' }}" loading="lazy" decoding="async">
                </div>
            @endif
        </section>
    @endif
</div>

@push('scripts')
<script>
    (function () {
        const hero = document.querySelector('[data-ciak-hero]');
        if (!hero) return;

        const slides = Array.from(hero.querySelectorAll('[data-ciak-hero-slide]'));
        if (slides.length < 2) return;

        const currentLabel = hero.querySelector('[data-ciak-hero-current]');
        let current = 0;
        let timer = null;

        const show = function (index) {
            current = (index + slides.length) % slides.length;
            slides.forEach(function (slide, slideIndex) {
                const active = slideIndex === current;
                slide.classList.toggle('is-active', active);
                const video = slide.querySelector('video');
                if (!video) return;
                if (active && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) video.play().catch(function () {});
                if (!active) video.pause();
            });
            if (currentLabel) currentLabel.textContent = String(current + 1);
        };

        const restart = function () {
            window.clearInterval(timer);
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                timer = window.setInterval(function () { show(current + 1); }, 6500);
            }
        };

        hero.querySelector('[data-ciak-hero-prev]')?.addEventListener('click', function () { show(current - 1); restart(); });
        hero.querySelector('[data-ciak-hero-next]')?.addEventListener('click', function () { show(current + 1); restart(); });
        show(0);
        restart();
    })();
</script>
@endpush
@endsection

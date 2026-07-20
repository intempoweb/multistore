@extends($storefrontLayout)

@section('title', $storefrontPage?->meta_title ?: ($storefrontPage?->title ?: $store->name))
@section('meta_description', $storefrontPage?->meta_description ?: $storefrontPage?->description)

@section('content')
<div class="intempo-b2c-home">
    <section class="intempo-b2c-hero container-fluid p-0" data-intempo-home-hero>
        <div class="intempo-b2c-hero-copy">
            <div class="intempo-b2c-hero-copy-inner">
                <p class="intempo-b2c-eyebrow">{{ $hero?->subtitle ?: __('themes_b2c.intempo.areas_diaries_title') }}</p>
                <h1>{{ $hero?->title ?: __('themes_b2c.intempo.areas_diaries_title') }}</h1>
                <p>{{ $hero?->content ?: __('themes_b2c.intempo.story_intro') }}</p>
                <div class="intempo-b2c-hero-actions">
                    <a class="intempo-b2c-primary-link" href="{{ filled($hero?->button_label) ? $heroButtonUrl : $catalogueUrl }}" @if($hero?->button_new_tab) target="_blank" rel="noopener" @endif>
                        {{ $hero?->button_label ?: __('themes_b2c.intempo.discover_collection') }}
                        <i data-lucide="arrow-right" aria-hidden="true"></i>
                    </a>
                    <a class="intempo-b2c-secondary-link" href="{{ $locatorUrl }}">{{ __('themes_b2c.intempo.find_store') }}</a>
                </div>
            </div>
        </div>
        <div class="intempo-b2c-hero-media {{ $heroMedia->isEmpty() ? 'is-empty' : '' }}">
            @foreach($heroMedia as $index => $media)
                <div class="intempo-b2c-hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-intempo-hero-slide>
                    @if($media['type'] === 'video')
                        <video muted loop playsinline preload="metadata" poster="{{ $media['poster'] }}"><source src="{{ $media['desktop'] }}"></video>
                    @else
                        <picture>
                            @if($media['mobile'])<source media="(max-width: 767px)" srcset="{{ $media['mobile'] }}">@endif
                            <img src="{{ $media['desktop'] }}" alt="{{ $media['alt'] ?: ($hero?->title ?: $store->name) }}" fetchpriority="high">
                        </picture>
                    @endif
                </div>
            @endforeach

            @if($heroMedia->isEmpty())
                <div class="intempo-b2c-hero-placeholder">
                    <span>INTEMPO</span>
                    <small>{{ __('themes_b2c.intempo.hero_summary') }}</small>
                </div>
            @endif

            @if($heroMedia->count() > 1)
                <div class="intempo-b2c-hero-controls">
                    <button type="button" data-intempo-hero-prev aria-label="{{ __('themes_b2c.intempo.previous') }}"><i data-lucide="arrow-left"></i></button>
                    <span><b data-intempo-hero-current>1</b> / {{ $heroMedia->count() }}</span>
                    <button type="button" data-intempo-hero-next aria-label="{{ __('themes_b2c.intempo.next') }}"><i data-lucide="arrow-right"></i></button>
                </div>
            @endif
        </div>
    </section>

    <section class="intempo-b2c-story intempo-b2c-shell" aria-labelledby="intempo-b2c-about-title">
        <div class="intempo-b2c-story-copy">
            <p class="intempo-b2c-eyebrow">{{ $aboutSection['block']->subtitle ?? __('themes_b2c.intempo.about_us') }}</p>
            <h2 id="intempo-b2c-about-title">{{ $storyTitle }}</h2>
            <p>{{ $storyContent }}</p>
            <a class="intempo-b2c-text-link" href="{{ $aboutSection['button_url'] ?? $catalogueUrl }}">
                {{ $aboutSection['block']->button_label ?? __('themes_b2c.intempo.explore_intempo_world') }}
                <i data-lucide="arrow-right"></i>
            </a>
        </div>

        <div class="intempo-b2c-category-grid is-featured-areas">
            @foreach($intempoAreas as $area)
                <a class="intempo-b2c-category-card is-featured-area" href="{{ $area['url'] }}">
                    <span><img src="{{ $area['icon'] }}" alt="" loading="lazy" decoding="async"></span>
                    <small>{{ $area['label'] }}</small>
                    <strong>{{ $area['title'] }}</strong>
                    <em>{{ $area['content'] }}</em>
                </a>
            @endforeach
        </div>
    </section>

    @if($featuredRows->isNotEmpty())
        <section class="intempo-b2c-products-section intempo-b2c-shell" aria-labelledby="intempo-b2c-featured-title">
            <header class="intempo-b2c-section-heading">
                <div>
                    <p class="intempo-b2c-eyebrow">{{ __('themes_b2c.intempo.featured') }}</p>
                    <h2 id="intempo-b2c-featured-title">{{ __('themes_b2c.intempo.picked_for_you') }}</h2>
                </div>
                <a href="{{ $catalogueUrl }}">{{ __('themes_b2c.intempo.view_all') }}<i data-lucide="arrow-right"></i></a>
            </header>
            <div class="intempo-b2c-products-grid">
                @foreach($featuredRows as $row)
                    @include('storefront.base.partials.product-card', ['product' => $row['product'], 'listingCard' => $row['listingCard']])
                @endforeach
            </div>
        </section>
    @endif

</div>
@endsection

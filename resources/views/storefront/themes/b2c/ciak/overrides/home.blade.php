@extends($storefrontLayout)

@section('title', $storefrontPage?->meta_title ?: ($storefrontPage?->title ?: $store->name))
@section('meta_description', $storefrontPage?->meta_description ?: $storefrontPage?->description)

@section('content')
<div class="ciak-home">
    <section class="ciak-hero container-fluid p-0" data-ciak-hero>
        <div class="ciak-hero-copy">
            <div class="ciak-hero-copy-inner">
                @if(filled($hero?->subtitle))<p class="ciak-eyebrow">{{ $hero->subtitle }}</p>@endif
                <h1>{{ $hero?->title ?: $storefrontPage?->title ?: $store->name }}</h1>
                @if(filled($hero?->content))<p class="ciak-lead">{{ $hero->content }}</p>@endif
                @if(filled($hero?->button_label))
                    <a class="ciak-primary-link" href="{{ $heroButtonUrl }}" @if($hero?->button_new_tab) target="_blank" rel="noopener" @endif>{{ $hero->button_label }}<i data-lucide="arrow-right"></i></a>
                @endif
            </div>
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
                <div class="ciak-hero-controls"><button type="button" data-ciak-hero-prev aria-label="{{ __('themes_b2c.ciak.previous') }}"><i data-lucide="arrow-left"></i></button><span><b data-ciak-hero-current>1</b> / {{ $heroMedia->count() }}</span><button type="button" data-ciak-hero-next aria-label="{{ __('themes_b2c.ciak.next') }}"><i data-lucide="arrow-right"></i></button></div>
            @endif
        </div>
    </section>

    @if($aboutSection || $visionSection)
        <section
            class="ciak-about-vision-section"
            data-ciak-about-vision
            aria-labelledby="ciak-about-vision-title"
        >
            <div class="ciak-shell">
                <header class="ciak-about-vision-heading">
                    @if(filled($aboutVisionEyebrow))
                        <p class="ciak-eyebrow">{{ $aboutVisionEyebrow }}</p>
                    @endif

                    @if(filled($aboutVisionHeading))
                        <h2 id="ciak-about-vision-title">{{ $aboutVisionHeading }}</h2>
                    @endif

                    @if(filled($aboutVisionIntro))
                        <p>{{ $aboutVisionIntro }}</p>
                    @endif
                </header>

                @if($aboutVisionPanels->count() > 1)
                    <div
                        class="ciak-about-vision-tabs"
                        role="tablist"
                        aria-label="{{ __('themes_b2c.ciak.about') }} & {{ __('themes_b2c.ciak.vision') }}"
                    >
                        @foreach($aboutVisionPanels as $panel)
                            <button
                                type="button"
                                class="{{ $loop->first ? 'is-active' : '' }}"
                                id="ciak-about-vision-tab-{{ $panel['key'] }}"
                                role="tab"
                                aria-controls="ciak-about-vision-panel-{{ $panel['key'] }}"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                tabindex="{{ $loop->first ? '0' : '-1' }}"
                                data-ciak-about-vision-tab
                                data-ciak-about-vision-target="{{ $panel['key'] }}"
                            >
                                <span>{{ $panel['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="ciak-about-vision-panels">
                    @foreach($aboutVisionPanels as $panel)
                        <article
                            class="ciak-about-vision-panel ciak-about-vision-panel-{{ $panel['key'] }} {{ $loop->first ? 'is-active' : '' }}"
                            id="ciak-about-vision-panel-{{ $panel['key'] }}"
                            role="tabpanel"
                            aria-labelledby="ciak-about-vision-tab-{{ $panel['key'] }}"
                            data-ciak-about-vision-panel
                            data-ciak-about-vision-panel-key="{{ $panel['key'] }}"
                            @if(!$loop->first) hidden @endif
                        >
                            @if($panel['key'] === 'about')
                                <div class="ciak-about-vision-media">
                                    <picture>
                                        @if($aboutMobileImage)
                                            <source media="(max-width:767px)" srcset="{{ $aboutMobileImage }}">
                                        @endif

                                        <img
                                            src="{{ $aboutImage }}"
                                            alt="{{ $aboutImageAlt ?: ($aboutSection['block']->title ?: $store->name) }}"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    </picture>
                                </div>

                                <div class="ciak-about-vision-copy">
                                    @if(filled($aboutSection['block']->subtitle))
                                        <p class="ciak-eyebrow">{{ $aboutSection['block']->subtitle }}</p>
                                    @endif

                                    <h3>{{ $aboutSection['block']->title ?: $panel['fallback_title'] }}</h3>

                                    @if(filled($aboutBody))
                                        <div class="ciak-about-vision-body">
                                            {!! nl2br(e($aboutBody)) !!}
                                        </div>
                                    @endif

                                    @if($aboutHighlights->isNotEmpty())
                                        <div class="ciak-about-vision-highlights">
                                            @foreach($aboutHighlights as $item)
                                                <div class="ciak-about-vision-highlight">
                                                    <i data-lucide="{{ $item['icon'] ?? 'circle' }}"></i>
                                                    <div>
                                                        <h4>{{ $item['title'] ?? '' }}</h4>
                                                        <p>{{ $item['text'] ?? '' }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <a class="ciak-about-vision-cta" href="{{ $aboutCtaUrl }}">
                                        {{ $aboutSection['block']->button_label ?: __('themes_b2c.ciak.about_vision.about.cta') }}
                                        <i data-lucide="arrow-right"></i>
                                    </a>
                                </div>
                            @else
                                <div class="ciak-about-vision-copy">
                                    @if(filled($visionSection['block']->subtitle))
                                        <p class="ciak-eyebrow">{{ $visionSection['block']->subtitle }}</p>
                                    @endif

                                    <h3>{{ $visionSection['block']->title ?: $panel['fallback_title'] }}</h3>

                                    @if(filled($visionBody))
                                        <div class="ciak-about-vision-body">
                                            {!! nl2br(e($visionBody)) !!}
                                        </div>
                                    @endif

                                    @if($visionHighlights->isNotEmpty())
                                        <div class="ciak-about-vision-highlights is-compact">
                                            @foreach($visionHighlights as $item)
                                                <div class="ciak-about-vision-highlight">
                                                    <i data-lucide="{{ $item['icon'] ?? 'circle' }}"></i>
                                                    <div>
                                                        <h4>{{ $item['title'] ?? '' }}</h4>
                                                        <p>{{ $item['text'] ?? '' }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <a class="ciak-about-vision-cta" href="{{ $visionCtaUrl }}">
                                        {{ $visionSection['block']->button_label ?: __('themes_b2c.ciak.about_vision.vision.cta') }}
                                        <i data-lucide="arrow-right"></i>
                                    </a>
                                </div>

                                <div class="ciak-about-vision-media">
                                    <picture>
                                        @if($visionMobileImage)
                                            <source media="(max-width:767px)" srcset="{{ $visionMobileImage }}">
                                        @endif

                                        <img
                                            src="{{ $visionImage }}"
                                            alt="{{ $visionImageAlt ?: ($visionSection['block']->title ?: $store->name) }}"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    </picture>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if($values->isNotEmpty())
                    <div class="ciak-about-vision-values" aria-labelledby="ciak-about-vision-values-title">
                        @if(filled($valuesTitle))
                            <h3 id="ciak-about-vision-values-title">{{ $valuesTitle }}</h3>
                        @endif

                        <div class="ciak-about-vision-values-grid">
                            @foreach($values as $item)
                                <article class="ciak-about-vision-value">
                                    <i data-lucide="{{ $item['icon'] ?? 'sparkles' }}"></i>
                                    <h4>{{ $item['title'] ?? '' }}</h4>
                                    <p>{{ $item['text'] ?? '' }}</p>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if($featuredRows->isNotEmpty())
        <section class="ciak-products-section ciak-shell" aria-labelledby="ciak-featured-title">
            <header class="ciak-section-heading">
                <div>
                    @if(filled($featuredIntroSection['block']->subtitle ?? null))
                        <p class="ciak-eyebrow">{{ $featuredIntroSection['block']->subtitle }}</p>
                    @endif
                    <h2 id="ciak-featured-title">{{ $featuredIntroSection['block']->title ?? __('themes_b2c.ciak.picked_for_you') }}</h2>
                </div>
                <a href="{{ $featuredIntroSection['button_url'] ?? route('storefront.catalog.index') }}">
                    {{ $featuredIntroSection['block']->button_label ?? __('themes_b2c.ciak.view_all') }}
                    <i data-lucide="arrow-right"></i>
                </a>
            </header>
            <div class="ciak-products-grid">
                @foreach($featuredRows as $row)
                    @include('storefront.base.partials.product-card', ['product' => $row['product'], 'listingCard' => $row['listingCard']])
                @endforeach
            </div>
        </section>
    @endif

    @if($formatGroups->isNotEmpty())
        <section class="ciak-format-section" data-ciak-formats aria-labelledby="ciak-formats-title">
            <div class="ciak-shell">
                <header class="ciak-format-heading">
                    <div>
                        @if(filled($formatsIntroSection['block']->subtitle ?? null))
                            <p class="ciak-eyebrow">{{ $formatsIntroSection['block']->subtitle }}</p>
                        @endif
                        <h2 id="ciak-formats-title">{{ $formatsIntroSection['block']->title ?? __('themes_b2c.ciak.choose_how_to_write') }}</h2>
                    </div>
                    @if(filled($formatsIntroSection['block']->content ?? null))
                        <p>{{ $formatsIntroSection['block']->content }}</p>
                    @endif
                </header>
            </div>

            <div class="ciak-format-stories-wrapper" data-ciak-format-stories-wrapper>
                <div class="ciak-shell">
                    <div class="ciak-format-stories" role="tablist" aria-label="{{ __('themes_b2c.ciak.available_layouts') }}">
                @foreach($formatItems as $item)
                    <button
                        type="button"
                        class="ciak-format-story {{ $loop->first ? 'is-active' : '' }} {{ $item['available'] ? '' : 'is-unavailable' }}"
                        role="tab"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        aria-controls="ciak-format-panel-{{ $loop->index }}"
                        id="ciak-format-tab-{{ $loop->index }}"
                        data-ciak-format-tab
                        data-ciak-format-index="{{ $loop->index }}"
                    >
                        <span class="ciak-format-story-ring">
                            <img src="{{ $item['image'] }}" alt="" loading="lazy" decoding="async">
                        </span>
                        <span class="ciak-format-story-label">{{ $item['label'] }}</span>
                    </button>
                @endforeach
                    </div><!-- closes ciak-format-stories -->
                </div><!-- closes ciak-shell inside wrapper -->
            </div><!-- closes ciak-format-stories-wrapper -->

            <div class="ciak-shell">
                <div class="ciak-format-showcase" data-ciak-format-showcase>
                @foreach($formatItems as $item)
                    <article
                        class="ciak-format-panel ciak-format-panel-{{ $item['key'] ?? 'format' }} {{ $loop->first ? 'is-active' : '' }}"
                        id="ciak-format-panel-{{ $loop->index }}"
                        role="tabpanel"
                        aria-labelledby="ciak-format-tab-{{ $loop->index }}"
                        data-ciak-format-panel
                        data-ciak-format-index="{{ $loop->index }}"
                        @if(!$loop->first) hidden @endif
                    >
                        <div class="ciak-format-panel-copy">
                            <p class="ciak-eyebrow">{{ $item['group'] }}</p>
                            <h3>{{ $item['label'] }}</h3>
                            <p>{{ $item['description'] }}</p>

                            <div class="ciak-format-specs" aria-label="{{ __('themes_b2c.ciak.layout_specs') }}">
                                @foreach($item['specs'] as $spec)
                                    <span>{{ $spec }}</span>
                                @endforeach
                            </div>

                            @if($item['available'] && $item['url'])
                                <a href="{{ $item['url'] }}">{{ $item['button_label'] ?? __('themes_b2c.ciak.discover_selection') }}<i data-lucide="arrow-right"></i></a>
                            @else
                                <span class="ciak-format-unavailable">{{ __('themes_b2c.ciak.coming_soon') }}</span>
                            @endif
                        </div>

                        <div class="ciak-format-stage" data-ciak-format-stage>
                            <div class="ciak-format-visual" aria-hidden="true">
                                <img
                                    class="ciak-format-visual-outline"
                                    src="{{ $item['image'] }}"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                >

                                <img
                                    class="ciak-format-visual-color"
                                    src="{{ $item['color_image'] ?? $item['detail_image'] ?? $item['image'] }}"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                >
                            </div>

                            <div class="ciak-format-callouts" aria-hidden="true">
                                <span class="ciak-format-callout is-one">
                                    <span class="ciak-format-callout-dot"></span>
                                    <span class="ciak-format-callout-line"></span>
                                    <span class="ciak-format-callout-label">{{ $item['specs'][0] ?? __('themes_b2c.ciak.layout') }}</span>
                                </span>

                                <span class="ciak-format-callout is-two">
                                    <span class="ciak-format-callout-label">{{ $item['specs'][1] ?? __('themes_b2c.ciak.detail') }}</span>
                                    <span class="ciak-format-callout-line"></span>
                                    <span class="ciak-format-callout-dot"></span>
                                </span>

                                <span class="ciak-format-callout is-three">
                                    <span class="ciak-format-callout-dot"></span>
                                    <span class="ciak-format-callout-line"></span>
                                    <span class="ciak-format-callout-label">{{ $item['specs'][2] ?? __('themes_b2c.ciak.paper') }}</span>
                                </span>
                            </div>
                        </div>
                    </article>
                @endforeach
                </div><!-- closes ciak-format-showcase -->
            </div><!-- closes ciak-shell -->
        </section>
    @endif


    @if($editorialSection)
        <section class="ciak-editorial" aria-label="{{ $editorialSection['block']->title ?: __('themes_b2c.ciak.story') }}">
            <div class="ciak-editorial-media">
                <picture>
                    @if($editorialSection['mobile_image'])<source media="(max-width:767px)" srcset="{{ $editorialSection['mobile_image'] }}">@endif
                    <img src="{{ $editorialSection['image'] }}" alt="{{ $editorialSection['image_alt'] ?: ($editorialSection['block']->title ?: $store->name) }}" loading="lazy" decoding="async">
                </picture>
            </div>
            <div class="ciak-editorial-copy">
                @if($editorialSection['block']->subtitle)<p class="ciak-eyebrow">{{ $editorialSection['block']->subtitle }}</p>@endif
                <h2>{{ $editorialSection['block']->title }}</h2>
                @if($editorialSection['block']->content)<p>{{ $editorialSection['block']->content }}</p>@endif
                @if($editorialSection['block']->button_label)<a href="{{ $editorialSection['button_url'] }}" @if($editorialSection['block']->button_new_tab) target="_blank" rel="noopener" @endif>{{ $editorialSection['block']->button_label }}<i data-lucide="arrow-right"></i></a>@endif
            </div>
        </section>
    @endif

    @if($instagramSection)
        @php
            $instagramEyebrow = __('themes_b2c.ciak.instagram_eyebrow');
            $instagramTitle = __('themes_b2c.ciak.instagram_title');
            $instagramIntro = __('themes_b2c.ciak.instagram_intro');
            $instagramButton = __('themes_b2c.ciak.instagram_button');
        @endphp
        <section
            class="ciak-instagram-section"
            aria-label="{{ $instagramTitle }}"
            data-ciak-instagram
            data-instagram-url="{{ Route::has('storefront.instagram.gallery') ? route('storefront.instagram.gallery') : '' }}"
        >
            <div class="ciak-shell">
                <header class="ciak-instagram-heading">
                    <div>
                        <p class="ciak-eyebrow">{{ $instagramEyebrow }}</p>
                        <h2>{{ $instagramTitle }}</h2>
                        <p>{{ $instagramIntro }}</p>
                    </div>
                    <a href="{{ $instagramSection['button_url'] }}" @if($instagramSection['block']->button_new_tab) target="_blank" rel="noopener" @endif>{{ $instagramButton }}<i data-lucide="arrow-right"></i></a>
                </header>

                @if($instagramSection['items']->isNotEmpty())
                    <div class="ciak-instagram-grid" aria-label="{{ __('themes_b2c.ciak.latest_instagram') }}" data-ciak-instagram-grid>
                        @foreach($instagramSection['items']->take(12) as $item)
                            <figure class="ciak-instagram-card {{ $item['type'] === 'video' ? 'is-video' : '' }}" data-ciak-instagram-card>
                                @if(!empty($item['permalink']))<a href="{{ $item['permalink'] }}" target="_blank" rel="noopener" aria-label="{{ __('themes_b2c.ciak.open_instagram_post') }}">@endif
                                @if($item['type'] === 'video')
                                    <video autoplay muted loop playsinline preload="metadata" poster="{{ $item['poster'] }}"><source src="{{ $item['desktop'] }}"></video>
                                @else
                                    <picture>
                                        @if($item['mobile'])<source media="(max-width:767px)" srcset="{{ $item['mobile'] }}">@endif
                                        <img src="{{ $item['desktop'] }}" alt="{{ $item['alt'] }}" loading="lazy" decoding="async">
                                    </picture>
                                @endif

                                <figcaption>
                                    <span class="ciak-instagram-badge"><i data-lucide="instagram"></i> Instagram</span>
                                    @if(($item['likes'] ?? null) !== null || ($item['comments'] ?? null) !== null)
                                        <span class="ciak-instagram-metrics">
                                            @if(($item['likes'] ?? null) !== null)<span><i data-lucide="heart"></i>{{ number_format((int) $item['likes'], 0, ',', '.') }}</span>@endif
                                            @if(($item['comments'] ?? null) !== null)<span><i data-lucide="message-circle"></i>{{ number_format((int) $item['comments'], 0, ',', '.') }}</span>@endif
                                        </span>
                                    @endif
                                    <span class="ciak-instagram-caption">{{ $item['alt'] }}</span>
                                </figcaption>
                                @if(!empty($item['permalink']))</a>@endif
                            </figure>
                        @endforeach
                    </div>

                    @if($instagramSection['items']->count() > 12)
                        <div class="ciak-instagram-more">
                            <button type="button" data-ciak-instagram-more data-offset="12">
                                {{ __('themes_b2c.ciak.show_full_gallery') }}
                                <i data-lucide="plus"></i>
                            </button>
                        </div>
                    @endif
                @endif
            </div>
        </section>
    @endif
</div>
@endsection

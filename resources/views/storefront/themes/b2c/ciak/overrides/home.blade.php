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
                <div class="ciak-hero-controls"><button type="button" data-ciak-hero-prev aria-label="{{ __('Precedente') }}"><i data-lucide="arrow-left"></i></button><span><b data-ciak-hero-current>1</b> / {{ $heroMedia->count() }}</span><button type="button" data-ciak-hero-next aria-label="{{ __('Successivo') }}"><i data-lucide="arrow-right"></i></button></div>
            @endif
        </div>
    </section>

    @if($aboutSection)
        <section class="ciak-about-section ciak-shell" aria-label="{{ $aboutSection['block']->title ?: __('About CIAK') }}">
            <div class="ciak-about-content">
                <div class="ciak-about-copy">
                    @if($aboutSection['block']->subtitle)<p class="ciak-eyebrow">{{ $aboutSection['block']->subtitle }}</p>@endif
                    @if($aboutSection['block']->title)<h2>{{ $aboutSection['block']->title }}</h2>@endif
                    @if($aboutSection['block']->content)<p>{{ $aboutSection['block']->content }}</p>@endif
                    @if($aboutSection['block']->button_label)<a href="{{ $aboutSection['button_url'] }}" @if($aboutSection['block']->button_new_tab) target="_blank" rel="noopener" @endif>{{ $aboutSection['block']->button_label }}<i data-lucide="arrow-right"></i></a>@endif
                </div>

                @if($aboutSection['image'])
                    <div class="ciak-about-media">
                        <picture>
                            @if($aboutSection['mobile_image'])<source media="(max-width:767px)" srcset="{{ $aboutSection['mobile_image'] }}">@endif
                            <img src="{{ $aboutSection['image'] }}" alt="{{ $aboutSection['block']->title ?: $store->name }}" loading="lazy" decoding="async">
                        </picture>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if($formatGroups->isNotEmpty())
        <section class="ciak-format-section ciak-shell" data-ciak-formats aria-labelledby="ciak-formats-title">
            <header class="ciak-format-heading">
                <div>
                    <p class="ciak-eyebrow">{{ __('Trova quello giusto') }}</p>
                    <h2 id="ciak-formats-title">{{ __('Scegli come scrivere') }}</h2>
                </div>
                <div class="ciak-format-controls" aria-label="{{ __('Controlli formati') }}">
                    <button type="button" data-ciak-format-prev aria-label="{{ __('Formati precedenti') }}"><i data-lucide="arrow-left"></i></button>
                    <button type="button" data-ciak-format-next aria-label="{{ __('Formati successivi') }}"><i data-lucide="arrow-right"></i></button>
                </div>
            </header>
            <div class="ciak-format-track" data-ciak-format-track>
                @foreach($formatGroups as $group)
                    @foreach($group['items'] as $item)
                        @if($item['available'] && $item['url'])
                            <a class="ciak-format-card" href="{{ $item['url'] }}" data-ciak-format-card>
                        @else
                            <div class="ciak-format-card is-unavailable" aria-disabled="true" data-ciak-format-card>
                        @endif
                                <span class="ciak-format-card-media"><img src="{{ $item['image'] }}" alt="" loading="lazy" decoding="async"></span>
                                <span class="ciak-format-card-copy">
                                    <small>{{ $item['group'] }}</small>
                                    <strong>{{ $item['label'] }}</strong>
                                    <em>{{ $item['available'] ? __('Disponibile') : __('Non disponibile') }}</em>
                                </span>
                        @if($item['available'] && $item['url'])</a>@else</div>@endif
                    @endforeach
                @endforeach
            </div>
        </section>
    @endif

    @if($featuredRows->isNotEmpty())
        <section class="ciak-products-section ciak-shell" aria-labelledby="ciak-featured-title">
            <header class="ciak-section-heading"><div><p class="ciak-eyebrow">{{ __('In evidenza') }}</p><h2 id="ciak-featured-title">{{ __('Scelti per te') }}</h2></div><a href="{{ route('storefront.catalog.index') }}">{{ __('Vedi tutto') }}<i data-lucide="arrow-right"></i></a></header>
            <div class="ciak-products-grid">
                @foreach($featuredRows as $row)
                    @include('storefront.base.partials.product-card', ['product' => $row['product'], 'listingCard' => $row['listingCard']])
                @endforeach
            </div>
        </section>
    @endif

    @if($editorialSection)
        <section class="ciak-editorial" aria-label="{{ $editorialSection['block']->title ?: __('Storia CIAK') }}">
            <div class="ciak-editorial-media">
                <picture>
                    @if($editorialSection['mobile_image'])<source media="(max-width:767px)" srcset="{{ $editorialSection['mobile_image'] }}">@endif
                    <img src="{{ $editorialSection['image'] }}" alt="{{ $editorialSection['block']->title ?: $store->name }}" loading="lazy" decoding="async">
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

    @if($visionSection)
        <section class="ciak-vision-section" aria-label="{{ $visionSection['block']->title ?: __('Vision CIAK') }}">
            <div class="ciak-shell ciak-vision-inner">
                <div class="ciak-vision-copy">
                    @if($visionSection['block']->subtitle)<p class="ciak-eyebrow">{{ $visionSection['block']->subtitle }}</p>@endif
                    @if($visionSection['block']->title)<h2>{{ $visionSection['block']->title }}</h2>@endif
                    @if($visionSection['block']->content)<p>{{ $visionSection['block']->content }}</p>@endif
                    @if($visionSection['block']->button_label)<a href="{{ $visionSection['button_url'] }}" @if($visionSection['block']->button_new_tab) target="_blank" rel="noopener" @endif>{{ $visionSection['block']->button_label }}<i data-lucide="arrow-right"></i></a>@endif
                </div>

                @if($visionSection['image'])
                    <div class="ciak-vision-media">
                        <picture>
                            @if($visionSection['mobile_image'])<source media="(max-width:767px)" srcset="{{ $visionSection['mobile_image'] }}">@endif
                            <img src="{{ $visionSection['image'] }}" alt="{{ $visionSection['block']->title ?: $store->name }}" loading="lazy" decoding="async">
                        </picture>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if($instagramSection)
        <section class="ciak-instagram-section" aria-label="{{ $instagramSection['block']->title ?: __('Instagram CIAK') }}">
            <div class="ciak-shell">
                <header class="ciak-instagram-heading">
                    <div>
                        @if($instagramSection['block']->subtitle)<p class="ciak-eyebrow">{{ $instagramSection['block']->subtitle }}</p>@endif
                        @if($instagramSection['block']->title)<h2>{{ $instagramSection['block']->title }}</h2>@endif
                        @if($instagramSection['block']->content)<p>{{ $instagramSection['block']->content }}</p>@endif
                    </div>
                    @if($instagramSection['block']->button_label)<a href="{{ $instagramSection['button_url'] }}" @if($instagramSection['block']->button_new_tab) target="_blank" rel="noopener" @endif>{{ $instagramSection['block']->button_label }}<i data-lucide="arrow-right"></i></a>@endif
                </header>

                @if($instagramSection['items']->isNotEmpty())
                    <div class="ciak-instagram-grid" aria-label="{{ __('Ultimi contenuti Instagram') }}">
                        @foreach($instagramSection['items']->take(12) as $item)
                            <figure class="ciak-instagram-card {{ $item['type'] === 'video' ? 'is-video' : '' }}">
                                @if(!empty($item['permalink']))<a href="{{ $item['permalink'] }}" target="_blank" rel="noopener" aria-label="{{ __('Apri post Instagram') }}">@endif
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
                @endif
            </div>
        </section>
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

    document.querySelectorAll('[data-ciak-formats]').forEach(function (section) {
        const track = section.querySelector('[data-ciak-format-track]');
        const cards = track ? Array.from(track.querySelectorAll('[data-ciak-format-card]')) : [];
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let autoplay = null;

        const step = function () {
            if (!track || !cards.length) return 0;
            const gap = parseFloat(window.getComputedStyle(track).columnGap || window.getComputedStyle(track).gap || '0');
            return cards[0].getBoundingClientRect().width + gap;
        };
        const move = function (direction) {
            if (!track) return;
            const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
            const atStart = track.scrollLeft <= 4;
            if (direction > 0 && atEnd) track.scrollTo({ left: 0, behavior: 'smooth' });
            else if (direction < 0 && atStart) track.scrollTo({ left: track.scrollWidth, behavior: 'smooth' });
            else track.scrollBy({ left: step() * direction, behavior: 'smooth' });
        };
        const stop = function () { if (autoplay) window.clearInterval(autoplay); autoplay = null; };
        const start = function () { if (!reducedMotion && track && track.scrollWidth > track.clientWidth) { stop(); autoplay = window.setInterval(function () { move(1); }, 3200); } };

        section.querySelector('[data-ciak-format-prev]')?.addEventListener('click', function () { move(-1); start(); });
        section.querySelector('[data-ciak-format-next]')?.addEventListener('click', function () { move(1); start(); });
        section.addEventListener('mouseenter', stop);
        section.addEventListener('mouseleave', start);
        section.addEventListener('focusin', stop);
        section.addEventListener('focusout', start);
        start();
    });
});
</script>
@endpush

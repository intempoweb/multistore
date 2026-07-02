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

    @if($aboutSection)
        <section class="ciak-about-section ciak-shell" aria-label="{{ $aboutSection['block']->title ?: __('themes_b2c.ciak.about') }}">
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
        @php
            $formatItems = $formatGroups->flatMap(fn ($group) => collect($group['items']))->values();
        @endphp
        <section class="ciak-format-section ciak-shell" data-ciak-formats aria-labelledby="ciak-formats-title">
            <header class="ciak-format-heading">
                <div>
                    <p class="ciak-eyebrow">{{ __('themes_b2c.ciak.find_the_right_one') }}</p>
                    <h2 id="ciak-formats-title">{{ __('themes_b2c.ciak.choose_how_to_write') }}</h2>
                </div>
                <p>{{ __('themes_b2c.ciak.format_intro') }}</p>
            </header>

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
            </div>

            <div class="ciak-format-showcase" data-ciak-format-showcase>
                @foreach($formatItems as $item)
                    <article
                        class="ciak-format-panel {{ $loop->first ? 'is-active' : '' }}"
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
                                <a href="{{ $item['url'] }}">{{ __('themes_b2c.ciak.discover_selection') }}<i data-lucide="arrow-right"></i></a>
                            @else
                                <span class="ciak-format-unavailable">{{ __('themes_b2c.ciak.coming_soon') }}</span>
                            @endif
                        </div>

                        <div class="ciak-format-stage" data-ciak-format-stage>
                            <div class="ciak-format-layer ciak-format-layer-main">
                                <img src="{{ $item['detail_image'] }}" alt="{{ $item['label'] }}" loading="lazy" decoding="async">
                            </div>
                            <div class="ciak-format-layer ciak-format-layer-tech" aria-hidden="true">
                                <span class="ciak-format-tech-line is-top"></span>
                                <span class="ciak-format-tech-line is-mid"></span>
                                <span class="ciak-format-tech-line is-bottom"></span>
                                <span class="ciak-format-tech-dot is-left"></span>
                                <span class="ciak-format-tech-dot is-right"></span>
                                <span class="ciak-format-tech-label is-left">{{ $item['specs'][0] ?? __('themes_b2c.ciak.layout') }}</span>
                                <span class="ciak-format-tech-label is-right">{{ $item['specs'][1] ?? __('themes_b2c.ciak.detail') }}</span>
                            </div>
                        </div>

                        <aside class="ciak-format-side-notes" aria-label="{{ __('themes_b2c.ciak.details') }}">
                            <span>{{ $item['available'] ? __('themes_b2c.ciak.available') : __('themes_b2c.ciak.unavailable') }}</span>
                            <strong>{{ __('themes_b2c.ciak.layout') }}</strong>
                            <em>{{ $item['label'] }}</em>
                        </aside>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($featuredRows->isNotEmpty())
        <section class="ciak-products-section ciak-shell" aria-labelledby="ciak-featured-title">
            <header class="ciak-section-heading"><div><p class="ciak-eyebrow">{{ __('themes_b2c.ciak.featured') }}</p><h2 id="ciak-featured-title">{{ __('themes_b2c.ciak.picked_for_you') }}</h2></div><a href="{{ route('storefront.catalog.index') }}">{{ __('themes_b2c.ciak.view_all') }}<i data-lucide="arrow-right"></i></a></header>
            <div class="ciak-products-grid">
                @foreach($featuredRows as $row)
                    @include('storefront.base.partials.product-card', ['product' => $row['product'], 'listingCard' => $row['listingCard']])
                @endforeach
            </div>
        </section>
    @endif

    @if($editorialSection)
        <section class="ciak-editorial" aria-label="{{ $editorialSection['block']->title ?: __('themes_b2c.ciak.story') }}">
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
        <section class="ciak-vision-section" aria-label="{{ $visionSection['block']->title ?: __('themes_b2c.ciak.vision') }}">
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
        <section
            class="ciak-instagram-section"
            aria-label="{{ $instagramSection['block']->title ?: __('themes_b2c.ciak.instagram') }}"
            data-ciak-instagram
            data-instagram-url="{{ Route::has('storefront.instagram.gallery') ? route('storefront.instagram.gallery') : '' }}"
        >
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
        const tabs = Array.from(section.querySelectorAll('[data-ciak-format-tab]'));
        const panels = Array.from(section.querySelectorAll('[data-ciak-format-panel]'));

        const activate = function (index) {
            tabs.forEach(function (tab) {
                const active = Number(tab.dataset.ciakFormatIndex || 0) === index;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            panels.forEach(function (panel) {
                const active = Number(panel.dataset.ciakFormatIndex || 0) === index;
                panel.classList.toggle('is-active', active);
                panel.hidden = !active;
            });
        };

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activate(Number(tab.dataset.ciakFormatIndex || 0));
            });
        });
    });

    document.querySelectorAll('[data-ciak-instagram]').forEach(function (section) {
        const grid = section.querySelector('[data-ciak-instagram-grid]');
        const button = section.querySelector('[data-ciak-instagram-more]');
        const endpoint = section.dataset.instagramUrl || '';

        if (!grid || !button || !endpoint) return;

        const formatNumber = function (value) {
            const number = Number(value || 0);
            return new Intl.NumberFormat('it-IT').format(number);
        };

        const escapeHtml = function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const cardHtml = function (item) {
            const isVideo = item.type === 'video';
            const permalink = item.permalink || '';
            const media = isVideo
                ? '<video autoplay muted loop playsinline preload="metadata" poster="' + escapeHtml(item.poster || '') + '"><source src="' + escapeHtml(item.desktop || '') + '"></video>'
                : '<picture>' + (item.mobile ? '<source media="(max-width:767px)" srcset="' + escapeHtml(item.mobile) + '">' : '') + '<img src="' + escapeHtml(item.desktop || '') + '" alt="' + escapeHtml(item.alt || 'Instagram') + '" loading="lazy" decoding="async"></picture>';
            const hasLikes = item.likes !== null && item.likes !== undefined;
            const hasComments = item.comments !== null && item.comments !== undefined;
            const metrics = (hasLikes || hasComments)
                ? '<span class="ciak-instagram-metrics">' +
                    (hasLikes ? '<span><i data-lucide="heart"></i>' + formatNumber(item.likes) + '</span>' : '') +
                    (hasComments ? '<span><i data-lucide="message-circle"></i>' + formatNumber(item.comments) + '</span>' : '') +
                  '</span>'
                : '';
            const inner = media +
                '<figcaption>' +
                    '<span class="ciak-instagram-badge"><i data-lucide="instagram"></i> Instagram</span>' +
                    metrics +
                    '<span class="ciak-instagram-caption">' + escapeHtml(item.alt || '') + '</span>' +
                '</figcaption>';

            return '<figure class="ciak-instagram-card ' + (isVideo ? 'is-video' : '') + '" data-ciak-instagram-card>' +
                (permalink ? '<a href="' + escapeHtml(permalink) + '" target="_blank" rel="noopener" aria-label="{{ __('themes_b2c.ciak.open_instagram_post') }}">' + inner + '</a>' : inner) +
                '</figure>';
        };

        button.addEventListener('click', async function () {
            const offset = Number(button.dataset.offset || 12);
            button.disabled = true;
            button.classList.add('is-loading');

            try {
                const url = new URL(endpoint, window.location.origin);
                url.searchParams.set('offset', String(offset));
                url.searchParams.set('limit', '12');

                const response = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) throw new Error('Instagram gallery request failed');

                const payload = await response.json();
                const items = Array.isArray(payload.items) ? payload.items : [];

                if (items.length) {
                    grid.insertAdjacentHTML('beforeend', items.map(cardHtml).join(''));
                    button.dataset.offset = String(payload.next_offset || (offset + items.length));

                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }

                if (!payload.has_more || !items.length) {
                    button.closest('.ciak-instagram-more')?.remove();
                }
            } catch (error) {
                console.warn(error);
                button.disabled = false;
            } finally {
                button.classList.remove('is-loading');
                if (document.body.contains(button)) {
                    button.disabled = false;
                }
            }
        });
    });
});
</script>
@endpush

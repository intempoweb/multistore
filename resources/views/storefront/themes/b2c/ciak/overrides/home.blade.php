@extends($storefrontLayout)

@section('title', $storefrontPage?->meta_title ?: ($storefrontPage?->title ?: $store->name))
@section('meta_description', $storefrontPage?->meta_description ?: $storefrontPage?->description)

@section('content')
<div class="ciak-home">
    <section class="ciak-hero" data-ciak-hero>
        <div class="ciak-hero-copy">
            @if(filled($hero?->subtitle))<p class="ciak-eyebrow">{{ $hero->subtitle }}</p>@endif
            <h1>{{ $hero?->title ?: $storefrontPage?->title ?: $store->name }}</h1>
            @if(filled($hero?->content))<p class="ciak-lead">{{ $hero->content }}</p>@endif
            @if(filled($hero?->button_label))
                <a class="ciak-primary-link" href="{{ $heroButtonUrl }}" @if($hero?->button_new_tab) target="_blank" rel="noopener" @endif>{{ $hero->button_label }}<i data-lucide="arrow-right"></i></a>
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
                        <a class="ciak-format-card" href="{{ $item['url'] }}">
                            <span><img src="{{ $item['image'] }}" alt="" loading="lazy"></span><strong>{{ $item['label'] }}</strong>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </section>
    @endif

    @if($featuredRows->isNotEmpty())
        <section class="ciak-products-section ciak-shell">
            <header class="ciak-section-heading"><div><p class="ciak-eyebrow">{{ __('In evidenza') }}</p><h2>{{ __('Scelti per te') }}</h2></div><a href="{{ route('storefront.catalog.index') }}">{{ __('Vedi tutto') }}<i data-lucide="arrow-right"></i></a></header>
            <div class="ciak-products-grid">
                @foreach($featuredRows as $row)
                    @include('storefront.base.partials.product-card', ['product' => $row['product'], 'listingCard' => $row['listingCard']])
                @endforeach
            </div>
        </section>
    @endif

    @if($editorialSection)
        <section class="ciak-editorial">
            <div class="ciak-editorial-media"><picture>@if($editorialSection['mobile_image'])<source media="(max-width:767px)" srcset="{{ $editorialSection['mobile_image'] }}">@endif<img src="{{ $editorialSection['image'] }}" alt="{{ $editorialSection['block']->title ?: $store->name }}" loading="lazy"></picture></div>
            <div class="ciak-editorial-copy">@if($editorialSection['block']->subtitle)<p class="ciak-eyebrow">{{ $editorialSection['block']->subtitle }}</p>@endif<h2>{{ $editorialSection['block']->title }}</h2>@if($editorialSection['block']->content)<p>{{ $editorialSection['block']->content }}</p>@endif @if($editorialSection['block']->button_label)<a href="{{ $editorialSection['button_url'] }}">{{ $editorialSection['block']->button_label }}<i data-lucide="arrow-right"></i></a>@endif</div>
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

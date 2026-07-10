@extends($storefrontLayout)

@php
    $pageFallback = __('themes_b2c.ciak.about_vision.pages.' . $pageKey);
    $pageFallback = is_array($pageFallback) ? $pageFallback : [];

    $pageTitle = $storefrontPage?->title ?: ($pageFallback['title'] ?? $store->name);
    $pageEyebrow = $pageFallback['eyebrow'] ?? '';
    $pageBody = $storefrontPage?->description ?: null;
    $pageLead = $pageFallback['lead'] ?? '';

    if (filled($pageBody)) {
        $pageLead = trim(strtok($pageBody, "\n")) ?: $pageLead;
    }

    $points = $pageFallback['points'] ?? [];
    $points = is_array($points) ? $points : [];
    $backCta = $pageFallback['back_cta'] ?? __('themes_b2c.ciak.about');
@endphp

@section('title', $storefrontPage?->meta_title ?: $pageTitle)
@section('meta_description', $storefrontPage?->meta_description ?: $pageLead)

@section('content')
<section class="ciak-brand-page ciak-brand-page-{{ $pageKey }}">
    <div class="ciak-shell">
        <header class="ciak-brand-page-heading">
            @if(filled($pageEyebrow))
                <p class="ciak-eyebrow">{{ $pageEyebrow }}</p>
            @endif

            <h1>{{ $pageTitle }}</h1>

            @if(filled($pageLead))
                <p>{{ $pageLead }}</p>
            @endif
        </header>

        <div class="ciak-brand-page-body">
            @if(filled($pageBody))
                <div class="ciak-brand-page-copy">
                    {!! nl2br(e($pageBody)) !!}
                </div>
            @endif

            @if(!empty($points))
                <div class="ciak-brand-page-points">
                    @foreach($points as $point)
                        <article>
                            <i data-lucide="{{ $point['icon'] ?? 'sparkles' }}"></i>
                            <h2>{{ $point['title'] ?? '' }}</h2>
                            <p>{{ $point['text'] ?? '' }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="ciak-brand-page-actions">
            <a class="ciak-about-vision-cta" href="{{ route('storefront.home') }}#{{ $pageKey }}">
                <i data-lucide="arrow-left"></i>
                {{ $backCta }}
            </a>
        </div>
    </div>
</section>
@endsection

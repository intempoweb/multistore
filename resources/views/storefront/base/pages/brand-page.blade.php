@extends($storefrontLayout)

@php
    $page = __('themes_b2c.ciak.about_vision.pages.' . $pageKey);
    $page = is_array($page) ? $page : [];
    $body = $page['body'] ?? [];
    $body = is_array($body) ? $body : [];
    $points = $page['points'] ?? [];
    $points = is_array($points) ? $points : [];
@endphp

@section('title', $page['title'] ?? $store->name)
@section('meta_description', $page['lead'] ?? null)

@section('content')
<section class="ciak-brand-page ciak-brand-page-{{ $pageKey }}">
    <div class="ciak-shell">
        <header class="ciak-brand-page-heading">
            <p class="ciak-eyebrow">{{ $page['eyebrow'] ?? '' }}</p>
            <h1>{{ $page['title'] ?? '' }}</h1>
            <p>{{ $page['lead'] ?? '' }}</p>
        </header>

        <div class="ciak-brand-page-body">
            <div class="ciak-brand-page-copy">
                @foreach($body as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>

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
                {{ $page['back_cta'] ?? __('themes_b2c.ciak.about') }}
            </a>
        </div>
    </div>
</section>
@endsection

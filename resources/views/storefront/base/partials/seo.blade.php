@php
    $seoData = collect($seo ?? []);
    $seoDescription = trim((string) ($seoData->get('description') ?: $__env->yieldContent('meta_description')));
    $seoCanonical = trim((string) $seoData->get('canonical'));
    $seoRobots = trim((string) ($seoData->get('robots') ?: 'index,follow'));
    $seoTitle = trim((string) ($seoData->get('title') ?: $__env->yieldContent('title')));
    $seoOgTitle = trim((string) ($seoData->get('og_title') ?: $seoTitle));
    $seoOgDescription = trim((string) ($seoData->get('og_description') ?: $seoDescription));
@endphp
@if($seoDescription !== '')<meta name="description" content="{{ $seoDescription }}">@endif
<meta name="robots" content="{{ $seoRobots }}">
@if($seoCanonical !== '')<link rel="canonical" href="{{ $seoCanonical }}">@endif
@foreach(($seoData->get('alternates') ?? []) as $alternateLocale => $alternateUrl)
    <link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ $alternateUrl }}">
@endforeach
@if($seoOgTitle !== '')<meta property="og:title" content="{{ $seoOgTitle }}">@endif
@if($seoOgDescription !== '')<meta property="og:description" content="{{ $seoOgDescription }}">@endif
@if($seoCanonical !== '')<meta property="og:url" content="{{ $seoCanonical }}">@endif
<meta property="og:type" content="{{ $seoData->get('og_type', 'website') }}">
@if($seoData->get('og_image'))<meta property="og:image" content="{{ $seoData->get('og_image') }}">@endif
<meta name="twitter:card" content="{{ $seoData->get('og_image') ? 'summary_large_image' : 'summary' }}">
@if($seoOgTitle !== '')<meta name="twitter:title" content="{{ $seoOgTitle }}">@endif
@if($seoOgDescription !== '')<meta name="twitter:description" content="{{ $seoOgDescription }}">@endif
@if($seoData->get('json_ld'))
    <script type="application/ld+json">@json(array_filter($seoData->get('json_ld'), fn ($value) => $value !== null))</script>
@endif

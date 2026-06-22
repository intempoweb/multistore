@php
    $categoryLabel = trim((string) ($category['label'] ?? ''));
    $categorySlug = trim((string) ($category['slug'] ?? ''));
@endphp
@if($categoryLabel !== '' && $categorySlug !== '')
    <a class="ciak-nav-link" href="{{ route('storefront.category.show', $categorySlug) }}">{{ $categoryLabel }}</a>
@endif

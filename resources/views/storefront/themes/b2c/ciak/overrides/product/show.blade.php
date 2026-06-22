@extends($storefrontLayout)

@section('title', $seo['title'] ?? ($selectedTranslation?->name ?: $selectedProduct->sku))

@section('content')
@php
    $productName = $selectedTranslation?->name ?: $baseTranslation?->name ?: $selectedProduct->sku;
    $description = $selectedTranslation?->description ?: $baseTranslation?->description;
    $shortDescription = $selectedTranslation?->short_description ?: $baseTranslation?->short_description;
    $gallery = collect($galleryImages ?? [])->filter(fn ($item) => filled($item['url'] ?? null))->values();
    $displayImage = $gallery->first()['url'] ?? $image;
    $contextParams = request()->filled('agent_context') ? ['agent_context' => request('agent_context')] : [];
@endphp

<div class="ciak-product-page ciak-shell" data-ciak-product data-product-page data-product-card data-product-sku="{{ $selectedProduct->sku }}">
    <nav class="ciak-breadcrumb" aria-label="breadcrumb"><a href="{{ route('storefront.catalog.index', $contextParams) }}">{{ __('Catalogo') }}</a><i data-lucide="chevron-right"></i><span>{{ $productName }}</span></nav>

    <div class="ciak-product-layout">
        <section class="ciak-product-gallery">
            <div class="ciak-product-main-image">
                @if($displayImage)<img src="{{ $displayImage }}" alt="{{ $productName }}" data-ciak-product-main-image>@else<span>{{ __('Immagine non disponibile') }}</span>@endif
            </div>
            @if($gallery->count() > 1)
                <div class="ciak-product-thumbs">
                    @foreach($gallery as $galleryImage)
                        <button type="button" class="{{ $loop->first ? 'is-active' : '' }}" data-ciak-product-thumb="{{ $galleryImage['url'] }}" aria-label="{{ __('Mostra immagine') }} {{ $loop->iteration }}"><img src="{{ $galleryImage['url'] }}" alt="" loading="lazy"></button>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="ciak-product-info">
            <p class="ciak-product-sku">SKU {{ $selectedProduct->sku }}</p>
            <h1>{{ $productName }}</h1>
            @if($shortDescription)<div class="ciak-product-intro">{!! nl2br(e($shortDescription)) !!}</div>@endif
            <div id="product-price-display" class="ciak-product-price" data-base-price="{{ $effectivePrice !== null ? number_format((float) $effectivePrice, 3, '.', '') : '' }}">{{ $effectivePrice !== null ? '€ ' . number_format((float) $effectivePrice, 2, ',', '.') : '—' }}</div>
            <div class="ciak-product-stock {{ $canAddToCart ? 'is-available' : 'is-unavailable' }}"><span></span>{{ __($stockLabel) }}</div>

            @if(collect($colorOptions)->isNotEmpty())
                <div class="ciak-product-options"><span>{{ __('Colore') }}</span><div>
                    @foreach($colorOptions as $option)
                        <a href="{{ route('storefront.product.show', array_merge(['sku' => $option['sku']], $contextParams)) }}" class="ciak-product-swatch {{ ($option['value'] ?? null) === $selectedColorValue ? 'is-active' : '' }}" title="{{ $option['value'] }}">
                            @if(!empty($option['swatch_url']))<img src="{{ $option['swatch_url'] }}" alt="{{ $option['value'] }}">@else<span>{{ $option['value'] }}</span>@endif
                        </a>
                    @endforeach
                </div></div>
            @endif

            @if(collect($formatOptions)->isNotEmpty())
                <div class="ciak-product-options"><span>{{ __('Formato') }}</span><div>
                    @foreach($formatOptions as $option)<a href="{{ route('storefront.product.show', array_merge(['sku' => $option['sku']], $contextParams)) }}" class="ciak-product-format {{ ($option['value'] ?? null) === $selectedFormatValue ? 'is-active' : '' }}">{{ $option['value'] }}</a>@endforeach
                </div></div>
            @endif

            <form
                id="product-add-to-cart-form"
                method="POST"
                action="{{ route('storefront.cart.add', $contextParams) }}"
                class="ciak-product-buy"
                data-cart-add-form
            >
                @csrf
                <input type="hidden" name="sku" value="{{ $selectedProduct->sku }}">
                <input id="product-quantity-input" type="hidden" name="qty" value="{{ $quantityMin }}" min="{{ $quantityMin }}" step="{{ $quantityStep }}" data-qty-min="{{ $quantityMin }}" data-qty-step="{{ $quantityStep }}">
                <button id="product-add-to-cart-button" type="submit" class="ciak-primary-link" @disabled(!$canAddToCart)><i data-lucide="shopping-bag"></i>{{ $canAddToCart ? __('Aggiungi al carrello') : __('Non disponibile') }}</button>
                <div id="product-add-to-cart-feedback" class="small mt-2 d-none" data-cart-feedback></div>
            </form>

            @if($description)<div class="ciak-product-description">{!! nl2br(e($description)) !!}</div>@endif
            @if(collect($technicalRows)->isNotEmpty())
                <details class="ciak-product-details"><summary>{{ __('Dettagli prodotto') }}<i data-lucide="plus"></i></summary><dl>@foreach($technicalRows as $row)<div><dt>{{ $row['label'] ?? $row['name'] ?? '' }}</dt><dd>{{ $row['value'] ?? '' }}</dd></div>@endforeach</dl></details>
            @endif
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const main = document.querySelector('[data-ciak-product-main-image]');
    document.querySelectorAll('[data-ciak-product-thumb]').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            if (main) main.src = thumb.dataset.ciakProductThumb;
            document.querySelectorAll('[data-ciak-product-thumb]').forEach(function (item) { item.classList.toggle('is-active', item === thumb); });
        });
    });
});
</script>
@endpush

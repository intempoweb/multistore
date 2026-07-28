@php
    $card = \App\Models\ProductCardViewModel::make($product ?? null, $listingCard ?? []);
    $agentContextId = $agentContextId ?? (string) request('agent_context', '');
    $productUrl = $card->productUrl;
    $contextUrl = static function (?string $url) use ($agentContextId): ?string {
        if (!$url || $agentContextId === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['agent_context' => $agentContextId]);
    };

    if ($productUrl && $agentContextId !== '') {
        $productUrl .= (str_contains($productUrl, '?') ? '&' : '?') . http_build_query(['agent_context' => $agentContextId]);
    }
@endphp

<article class="ready-home-product" data-product-card data-product-sku="{{ $card->targetSku }}">
    <a class="ready-home-product-image" href="{{ $productUrl }}" data-product-card-link>
        @if($card->image)
            <img
                src="{{ $card->image }}"
                alt="{{ $card->name }}"
                loading="lazy"
                decoding="async"
                data-product-card-image
            >
        @endif
    </a>
    <a class="ready-home-product-title" href="{{ $productUrl }}" data-product-card-link data-product-card-title>{{ $card->name }}</a>

    @if($card->variants > 1)
        <span class="ready-home-product-variants">
            {{ trans_choice(__('themes_b2c.product.variant_count'), $card->variants, ['count' => $card->variants]) }}
        </span>
    @endif

    @if($card->colorOptions->isNotEmpty())
        <div class="ready-home-product-options" aria-label="{{ __('themes_b2c.product.color') }}">
            @foreach($card->colorOptions->take(12) as $option)
                @php($payload = $card->colorOptionPayload($option))
                <button
                    type="button"
                    class="{{ $payload['is_selected'] ? 'is-active is-selected' : '' }}"
                    data-product-card-variant
                    data-variant-type="color"
                    data-variant-sku="{{ $payload['sku'] }}"
                    data-variant-url="{{ $contextUrl($payload['url'] ?? null) }}"
                    data-variant-image="{{ $payload['image'] }}"
                    data-variant-hover-image="{{ $payload['hover_image'] }}"
                    data-variant-price="{{ $payload['price_raw'] ?? '' }}"
                    data-variant-qty-min="{{ $payload['quantity_min'] }}"
                    data-variant-qty-step="{{ $payload['quantity_step'] }}"
                    data-variant-qty-max="{{ $payload['quantity_max'] ?? '' }}"
                    data-variant-pack-multiple="{{ $payload['pack_multiple'] }}"
                    data-variant-purchasable="{{ $payload['is_purchasable'] ? '1' : '0' }}"
                    title="{{ $payload['value'] ?? '' }}"
                    aria-label="{{ __('themes_b2c.product.color') }} {{ $payload['value'] ?? '-' }}"
                    aria-pressed="{{ $payload['is_selected'] ? 'true' : 'false' }}"
                >
                    @if(!empty($payload['swatch_url']))
                        <img src="{{ $payload['swatch_url'] }}" alt="{{ $payload['value'] ?? '' }}" loading="lazy" decoding="async">
                    @else
                        <span>{{ mb_substr((string) ($payload['value'] ?? '-'), 0, 1) }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    @if($card->formatOptions->isNotEmpty())
        <div class="ready-home-product-formats" aria-label="{{ __('themes_b2c.product.format') }}">
            @foreach($card->formatOptions->take(4) as $option)
                @php($payload = $card->formatOptionPayload($option))
                <button
                    type="button"
                    class="{{ $payload['is_selected'] ? 'is-active is-selected' : '' }}"
                    data-product-card-variant
                    data-variant-type="format"
                    data-variant-sku="{{ $payload['sku'] }}"
                    data-variant-url="{{ $contextUrl($payload['url'] ?? null) }}"
                    data-variant-image="{{ $payload['image'] }}"
                    data-variant-hover-image="{{ $payload['hover_image'] }}"
                    data-variant-price="{{ $payload['price_raw'] ?? '' }}"
                    data-variant-qty-min="{{ $payload['quantity_min'] }}"
                    data-variant-qty-step="{{ $payload['quantity_step'] }}"
                    data-variant-qty-max="{{ $payload['quantity_max'] ?? '' }}"
                    data-variant-pack-multiple="{{ $payload['pack_multiple'] }}"
                    data-variant-purchasable="{{ $payload['is_purchasable'] ? '1' : '0' }}"
                    aria-pressed="{{ $payload['is_selected'] ? 'true' : 'false' }}"
                >
                    {{ $payload['value'] ?? '-' }}
                </button>
            @endforeach
        </div>
    @endif

    <p data-product-card-price>{{ $card->formattedPrice() }}</p>
</article>

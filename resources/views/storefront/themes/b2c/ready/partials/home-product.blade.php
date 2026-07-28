@php
    $card = \App\Models\ProductCardViewModel::make($product ?? null, $listingCard ?? []);
    $agentContextId = $agentContextId ?? (string) request('agent_context', '');
    $productUrl = $card->productUrl;

    if ($productUrl && $agentContextId !== '') {
        $productUrl .= (str_contains($productUrl, '?') ? '&' : '?') . http_build_query(['agent_context' => $agentContextId]);
    }
@endphp

<article class="ready-home-product" data-product-card data-product-sku="{{ $card->targetSku }}">
    <a class="ready-home-product-image" href="{{ $productUrl }}">
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
    <a class="ready-home-product-title" href="{{ $productUrl }}">{{ $card->name }}</a>
    <p>{{ $card->formattedPrice() }}</p>
</article>

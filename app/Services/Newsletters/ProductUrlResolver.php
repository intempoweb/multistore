<?php

namespace App\Services\Newsletters;

use App\Models\Product;
use App\Models\Store;

class ProductUrlResolver
{
    public function resolve(Product $product, Store $store, string $locale): string
    {
        $path = route('storefront.product.show', [
            'locale' => $locale,
            'sku' => $product->sku,
        ], false);

        $domain = trim((string) $store->domain);
        $base = $domain !== ''
            ? 'https://' . preg_replace('#^https?://#', '', $domain)
            : rtrim((string) config('app.url'), '/');

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

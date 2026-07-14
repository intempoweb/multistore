<?php

namespace App\Services\Storefront\Documents;

use App\Models\Erp\DocumentHeader;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\Store;

class DocumentProductResolver
{
    public function attachProducts(DocumentHeader $document, Store $store): DocumentHeader
    {
        $rows = collect($document->rows ?? []);
        $skus = $rows
            ->map(fn ($row) => trim((string) ($row->CODART_MG66 ?? '')))
            ->filter()
            ->unique()
            ->values();

        if ($skus->isEmpty()) {
            return $document;
        }

        $products = Product::query()
            ->with(['mediaAssets' => function ($query) {
                $query
                    ->whereIn('role', [MediaAsset::ROLE_MAIN, MediaAsset::ROLE_GALLERY])
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->where('ditta_cg18', (int) ($document->DITTA_CG18 ?? $store->ditta_cg18))
            ->where('site_type', (int) $store->erp_site_code)
            ->whereIn('sku', $skus->all())
            ->get()
            ->keyBy(fn (Product $product) => trim((string) $product->sku));

        foreach ($rows as $row) {
            $sku = trim((string) ($row->CODART_MG66 ?? ''));

            $row->setAttribute('document_product', $products->get($sku));
        }

        return $document;
    }
}

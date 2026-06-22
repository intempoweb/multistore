<?php

namespace App\Services\Storefront\Seo;

use App\Models\Product;
use App\Models\Store;

class ProductSeoGenerator
{
    private array $storeNames = [];

    public function generate(Product $product, string $locale, ?string $name = null, ?string $description = null): array
    {
        $name = trim((string) ($name ?: $product->sku));
        $storeName = $this->storeName($product);
        $title = $this->limitAtWord(trim($name . ($storeName !== '' ? ' | ' . $storeName : '')), 65);
        $plainDescription = trim(preg_replace('/\s+/', ' ', strip_tags((string) $description)) ?? '');

        if ($plainDescription === '') {
            $plainDescription = "Scopri {$name}. Codice articolo {$product->sku}";
            if ($storeName !== '') {
                $plainDescription .= " su {$storeName}";
            }
            $plainDescription .= '.';
        }

        return [
            'seo_title' => $title,
            'seo_description' => $this->limitAtWord($plainDescription, 160),
        ];
    }

    private function limitAtWord(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        $truncated = mb_substr($value, 0, $limit + 1);
        $lastSpace = mb_strrpos($truncated, ' ');

        return rtrim($lastSpace === false ? mb_substr($value, 0, $limit) : mb_substr($truncated, 0, $lastSpace), " \t\n\r\0\x0B,;:-");
    }

    private function storeName(Product $product): string
    {
        $key = ((int) $product->ditta_cg18) . ':' . ((int) $product->site_type);

        return $this->storeNames[$key] ??= trim((string) Store::query()
            ->where('ditta_cg18', (int) $product->ditta_cg18)
            ->where('erp_site_code', (int) $product->site_type)
            ->value('name'));
    }
}

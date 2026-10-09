<?php

namespace App\Services\Meta;

use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\Store;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\Pricing\ProductPriceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use XMLWriter;

class ReadyCatalogFeedService
{
    public const RELATIVE_PATH = 'feeds/meta/products.xml';

    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly ProductPriceService $prices,
    ) {}

    /** @return array{exported:int,skipped:int,path:string} */
    public function generate(): array
    {
        $store = Store::query()->where('site_code', 'READY')->where('is_active', true)->first();

        if (!$store || !$store->isB2C() || (int) $store->ditta_cg18 !== 1 || (int) $store->erp_site_code !== 7) {
            throw new RuntimeException('Store READY B2C (ditta 1, sito ERP 7) non trovato o non coerente.');
        }

        $directory = public_path('feeds/meta');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossibile creare la directory: '.$directory);
        }

        $destination = public_path(self::RELATIVE_PATH);
        $temporary = tempnam($directory, '.ready-meta-');
        if ($temporary === false) {
            throw new RuntimeException('Impossibile creare il file temporaneo del feed.');
        }

        $xml = new XMLWriter();
        $exported = 0;
        $skipped = 0;

        try {
            if (!$xml->openUri($temporary)) {
                throw new RuntimeException('Impossibile aprire il feed XML temporaneo.');
            }

            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('rss');
            $xml->writeAttribute('version', '2.0');
            $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
            $xml->startElement('channel');
            $xml->writeElement('title', 'Ready - Catalogo Meta');
            $xml->writeElement('link', 'https://ready-to.it/it');
            $xml->writeElement('description', 'Catalogo prodotti Ready B2C');

            Product::query()
                ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
                ->active()
                ->where('type', 'simple')
                ->with([
                    'translations' => fn ($query) => $query->whereIn('locale', ['it', config('app.fallback_locale', 'en')]),
                    'mediaAssets',
                    'parent.translations' => fn ($query) => $query->whereIn('locale', ['it', config('app.fallback_locale', 'en')]),
                    'parent.mediaAssets',
                ])
                ->orderBy('id')
                ->chunkById(200, function ($products) use ($store, $xml, &$exported, &$skipped): void {
                    foreach ($products as $product) {
                        try {
                            if ($this->writeProduct($xml, $store, $product)) {
                                $exported++;
                            } else {
                                $skipped++;
                            }
                        } catch (Throwable $exception) {
                            $skipped++;
                            Log::warning('Ready Meta: prodotto ignorato', [
                                'sku' => $product->sku,
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    }
                    $xml->flush();
                });

            $xml->endElement(); // channel
            $xml->endElement(); // rss
            $xml->endDocument();
            $xml->flush();

            if ($exported === 0) {
                throw new RuntimeException('Nessun prodotto valido esportato: feed precedente conservato.');
            }

            if (!rename($temporary, $destination)) {
                throw new RuntimeException('Impossibile pubblicare il feed XML.');
            }

            @chmod($destination, 0644);

            return ['exported' => $exported, 'skipped' => $skipped, 'path' => $destination];
        } finally {
            unset($xml);
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function writeProduct(XMLWriter $xml, Store $store, Product $product): bool
    {
        $sku = trim((string) $product->sku);
        if ($sku === '') {
            return false;
        }

        $parent = $product->parent;
        if ($parent instanceof Product && (!(bool) $parent->is_active || $parent->type !== 'configurable')) {
            $parent = null;
        }

        $translation = $product->translations->firstWhere('locale', 'it')
            ?? $parent?->translations?->firstWhere('locale', 'it')
            ?? $product->translations->first()
            ?? $parent?->translations?->first();

        $title = trim((string) ($translation?->name ?? $sku));
        $description = trim(strip_tags((string) ($translation?->description ?: $translation?->short_description ?: $title)));
        $description = preg_replace('/\s+/u', ' ', html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: $title;

        $image = $this->imageUrl($product) ?? ($parent ? $this->imageUrl($parent) : null);
        if (!$image || !filter_var($image, FILTER_VALIDATE_URL) || !str_starts_with($image, 'https://')) {
            return false;
        }

        $resolved = $this->prices->resolveForListing($store, $product, 1, null);
        $price = $resolved['price'] ?? null;
        if (!is_numeric($price) || (float) $price <= 0) {
            return false;
        }

        $minQty = max(1, (int) ceil((float) ($product->min_order_qty ?? 1)));
        $pack = max(1, (int) ceil((float) ($product->pzconf_mg68 ?? 0)));
        $minQty = max($minQty, $pack);
        if ($pack > 1 && $minQty % $pack !== 0) {
            $minQty = (int) ceil($minQty / $pack) * $pack;
        }
        $available = $product->stock_qty === null || floor((float) $product->stock_qty) >= $minQty;

        // URL assoluto sul dominio Ready, indipendente da APP_URL o dal contesto CLI.
        $slug = $this->catalog->buildProductSlug($product, 'it');
        $url = 'https://ready-to.it/it/product/'.rawurlencode($slug);

        $xml->startElement('item');
        $this->g($xml, 'id', $sku);
        $this->g($xml, 'title', Str::limit($title, 150, ''));
        $this->g($xml, 'description', Str::limit($description, 5000, ''));
        $this->g($xml, 'link', $url);
        $this->g($xml, 'image_link', $image);
        $this->g($xml, 'availability', $available ? 'in stock' : 'out of stock');
        $this->g($xml, 'condition', 'new');
        $this->g($xml, 'price', number_format((float) $price, 2, '.', '').' EUR');
        $this->g($xml, 'brand', trim((string) ($product->marca_mg64 ?: 'Ready')));

        $parentSku = trim((string) ($product->parent_code ?? ''));
        if ($parentSku !== '' && $parent instanceof Product) {
            $this->g($xml, 'item_group_id', $parentSku);
        }
        if (trim((string) ($product->barcode ?? '')) !== '') {
            $this->g($xml, 'gtin', trim((string) $product->barcode));
        }
        $xml->endElement();

        return true;
    }

    private function imageUrl(Product $product): ?string
    {
        $assets = $product->mediaAssets
            ->filter(fn ($asset) => in_array($asset->role, [MediaAsset::ROLE_MAIN, MediaAsset::ROLE_GALLERY], true))
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']]);

        $asset = $assets->firstWhere('role', MediaAsset::ROLE_MAIN) ?? $assets->first();
        return $asset?->url;
    }

    private function g(XMLWriter $xml, string $name, string $value): void
    {
        $xml->writeElementNs('g', $name, 'http://base.google.com/ns/1.0', $value);
    }
}

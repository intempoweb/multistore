<?php

namespace App\Services\Storefront\Home\Presenters;

use App\Data\Storefront\HomePageInput;
use App\Models\ProductCardViewModel;
use App\Models\Store;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\Home\HomePagePresenter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

final class FipellHomePagePresenter implements HomePagePresenter
{
    public function __construct(
        private CatalogRepository $catalogRepository,
        private AuthFactory $auth,
    ) {}

    public function supports(Store $store): bool
    {
        return $store->isB2B() && strtolower(trim((string) $store->theme)) === 'fipell';
    }

    public function present(HomePageInput $input): array
    {
        $products = collect($input->products?->items() ?? []);
        $contextId = (string) $input->request->input('agent_context', '');
        $contextParams = $contextId !== '' ? ['agent_context' => $contextId] : [];
        $catalogUrl = route('storefront.catalog.index', $contextParams);
        $documentsUrl = route('storefront.account.documents.index', $contextParams);
        $customer = $this->auth->guard('customer')->user();
        $priorityProducts = $products->sortByDesc(fn ($product) => ((bool) ($product->flgofferta_webt01 ?? false) || (bool) ($product->flgpromo_webt01 ?? false) ? 100 : 0)
            + ((bool) ($product->flgnovita_webt01 ?? false) ? 40 : 0)
            + ($this->isOrderable($product) ? 20 : 0)
            + (! empty($product->main_image_url) ? 10 : 0)
        )->values();
        $heroProducts = $this->heroProducts($input, $priorityProducts);
        $newProducts = $products->filter(fn ($product) => (bool) ($product->flgnovita_webt01 ?? false))->values();
        $featuredProducts = $newProducts->isNotEmpty()
            ? $newProducts->shuffle()->take(4)->values()
            : $priorityProducts->filter(fn ($product) => $this->isOrderable($product))->shuffle()->take(4)->values();

        if ($featuredProducts->isEmpty()) {
            $featuredProducts = $priorityProducts->shuffle()->take(4)->values();
        }

        return [
            'catalogUrl' => $catalogUrl,
            'documentsUrl' => $documentsUrl,
            'accountUrl' => route('storefront.account.index', $contextParams),
            'quickOrderEnabled' => $input->store->isB2B() && $this->auth->guard('customer')->check(),
            'customerName' => trim((string) (
                $customer?->ragsoanag_cg16
                ?? $customer?->ragsocor_cg16
                ?? collect([$customer?->nomeconnweb, $customer?->cognomeconnweb])->filter()->implode(' ')
            )),
            'productsTotal' => $input->products?->total() ?? $products->count(),
            'rootCategories' => $input->rootCategories,
            'categoryCards' => $this->categoryCards($input->rootCategories, $catalogUrl, $contextParams),
            'heroCards' => $heroProducts->map(fn ($product) => $this->cardRow($product, $input, $contextId)),
            'featuredCards' => $featuredProducts->map(fn ($product) => $this->featuredCardRow($product, $input, $contextId)),
        ];
    }

    private function heroProducts(HomePageInput $input, Collection $priorityProducts): Collection
    {
        $themes = [
            ['cartoleria', 'notes', 'quaderni', 'rubriche', 'scrittura', 'penne', 'carta', 'calendario'],
            ['scuola', 'didattica', 'ready', 'diario', 'agende', 'astucci', 'colori'],
            ['ufficio', 'arredo', 'archiviazione', 'modultime', 'modulistica', 'informatica', 'consumabili'],
        ];
        $selected = collect();
        $selectedSkus = collect();
        $selectedFamilies = collect();

        foreach ($themes as $keywords) {
            $categories = $input->rootCategories->filter(function ($category) use ($keywords) {
                $label = Str::lower((string) ($category['label'] ?? $category['code'] ?? ''));

                return collect($keywords)->contains(fn ($keyword) => str_contains($label, $keyword));
            })->shuffle()->values();

            foreach ($categories as $category) {
                if ($this->addCategoryProduct($input, $category, $selected, $selectedSkus, $selectedFamilies)) {
                    break;
                }
            }
        }

        foreach ($input->rootCategories->shuffle()->take(10) as $category) {
            if ($selected->count() >= 5) {
                break;
            }

            $this->addCategoryProduct($input, $category, $selected, $selectedSkus, $selectedFamilies);
        }

        if ($selected->count() < 5) {
            $selected = $selected->merge(
                $priorityProducts->filter(fn ($product) => ! empty($product->main_image_url))
                    ->reject(fn ($product) => $selectedSkus->contains((string) $product->sku)
                        || $selectedFamilies->contains(trim((string) ($product->fam_99 ?? ''))))
                    ->take(5 - $selected->count())
            )->values();
        }

        if ($selected->count() < 5) {
            $selectedSkus = $selected->pluck('sku')->map(fn ($sku) => (string) $sku);
            $selected = $selected->merge(
                $priorityProducts->filter(fn ($product) => ! empty($product->main_image_url))
                    ->reject(fn ($product) => $selectedSkus->contains((string) $product->sku))
                    ->take(5 - $selected->count())
            )->values();
        }

        return $selected;
    }

    private function addCategoryProduct(
        HomePageInput $input,
        array $category,
        Collection $selected,
        Collection $selectedSkus,
        Collection $selectedFamilies,
    ): bool {
        $familyCode = trim((string) ($category['fam_code'] ?? ''));

        if ($familyCode === '' || $selectedFamilies->contains($familyCode)) {
            return false;
        }

        try {
            $result = $this->catalogRepository->getCategoryProducts(
                $input->store,
                $input->locale,
                $familyCode,
                null,
                null,
                null,
                null,
                null,
                18,
                [],
                'default'
            );
        } catch (Throwable) {
            return false;
        }

        $product = collect($result->items())
            ->filter(fn ($product) => ! empty($product->main_image_url) && ! $selectedSkus->contains((string) $product->sku))
            ->shuffle()
            ->first();

        if (! $product) {
            return false;
        }

        $selected->push($product);
        $selectedSkus->push((string) $product->sku);
        $selectedFamilies->push($familyCode);

        return true;
    }

    private function categoryCards(Collection $categories, string $catalogUrl, array $contextParams): Collection
    {
        return $categories->take(6)->map(function ($category) use ($catalogUrl, $contextParams) {
            $label = $category['label'] ?? $category['code'] ?? 'Categoria';
            $slug = $category['slug'] ?? null;

            return [
                'label' => $label,
                'url' => $slug ? route('storefront.category.show', array_merge(['slug' => $slug], $contextParams)) : $catalogUrl,
                'icon' => $this->categoryIcon($label),
            ];
        });
    }

    private function categoryIcon(string $label): string
    {
        $icons = [
            'notes' => 'fa-regular fa-note-sticky', 'quaderni' => 'fa-regular fa-note-sticky',
            'rubriche' => 'fa-regular fa-address-book', 'pelletteria' => 'fa-solid fa-bag-shopping',
            'lifestyle' => 'fa-solid fa-bag-shopping', 'ufficio' => 'fa-solid fa-briefcase',
            'arredo' => 'fa-solid fa-chair', 'ready' => 'fa-regular fa-circle-check',
            'calendario' => 'fa-regular fa-calendar-days', 'modultime' => 'fa-regular fa-file-lines',
            'modulistica' => 'fa-regular fa-file-lines', 'cartoleria' => 'fa-regular fa-pen-to-square',
            'scrittura' => 'fa-solid fa-pen', 'penne' => 'fa-solid fa-pen',
            'scuola' => 'fa-solid fa-graduation-cap', 'archiviazione' => 'fa-regular fa-folder-open',
            'carta' => 'fa-regular fa-file', 'etichette' => 'fa-solid fa-tags',
            'informatica' => 'fa-solid fa-laptop', 'consumabili' => 'fa-solid fa-print',
        ];
        $normalized = Str::lower($label);

        foreach ($icons as $needle => $icon) {
            if (str_contains($normalized, $needle)) {
                return $icon;
            }
        }

        return 'fa-regular fa-folder';
    }

    private function cardRow(mixed $product, HomePageInput $input, string $contextId): array
    {
        $card = ProductCardViewModel::make(
            $product,
            collect($input->listingCardsByProductSku->get((string) $product->sku, []))
        );

        return ['card' => $card, 'url' => $this->contextUrl($card->productUrl, $contextId)];
    }

    private function featuredCardRow(mixed $product, HomePageInput $input, string $contextId): array
    {
        $row = $this->cardRow($product, $input, $contextId);
        $card = $row['card'];
        $variantStock = $card->selectedVariant['stock_qty'] ?? null;
        $stock = $variantStock !== null ? (float) $variantStock : ($product->stock_qty !== null ? (float) $product->stock_qty : null);

        $row['price_label'] = $card->price === null
            ? 'Prezzo su richiesta'
            : '€ '.number_format((float) $card->price, 3, ',', '.');
        $row['availability'] = match (true) {
            $stock === null => ['class' => 'is-available', 'label' => 'Ordinabile'],
            $stock > 0 => ['class' => 'is-available', 'label' => 'Disponibile'],
            ! (bool) ($product->no_backorder ?? false) => ['class' => 'is-orderable', 'label' => 'Ordinabile'],
            default => ['class' => 'is-unavailable', 'label' => 'Non disponibile'],
        };

        return $row;
    }

    private function isOrderable(mixed $product): bool
    {
        $stock = $product->stock_qty !== null ? (float) $product->stock_qty : null;

        return $stock === null || $stock > 0 || ! (bool) ($product->no_backorder ?? false);
    }

    private function contextUrl(?string $url, string $contextId): ?string
    {
        if (! $url || $contextId === '') {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['agent_context' => $contextId]);
    }
}

<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\ThemeResolver;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
        private CatalogRepository $catalogRepository
    ) {
    }

    public function index(): View
    {
        $store = app()->bound('currentStore') ? app('currentStore') : null;

        abort_unless($store, 404, 'Store corrente non disponibile.');

        $locale = app()->getLocale();

        $categories = $this->catalogRepository->getRootCategories($store, $locale);

        return view($this->themeResolver->view('catalog.index', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'locale' => $locale,
            'categories' => $categories,
        ]);
    }
}
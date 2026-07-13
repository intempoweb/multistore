<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Storefront\StorefrontContext;
use App\Services\Storefront\StorefrontPageTranslationResolver;
use App\Services\Storefront\ThemeResolver;
use Illuminate\View\View;

class BrandPageController extends Controller
{
    public function __construct(
        private StorefrontContext $context,
        private ThemeResolver $themeResolver,
        private StorefrontPageTranslationResolver $pageTranslationResolver,
    ) {}

    public function about(): View
    {
        return $this->show('about');
    }

    public function vision(): View
    {
        return $this->show('vision');
    }

    private function show(string $pageKey): View
    {
        $store = $this->b2cStore();
        $locale = $this->context->locale();
        $storefrontPage = $this->pageTranslationResolver->findByLegacySlug($store, $pageKey, $locale);

        return view($this->themeResolver->view('brand-page', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'storefrontPage' => $storefrontPage,
            'storefrontPageBlocks' => $storefrontPage?->activeBlocks ?? collect(),
            'pageKey' => $pageKey,
        ]);
    }

    private function b2cStore(): Store
    {
        $store = $this->context->store();

        abort_if($store->isB2B(), 404);

        return $store;
    }
}

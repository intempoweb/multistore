<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\StorefrontContext;
use App\Services\Storefront\StorefrontPageTranslationResolver;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        private StorefrontContext $context,
        private StorefrontPageTranslationResolver $pageTranslationResolver,
        private ThemeResolver $themeResolver,
        private CategoryController $categoryController,
    ) {}

    public function show(Request $request, string $slug): View|Response
    {
        $store = $this->context->store();
        $locale = $this->context->locale();
        $page = $this->pageTranslationResolver->findByPublicSlug($store, $slug, $locale);

        if (! $page) {
            return $this->categoryController->show($request, $slug);
        }

        $view = in_array($page->template, ['brand-page', 'blade', 'default', null], true)
            ? $this->themeResolver->view('brand-page', $store)
            : $this->themeResolver->view($page->template ?: 'page', $store);

        return response()
            ->view($view, [
                'store' => $store,
                'storefrontLayout' => $this->themeResolver->layout($store),
                'storefrontPage' => $page,
                'storefrontPageBlocks' => $page->activeBlocks ?? collect(),
                'pageKey' => $page->getRawOriginal('slug') ?: $slug,
                'locale' => $locale,
            ])
            ->header('Cache-Control', 'private, no-store');
    }
}

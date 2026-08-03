<?php

namespace App\View\Composers;

use App\Models\Store;
use App\Services\Storefront\Marketing\StorefrontPopupResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class StorefrontPopupComposer
{
    public function __construct(private readonly StorefrontPopupResolver $resolver)
    {
    }

    public function compose(View $view): void
    {
        $store = app()->bound('currentStore') ? app('currentStore') : null;

        if (!$store instanceof Store) {
            $view->with('marketingPopup', null);

            return;
        }

        try {
            $view->with('marketingPopup', $this->resolver->resolve($store, app()->getLocale()));
        } catch (Throwable $exception) {
            Log::warning('Storefront popup non risolto.', [
                'store_id' => $store->id,
                'locale' => app()->getLocale(),
                'error' => $exception->getMessage(),
            ]);

            $view->with('marketingPopup', null);
        }
    }
}

<?php

namespace App\Services\Newsletters;

use App\Models\Newsletter;

class NewsletterRenderer
{
    public function __construct(
        private NewsletterProductPresenter $presenter,
    ) {
    }

    public function render(Newsletter $newsletter): string
    {
        $newsletter->loadMissing(['store', 'products.translations', 'products.mediaAssets']);

        $products = $newsletter->products
            ->map(fn ($product) => $this->presenter->present($newsletter, $product))
            ->values();

        return view('newsletters.email.campaign', [
            'newsletter' => $newsletter,
            'store' => $newsletter->store,
            'products' => $products,
        ])->render();
    }
}

<?php

namespace App\View\Composers;

use App\Services\Storefront\ViewData\StorefrontChromeDataBuilder;
use Illuminate\View\View;

final class StorefrontChromeComposer
{
    public function __construct(
        private StorefrontChromeDataBuilder $builder,
    ) {}

    public function compose(View $view): void
    {
        $view->with($this->builder->build($view->getData()));
    }
}

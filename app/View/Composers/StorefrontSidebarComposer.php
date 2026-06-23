<?php

namespace App\View\Composers;

use App\Services\Storefront\ViewData\StorefrontSidebarDataBuilder;
use Illuminate\View\View;

final class StorefrontSidebarComposer
{
    public function __construct(
        private StorefrontSidebarDataBuilder $builder,
    ) {}

    public function compose(View $view): void
    {
        $view->with('sidebar', $this->builder->build($view->getData()));
    }
}

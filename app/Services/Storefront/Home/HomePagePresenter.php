<?php

namespace App\Services\Storefront\Home;

use App\Data\Storefront\HomePageInput;
use App\Models\Store;

interface HomePagePresenter
{
    public function supports(Store $store): bool;

    public function present(HomePageInput $input): array;
}

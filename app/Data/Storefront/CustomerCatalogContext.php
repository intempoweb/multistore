<?php

namespace App\Data\Storefront;

final readonly class CustomerCatalogContext
{
    public function __construct(
        public ?int $tipocf,
        public ?int $clifor,
    ) {}
}

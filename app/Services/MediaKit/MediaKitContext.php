<?php

namespace App\Services\MediaKit;

use App\Models\Store;

final readonly class MediaKitContext
{
    public function __construct(
        public Store $store,
        public ?int $customerId,
        public ?string $actorType,
        public ?int $actorId,
        public ?int $tipoCf = null,
        public ?int $clifor = null,
        public bool $applyCustomerAcl = true,
    ) {
    }

    public function ditta(): int
    {
        return (int) $this->store->ditta_cg18;
    }

    public function siteType(): int
    {
        return (int) $this->store->erp_site_code;
    }
}

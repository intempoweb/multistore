<?php

namespace Tests\Unit\Storefront;

use App\Models\GroupDescription;
use App\Models\Product;
use App\Models\Store;
use App\Repositories\Storefront\CatalogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogRepositoryCategoryStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_categories_ignore_inactive_erp_descriptions(): void
    {
        $store = $this->createReadyStore();

        $this->createFamilyDescription($store, 'A', 'ACCESSORI', true);
        $this->createFamilyDescription($store, 'E', 'READY', false);

        $this->createSimpleProduct($store, 'ACCESSORI-1', 'A');
        $this->createSimpleProduct($store, 'READY-1', 'E');

        $categories = app(CatalogRepository::class)->getRootCategories($store, 'it');

        $this->assertSame(['A'], $categories->pluck('fam_code')->all());
    }

    public function test_children_categories_ignore_inactive_erp_descriptions(): void
    {
        $store = $this->createReadyStore();

        $this->createFamilyDescription($store, 'A', 'ACCESSORI', true);
        $this->createSubfamilyDescription($store, 'A', '01', 'ATTIVA', true);
        $this->createSubfamilyDescription($store, 'A', '02', 'DISATTIVA', false);

        $this->createSimpleProduct($store, 'ACTIVE-CHILD-1', 'A', '01');
        $this->createSimpleProduct($store, 'INACTIVE-CHILD-1', 'A', '02');

        $categories = app(CatalogRepository::class)->getChildrenCategories($store, 'it', 'A');

        $this->assertSame(['01'], $categories->pluck('sfam_code')->all());
    }

    private function createReadyStore(): Store
    {
        return Store::query()->create([
            'ditta_cg18' => 1,
            'erp_site_code' => 7,
            'company_code' => 'INTEMPO',
            'site_code' => 'READY',
            'domain' => 'ready.test',
            'name' => 'READY',
            'is_b2b' => false,
            'theme' => 'ready',
            'default_locale' => 'it',
            'supported_locales' => ['it'],
            'is_active' => true,
        ]);
    }

    private function createFamilyDescription(Store $store, string $fam, string $description, bool $active): void
    {
        GroupDescription::query()->create([
            'ditta_cg18' => $store->ditta_cg18,
            'site_type' => $store->erp_site_code,
            'locale' => 'it',
            'fam_code' => $fam,
            'description' => $description,
            'is_active' => $active,
        ]);
    }

    private function createSubfamilyDescription(Store $store, string $fam, string $sfam, string $description, bool $active): void
    {
        GroupDescription::query()->create([
            'ditta_cg18' => $store->ditta_cg18,
            'site_type' => $store->erp_site_code,
            'locale' => 'it',
            'fam_code' => $fam,
            'sfam_code' => $sfam,
            'description' => $description,
            'is_active' => $active,
        ]);
    }

    private function createSimpleProduct(Store $store, string $sku, string $fam, ?string $sfam = null): void
    {
        Product::query()->create([
            'ditta_cg18' => $store->ditta_cg18,
            'site_type' => $store->erp_site_code,
            'sku' => $sku,
            'type' => 'simple',
            'is_active' => true,
            'stock_qty' => 10,
            'fam_99' => $fam,
            'sfam_99' => $sfam,
        ]);
    }
}

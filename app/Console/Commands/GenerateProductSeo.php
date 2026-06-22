<?php

namespace App\Console\Commands;

use App\Models\ProductTranslation;
use App\Services\Storefront\Seo\ProductSeoGenerator;
use Illuminate\Console\Command;

class GenerateProductSeo extends Command
{
    protected $signature = 'storefront:generate-product-seo
        {--store= : ID dello store da elaborare}
        {--force : Rigenera anche i campi SEO già valorizzati}';

    protected $description = 'Genera automaticamente title e description SEO delle traduzioni prodotto.';

    public function handle(ProductSeoGenerator $generator): int
    {
        $query = ProductTranslation::query()
            ->with('product')
            ->when(!$this->option('force'), fn ($query) => $query
                ->where(fn ($seo) => $seo->whereNull('seo_title')->orWhereNull('seo_description')))
            ->when($this->option('store'), function ($query, $storeId) {
                $query->whereHas('product', function ($product) use ($storeId) {
                    $product->whereExists(function ($store) use ($storeId) {
                        $store->selectRaw('1')
                            ->from('stores')
                            ->whereColumn('stores.ditta_cg18', 'products.ditta_cg18')
                            ->whereColumn('stores.erp_site_code', 'products.site_type')
                            ->where('stores.id', (int) $storeId);
                    });
                });
            });

        $total = (clone $query)->count();
        $bar = $this->output->createProgressBar($total);

        $query->orderBy('id')->chunkById(500, function ($translations) use ($generator, $bar) {
            foreach ($translations as $translation) {
                if (!$translation->product) {
                    $bar->advance();
                    continue;
                }

                $seo = $generator->generate(
                    $translation->product,
                    $translation->locale,
                    $translation->name,
                    $translation->short_description ?: $translation->description
                );
                $translation->forceFill($seo)->save();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("SEO prodotto generata per {$total} traduzioni.");

        return self::SUCCESS;
    }
}

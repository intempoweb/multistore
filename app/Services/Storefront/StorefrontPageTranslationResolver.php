<?php

namespace App\Services\Storefront;

use App\Models\Store;
use App\Models\StorefrontPage;

class StorefrontPageTranslationResolver
{
    public function apply(StorefrontPage $page, Store $store, ?string $locale): StorefrontPage
    {
        if ($store->isB2B()) {
            return $page;
        }

        return $page->applyTranslation($locale);
    }

    public function findByLegacySlug(Store $store, string $legacySlug, ?string $locale): ?StorefrontPage
    {
        $page = StorefrontPage::query()
            ->with(['translations', 'activeBlocks.translations', 'activeBlocks.activeMedia'])
            ->where('store_id', $store->id)
            ->where('slug', $legacySlug)
            ->active()
            ->first();

        return $page ? $this->apply($page, $store, $locale) : null;
    }

    public function findByPublicSlug(Store $store, string $slug, ?string $locale): ?StorefrontPage
    {
        $slug = trim($slug, '/');

        if ($slug === '') {
            return null;
        }

        if ($store->isB2B()) {
            return StorefrontPage::query()
                ->with(['activeBlocks.activeMedia'])
                ->where('store_id', $store->id)
                ->where('slug', $slug)
                ->active()
                ->first();
        }

        $page = StorefrontPage::query()
            ->with(['translations', 'activeBlocks.translations', 'activeBlocks.activeMedia'])
            ->where('store_id', $store->id)
            ->whereHas('translations', function ($query) use ($store, $slug, $locale) {
                $query->where('store_id', $store->id)
                    ->where('slug', $slug)
                    ->when($locale, fn ($q) => $q->where('locale', $locale));
            })
            ->active()
            ->first();

        if (! $page) {
            $page = StorefrontPage::query()
                ->with(['translations', 'activeBlocks.translations', 'activeBlocks.activeMedia'])
                ->where('store_id', $store->id)
                ->where('slug', $slug)
                ->active()
                ->first();
        }

        return $page ? $this->apply($page, $store, $locale) : null;
    }
}

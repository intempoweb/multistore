<?php

namespace App\Services\Storefront\Home;

use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageBlock;
use App\Models\StorefrontPageBlockTranslation;
use Illuminate\Support\Arr;
use RuntimeException;

final class HomeBlockDefaults
{
    private const EDITABLE_FIELDS = [
        'type', 'sort_order', 'title', 'subtitle', 'content',
        'image_path', 'mobile_image_path', 'video_path',
        'button_label', 'button_url', 'button_new_tab', 'settings',
    ];

    public function ensure(StorefrontPage $page, Store $store): void
    {
        $defaults = $this->forStore($store);
        $legacyCiak = collect($this->load('b2c', 'ciak'))->keyBy('name');
        $isCiak = $this->normalizeTheme($store->theme) === 'ciak';

        foreach ($defaults as $definition) {
            $name = trim((string) ($definition['name'] ?? ''));

            if ($name === '') {
                throw new RuntimeException('Ogni blocco home predefinito deve avere un nome.');
            }

            $attributes = $this->attributes($definition);
            $block = StorefrontPageBlock::query()->firstOrCreate(
                ['storefront_page_id' => $page->id, 'name' => $name],
                $attributes + ['is_active' => true]
            );

            if (! $block->wasRecentlyCreated && ! $isCiak) {
                $legacy = $legacyCiak->get($name);
                $this->replaceOnlyLegacyCiakValues($block, $attributes, is_array($legacy) ? $legacy : []);
            }

            if ($block->wasRecentlyCreated) {
                $this->ensureItalianTranslation($block, $definition);
            } elseif (! $isCiak) {
                $legacy = $legacyCiak->get($name);
                $this->replaceOnlyLegacyTranslationValues($block, $definition, is_array($legacy) ? $legacy : []);
            }
        }
    }


    /** @return array<int, string> */
    public function namesForStore(Store $store): array
    {
        return collect($this->forStore($store))
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function forStore(Store $store): array
    {
        $channel = $store->channel();
        $theme = $this->normalizeTheme($store->theme);
        $defaults = $this->load($channel, $theme);

        if ($defaults !== []) {
            return $defaults;
        }

        return $this->load($channel, 'default');
    }

    /** @return array<int, array<string, mixed>> */
    private function load(string $channel, string $theme): array
    {
        $path = resource_path("views/storefront/themes/{$channel}/{$theme}/defaults/home.php");

        if (! is_file($path)) {
            return [];
        }

        $blocks = require $path;

        if (! is_array($blocks)) {
            throw new RuntimeException("Il file {$path} deve restituire un array.");
        }

        return array_values(array_filter($blocks, 'is_array'));
    }

    /** @return array<string, mixed> */
    private function attributes(array $definition): array
    {
        $attributes = Arr::only($definition, self::EDITABLE_FIELDS);
        $attributes['type'] = $attributes['type'] ?? 'content';
        $attributes['sort_order'] = (int) ($attributes['sort_order'] ?? 0);
        $attributes['button_new_tab'] = (bool) ($attributes['button_new_tab'] ?? false);
        $attributes['settings'] = is_array($attributes['settings'] ?? null) ? $attributes['settings'] : [];

        return $attributes;
    }

    private function replaceOnlyLegacyCiakValues(StorefrontPageBlock $block, array $new, array $legacy): void
    {
        $updates = [];

        foreach (['type', 'sort_order', 'title', 'subtitle', 'content', 'button_label', 'button_url'] as $field) {
            $current = $block->getAttribute($field);
            $legacyValue = $legacy[$field] ?? null;

            if ($current === $legacyValue && array_key_exists($field, $new)) {
                $updates[$field] = $new[$field];
            }
        }

        if (empty($block->settings) && ! empty($new['settings'])) {
            $updates['settings'] = $new['settings'];
        }

        if ($updates !== []) {
            $block->forceFill($updates)->save();
        }
    }

    private function ensureItalianTranslation(StorefrontPageBlock $block, array $definition): void
    {
        StorefrontPageBlockTranslation::query()->firstOrCreate(
            ['storefront_page_block_id' => $block->id, 'locale' => 'it'],
            Arr::only($definition, ['title', 'subtitle', 'content', 'button_label'])
        );
    }

    private function replaceOnlyLegacyTranslationValues(StorefrontPageBlock $block, array $new, array $legacy): void
    {
        $translation = StorefrontPageBlockTranslation::query()
            ->where('storefront_page_block_id', $block->id)
            ->where('locale', 'it')
            ->first();

        if (! $translation) {
            $this->ensureItalianTranslation($block, $new);
            return;
        }

        $updates = [];
        foreach (['title', 'subtitle', 'content', 'button_label'] as $field) {
            if ($translation->getAttribute($field) === ($legacy[$field] ?? null) && array_key_exists($field, $new)) {
                $updates[$field] = $new[$field];
            }
        }

        if ($updates !== []) {
            $translation->forceFill($updates)->save();
        }
    }

    private function normalizeTheme(mixed $theme): string
    {
        return strtolower(trim((string) $theme));
    }
}

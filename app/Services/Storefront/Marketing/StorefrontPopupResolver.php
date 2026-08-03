<?php

namespace App\Services\Storefront\Marketing;

use App\Models\Store;
use App\Models\StorefrontPopup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StorefrontPopupResolver
{
    public function __construct(private readonly Request $request)
    {
    }

    public function resolve(Store $store, string $locale): ?array
    {
        $popup = StorefrontPopup::query()
            ->with('translations')
            ->forStore($store)
            ->active()
            ->valid()
            ->ordered()
            ->get()
            ->first(fn (StorefrontPopup $popup) => $this->matchesCurrentRequest($popup));

        if (!$popup) {
            return null;
        }

        $localized = $popup->localized($locale, $store->defaultLocale('it'));

        if (!filled($localized['title']) && !filled($localized['body']) && !filled($popup->image_url)) {
            return null;
        }

        $title = $localized['title'];
        $subtitle = $localized['subtitle'];
        $body = $localized['body'];
        $ctaLabel = $localized['cta_label'];

        return [
            'id' => $popup->id,
            'key' => implode(':', [
                'storefront-popup',
                $store->id,
                $popup->id,
                optional($popup->updated_at)->timestamp ?: time(),
            ]),
            'frequency' => $popup->frequency,
            'delay_ms' => max(0, (int) $popup->delay_ms),
            'title' => $title,
            'title_text' => strip_tags((string) $title),
            'title_html' => $this->sanitizeHtml($title, ['strong', 'b', 'em', 'i', 'u', 'br']),
            'subtitle' => $subtitle,
            'subtitle_html' => $this->sanitizeHtml($subtitle, ['strong', 'b', 'em', 'i', 'u', 'br']),
            'body' => $body,
            'body_html' => $this->sanitizeHtml($body, ['p', 'strong', 'b', 'em', 'i', 'u', 'br', 'ul', 'ol', 'li']),
            'cta_label' => $ctaLabel,
            'cta_label_text' => strip_tags((string) $ctaLabel),
            'cta_url' => $this->normalizeUrl($popup->cta_url),
            'open_in_new_tab' => (bool) $popup->open_in_new_tab,
            'image_url' => $this->normalizeMediaUrl($popup->image_url),
            'background_color' => $popup->background_color ?: '#ffffff',
            'text_color' => $popup->text_color ?: '#111111',
            'button_background_color' => $popup->button_background_color ?: '#111111',
            'button_text_color' => $popup->button_text_color ?: '#ffffff',
        ];
    }

    private function matchesCurrentRequest(StorefrontPopup $popup): bool
    {
        if ($popup->display_scope === StorefrontPopup::SCOPE_ALL) {
            return true;
        }

        $routeName = (string) ($this->request->route()?->getName() ?? '');
        $path = trim($this->request->path(), '/');

        return match ($popup->display_scope) {
            StorefrontPopup::SCOPE_HOME => $routeName === 'storefront.home' || $path === '' || preg_match('/^[a-z]{2}$/', $path) === 1,
            StorefrontPopup::SCOPE_CATALOG => Str::startsWith($routeName, ['storefront.catalog.', 'storefront.search.']),
            StorefrontPopup::SCOPE_CATEGORY => Str::startsWith($routeName, 'storefront.category.'),
            StorefrontPopup::SCOPE_PRODUCT => Str::startsWith($routeName, 'storefront.product.'),
            StorefrontPopup::SCOPE_CART => Str::startsWith($routeName, 'storefront.cart.'),
            StorefrontPopup::SCOPE_CHECKOUT => Str::startsWith($routeName, 'storefront.checkout.'),
            StorefrontPopup::SCOPE_ACCOUNT => Str::startsWith($routeName, ['storefront.account.', 'storefront.customer.', 'storefront.profile.']),
            default => false,
        };
    }

    private function normalizeMediaUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', '//'])) {
            return $value;
        }

        return media_url($value);
    }

    private function normalizeUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $value;
    }

    private function sanitizeHtml(?string $value, array $allowedTags): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $allowed = '<' . implode('><', $allowedTags) . '>';
        $clean = strip_tags($value, $allowed);
        $clean = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $clean) ?? '';
        $clean = preg_replace('/<\/br>/i', '', $clean) ?? '';

        if ($clean === strip_tags($clean)) {
            return nl2br(e($value));
        }

        return $clean;
    }
}

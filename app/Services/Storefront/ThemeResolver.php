<?php

namespace App\Services\Storefront;

use App\Models\Store;
use Illuminate\Support\Facades\View;
use RuntimeException;

class ThemeResolver
{
    public function view(string $view, Store $store): string
    {
        return $this->resolveExistingView(
            $this->viewCandidates($view, $store),
            'Nessuna view storefront trovata.'
        );
    }

    public function layout(Store $store): string
    {
        return $this->resolveExistingView(
            $this->layoutCandidates($store),
            'Nessun layout storefront trovato.'
        );
    }

    public function authLayout(Store $store): string
    {
        return $this->resolveExistingView(
            $this->authLayoutCandidates($store),
            'Nessun layout auth storefront trovato.'
        );
    }

    public function resolveArea(Store $store): string
    {
        return $store->is_b2b ? 'b2b' : 'b2c';
    }

    public function resolveTheme(Store $store): string
    {
        $theme = trim((string) ($store->theme ?? ''));

        return $theme !== '' ? $theme : 'default';
    }

    protected function viewCandidates(string $view, Store $store): array
    {
        $view = trim($view, '. ');
        $area = $this->resolveArea($store);
        $theme = $this->resolveTheme($store);

        return array_values(array_unique([
            "storefront.themes.{$area}.{$theme}.overrides.{$view}",
            "storefront.themes.{$area}.default.overrides.{$view}",
            "storefront.base.pages.{$view}",
        ]));
    }

    protected function layoutCandidates(Store $store): array
    {
        $area = $this->resolveArea($store);
        $theme = $this->resolveTheme($store);

        return array_values(array_unique([
            "storefront.themes.{$area}.{$theme}.layout",
            "storefront.themes.{$area}.default.layout",
            'layouts.storefront',
            'layouts.frontend',
        ]));
    }

    protected function authLayoutCandidates(Store $store): array
    {
        $area = $this->resolveArea($store);
        $theme = $this->resolveTheme($store);

        return array_values(array_unique([
            "storefront.themes.{$area}.{$theme}.auth-layout",
            "storefront.themes.{$area}.default.auth-layout",
            'storefront.base.layouts.auth',
            'layouts.guest',
        ]));
    }

    protected function resolveExistingView(array $candidates, string $message): string
    {
        foreach ($candidates as $candidate) {
            if (View::exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            $message . ' Candidati provati: ' . implode(', ', $candidates)
        );
    }
}

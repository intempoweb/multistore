@include('storefront.base.partials.footer', [
    'store' => $store ?? null,
    'locale' => $locale ?? app()->getLocale(),
    'navigationTree' => $navigationTree ?? collect(),
    'contextParams' => $contextParams ?? [],
    'agentContextId' => $agentContextId ?? '',
])

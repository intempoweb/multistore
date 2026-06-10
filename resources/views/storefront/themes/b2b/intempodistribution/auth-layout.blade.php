<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $store?->name ?? config('app.name', 'Store'))</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">

    @stack('styles')

    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/auth.js') }}" defer></script>
    @stack('head-scripts')
</head>

<body
    class="storefront-auth-page"
    data-storefront-layout="b2b-intempodistribution-auth"
    data-storefront-site-type="b2b"
>
    @php
        $authBlocks = collect($storefrontPageBlocks ?? [])
            ->filter(fn ($block) => (bool) ($block->is_active ?? true))
            ->sortBy(fn ($block) => (int) ($block->sort_order ?? 0))
            ->values();

        $backgroundBlocks = $authBlocks
            ->filter(fn ($block) => ($block->type ?? null) === 'brand_grid')
            ->values();

        if ($backgroundBlocks->isEmpty()) {
            $backgroundBlocks = collect(range(1, 8))->map(function ($i) {
                return (object) [
                    'is_active' => true,
                    'sort_order' => $i,
                    'type' => 'brand_grid',
                    'title' => 'Brand ' . $i,
                    'image_path' => 'https://picsum.photos/seed/intempo-login-' . $i . '/900/700',
                    'button_url' => null,
                    'button_label' => null,
                    'button_new_tab' => false,
                    'settings' => [],
                ];
            })->values();
        }
    @endphp

    <div class="storefront-auth-shell">
        <div class="storefront-auth-background-grid" aria-hidden="true">
            @foreach($backgroundBlocks->take(8) as $block)
                @php
                    $settings = is_array($block->settings ?? null) ? $block->settings : [];
                    $imagePath = $block->image_path ?? data_get($settings, 'image_path') ?? data_get($settings, 'image');
                    $imageUrl = media_url($imagePath);

                    $title = $block->title ?? data_get($settings, 'title');
                    $linkUrl = $block->button_url ?? data_get($settings, 'link_url') ?? data_get($settings, 'url');
                    $linkLabel = $block->button_label ?? data_get($settings, 'link_label') ?? data_get($settings, 'label');
                    $newTab = (bool) ($block->button_new_tab ?? data_get($settings, 'new_tab', false));
                @endphp

                <div
                    class="storefront-auth-background-card"
                    @if($imageUrl)
                        style="background-image: url('{{ $imageUrl }}');"
                    @endif
                >
                    <div class="storefront-auth-background-card-content">
                        @if($title)
                            <div class="storefront-auth-background-card-title">
                                {{ $title }}
                            </div>
                        @endif

                        @if($linkUrl)
                            <div class="storefront-auth-background-card-link">
                                <a
                                    href="{{ $linkUrl }}"
                                    @if($newTab) target="_blank" rel="noopener" @endif
                                >
                                    {{ $linkLabel ?: $linkUrl }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <main class="storefront-auth-main">
            <div class="storefront-auth-content">
                @yield('content')
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>

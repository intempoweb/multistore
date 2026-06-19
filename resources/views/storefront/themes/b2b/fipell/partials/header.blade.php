@php
    use App\Repositories\Storefront\CatalogRepository;
    use Illuminate\Support\Facades\Cache;

    $store = $store ?? (app()->bound('currentStore') ? app('currentStore') : null);
    $locale = $locale ?? app()->getLocale();
    $storeName = $store->name ?? config('app.name', 'Store');
    $storeLogo = $store?->logo_url ? media_url($store->logo_url) : null;

    $cartCount = (float) ($cartCount ?? 0);
    $searchQuery = trim((string) request()->query('q', ''));
    $agentContextId = $agentContextId ?? (string) request('agent_context', '');
    $contextParams = $contextParams ?? ($agentContextId !== '' ? ['agent_context' => $agentContextId] : []);

    $navigationTree = collect($navigationTree ?? []);

    if ($navigationTree->isEmpty() && $store) {
        $navigationCacheKey = implode(':', [
            'storefront-navigation-tree',
            (int) ($store->id ?? 0),
            (int) ($store->ditta_cg18 ?? 0),
            (int) ($store->erp_site_code ?? 0),
            $locale,
        ]);

        try {
            $navigationTree = Cache::remember($navigationCacheKey, now()->addMinutes(30), function () use ($store, $locale) {
                return app(CatalogRepository::class)->getNavigationTree($store, $locale)->all();
            });
        } catch (Throwable $exception) {
            $navigationTree = [];
        }

        $navigationTree = collect($navigationTree);
    }

    $availableLocales = collect($availableLocales ?? ($store->locales ?? $store->available_locales ?? []))
        ->map(function ($localeItem, $key) {
            if (is_array($localeItem)) {
                $code = trim((string) ($localeItem['code'] ?? $key));

                return [
                    'code' => $code,
                    'label' => (string) ($localeItem['label'] ?? strtoupper($code)),
                    'url' => $localeItem['url'] ?? null,
                ];
            }

            $code = trim((string) $localeItem);

            return [
                'code' => $code,
                'label' => strtoupper($code),
                'url' => null,
            ];
        })
        ->filter(fn (array $localeItem) => ($localeItem['code'] ?? '') !== '')
        ->values();
@endphp

<header class="storefront-header fipell-header sticky-top" data-fipell-header>
    <div class="fipell-header-main">
        <div class="container-fluid fipell-shell">
            <div class="fipell-header-grid">

                <div class="fipell-header-left">
                    <button
                        type="button"
                        class="btn fipell-icon-btn fipell-menu-button-primary"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#fipellCategoryMenu"
                        aria-controls="fipellCategoryMenu"
                        aria-label="Apri menu categorie"
                    >
                        <i class="fa-solid fa-bars" aria-hidden="true"></i>
                        <span>Menu</span>
                    </button>

                    <a class="fipell-brand fipell-brand-scroll" href="{{ route('storefront.home', $contextParams) }}" aria-label="{{ $storeName }}" data-fipell-scroll-brand>
                        @if($storeLogo)
                            <img src="{{ $storeLogo }}" alt="{{ $storeName }}" class="fipell-brand-logo" loading="eager" decoding="async">
                        @else
                            <span class="fipell-logo-fallback">{{ mb_substr($storeName, 0, 1) }}</span>
                            <span class="fipell-brand-name">{{ $storeName }}</span>
                        @endif
                    </a>
                </div>

                @if(Route::has('storefront.search.index'))
                    <form
                        method="GET"
                        action="{{ route('storefront.search.index', $contextParams) }}"
                        class="fipell-header-search d-none d-lg-block"
                        role="search"
                        data-storefront-search-form
                        data-search-url="{{ route('storefront.search.index', $contextParams) }}"
                        data-search-min-chars="2"
                        @if(Route::has('storefront.search.suggest'))
                            data-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
                            data-search-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
                        @endif
                        @if(Route::has('storefront.cart.add'))
                            data-cart-add-url="{{ route('storefront.cart.add', $contextParams) }}"
                        @endif
                    >
                        @if($agentContextId !== '')
                            <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                        @endif

                        <label for="fipell-header-search" class="visually-hidden">Cerca prodotti</label>

                        <div class="storefront-search-shell" data-storefront-search-shell>
                            <div class="storefront-search-control fipell-search-control">
                                <i class="fa-solid fa-magnifying-glass storefront-search-icon" aria-hidden="true"></i>

                                <input
                                    type="search"
                                    name="q"
                                    id="fipell-header-search"
                                    class="form-control storefront-search-input"
                                    value="{{ $searchQuery }}"
                                    placeholder="Cerca prodotti, SKU o categorie..."
                                    autocomplete="off"
                                    autocapitalize="off"
                                    spellcheck="false"
                                    aria-label="Cerca prodotti"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="fipell-search-suggestions"
                                    data-storefront-search-input
                                    data-search-input
                                >

                                <button
                                    type="button"
                                    class="btn storefront-search-clear {{ $searchQuery !== '' ? '' : 'd-none' }}"
                                    aria-label="Svuota ricerca"
                                    data-storefront-search-clear
                                    data-search-clear
                                >
                                    <i class="fa-solid fa-xmark"></i>
                                </button>

                                <button type="submit" class="btn storefront-search-submit fipell-search-submit" aria-label="Cerca">
                                    <span>Cerca</span>
                                </button>
                            </div>

                            <div
                                id="fipell-search-suggestions"
                                class="storefront-search-suggestions d-none"
                                role="listbox"
                                aria-label="Suggerimenti ricerca"
                                data-storefront-search-suggestions
                                data-search-suggestions
                            >
                                <div class="storefront-search-suggestions-inner" data-storefront-search-suggestions-inner data-search-suggestions-inner></div>
                            </div>
                        </div>
                    </form>
                @endif

                <div class="fipell-header-actions">
                    @if($availableLocales->count() > 1)
                        <div class="dropdown d-none d-md-block">
                            <button class="btn fipell-action-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ strtoupper($locale) }}
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end fipell-dropdown">
                                @foreach($availableLocales as $localeItem)
                                    @php
                                        $localeCode = (string) ($localeItem['code'] ?? '');
                                        $localeLabel = (string) ($localeItem['label'] ?? strtoupper($localeCode));
                                        $localeUrl = $localeItem['url'] ?? null;
                                    @endphp

                                    <li>
                                        <a
                                            class="dropdown-item {{ $localeCode === $locale ? 'active' : '' }}"
                                            href="{{ $localeUrl ?: request()->fullUrlWithQuery(array_merge(['locale' => $localeCode], $contextParams)) }}"
                                        >
                                            {{ $localeLabel }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(($store?->is_b2b ?? false) && auth('customer')->check() && Route::has('storefront.cart.import'))
                        <button
                            type="button"
                            class="btn fipell-action-btn fipell-quick-order d-none d-md-inline-flex"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#storefrontCartImport"
                            aria-controls="storefrontCartImport"
                            aria-label="Acquisto rapido"
                        >
                            <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                            <span>Acquisto rapido</span>
                        </button>
                    @endif

                    <button
                        type="button"
                        class="btn fipell-cart-btn position-relative"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#storefrontMinicart"
                        aria-controls="storefrontMinicart"
                        data-minicart-trigger
                    >
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span class="d-none d-sm-inline">Carrello</span>

                        <span
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill fipell-cart-badge {{ $cartCount > 0 ? '' : 'd-none' }}"
                            data-minicart-count-badge
                        >
                            {{ number_format($cartCount, 0, ',', '.') }}
                        </span>
                    </button>
                </div>

            </div>
        </div>
    </div>

    @if(Route::has('storefront.search.index'))
        <div class="fipell-mobile-search d-lg-none">
            <div class="container-fluid fipell-shell py-2">
                <form
                    method="GET"
                    action="{{ route('storefront.search.index', $contextParams) }}"
                    class="storefront-search-form"
                    role="search"
                    data-storefront-search-form
                    data-search-url="{{ route('storefront.search.index', $contextParams) }}"
                    data-search-min-chars="2"
                    @if(Route::has('storefront.search.suggest'))
                        data-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
                        data-search-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
                    @endif
                    @if(Route::has('storefront.cart.add'))
                        data-cart-add-url="{{ route('storefront.cart.add', $contextParams) }}"
                    @endif
                >
                    @if($agentContextId !== '')
                        <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                    @endif

                    <label for="fipell-mobile-search-input" class="visually-hidden">Cerca prodotti</label>

                    <div class="storefront-search-shell" data-storefront-search-shell>
                        <div class="storefront-search-control fipell-search-control">
                            <i class="fa-solid fa-magnifying-glass storefront-search-icon"></i>

                            <input
                                type="search"
                                name="q"
                                id="fipell-mobile-search-input"
                                class="form-control storefront-search-input"
                                value="{{ $searchQuery }}"
                                placeholder="Cerca prodotti..."
                                autocomplete="off"
                                data-storefront-search-input
                                data-search-input
                            >

                            <button type="submit" class="btn storefront-search-submit fipell-search-submit" aria-label="Cerca">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>
                        </div>

                        <div class="storefront-search-suggestions d-none" role="listbox" aria-label="Suggerimenti ricerca" data-storefront-search-suggestions data-search-suggestions>
                            <div class="storefront-search-suggestions-inner" data-storefront-search-suggestions-inner data-search-suggestions-inner></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</header>

<script>
    (() => {
        const header = document.querySelector('[data-fipell-header]');

        if (!header) {
            return;
        }

        const scrollThreshold = 72;
        let ticking = false;

        const updateHeaderState = () => {
            header.classList.toggle('is-scrolled', window.scrollY > scrollThreshold);
            ticking = false;
        };

        updateHeaderState();

        window.addEventListener('scroll', () => {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(updateHeaderState);
        }, { passive: true });
    })();
</script>

<div
    class="offcanvas offcanvas-start fipell-category-offcanvas"
    tabindex="-1"
    id="fipellCategoryMenu"
    aria-labelledby="fipellCategoryMenuLabel"
>
    <div class="offcanvas-header border-bottom">
        <div>
            <div class="small text-muted text-uppercase fw-bold">Fipell</div>
            <h5 class="offcanvas-title mb-0" id="fipellCategoryMenuLabel">Menu</h5>
        </div>

        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Chiudi"></button>
    </div>

    <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush fipell-category-list">
            @if(Route::has('storefront.catalog.index'))
                <a
                    href="{{ route('storefront.home', $contextParams) }}"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ request()->routeIs('storefront.home') ? 'active' : '' }}"
                >
                    <span class="fw-semibold">Tutto il catalogo</span>
                </a>
            @endif

            @foreach($navigationTree as $firstLevel)
                @php
                    $firstChildren = collect($firstLevel['children'] ?? []);
                    $firstLabel = $firstLevel['label'] ?? $firstLevel['code'] ?? 'Categoria';
                    $firstSlug = $firstLevel['slug'] ?? null;
                    $collapseId = 'fipell-category-' . md5((string) $firstSlug . '-' . $loop->index);
                @endphp

                @if($firstSlug)
                    <div class="fipell-category-block">
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <a
                                href="{{ route('storefront.category.show', array_merge(['slug' => $firstSlug], $contextParams)) }}"
                                class="fipell-category-link flex-grow-1 text-decoration-none {{ request()->is('category/' . $firstSlug . '*') ? 'active' : '' }}"
                            >
                                {{ $firstLabel }}
                            </a>

                            @if($firstChildren->isNotEmpty())
                                <button
                                    type="button"
                                    class="btn btn-sm btn-light fipell-category-toggle"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#{{ $collapseId }}"
                                    aria-expanded="false"
                                    aria-controls="{{ $collapseId }}"
                                >
                                    <i class="fa-solid fa-chevron-down"></i>
                                </button>
                            @endif
                        </div>

                        @if($firstChildren->isNotEmpty())
                            <div class="collapse" id="{{ $collapseId }}">
                                <div class="fipell-category-children">
                                    @foreach($firstChildren as $secondLevel)
                                        @php
                                            $secondChildren = collect($secondLevel['children'] ?? []);
                                            $secondLabel = $secondLevel['label'] ?? $secondLevel['code'] ?? 'Categoria';
                                            $secondSlug = $secondLevel['slug'] ?? null;
                                        @endphp

                                        @if($secondSlug)
                                            <a
                                                href="{{ route('storefront.category.show', array_merge(['slug' => $secondSlug], $contextParams)) }}"
                                                class="fipell-category-child text-decoration-none"
                                            >
                                                {{ $secondLabel }}
                                            </a>
                                        @else
                                            <div class="fipell-category-child text-muted">
                                                {{ $secondLabel }}
                                            </div>
                                        @endif

                                        @if($secondChildren->isNotEmpty())
                                            <div class="fipell-category-grandchildren">
                                                @foreach($secondChildren as $thirdLevel)
                                                    @php
                                                        $thirdLabel = $thirdLevel['label'] ?? $thirdLevel['code'] ?? 'Categoria';
                                                        $thirdSlug = $thirdLevel['slug'] ?? null;
                                                    @endphp

                                                    @if($thirdSlug)
                                                        <a
                                                            href="{{ route('storefront.category.show', array_merge(['slug' => $thirdSlug], $contextParams)) }}"
                                                            class="fipell-category-grandchild text-decoration-none"
                                                        >
                                                            {{ $thirdLabel }}
                                                        </a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>

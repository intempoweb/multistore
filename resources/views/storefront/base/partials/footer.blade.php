@php
    use App\Repositories\Storefront\CatalogRepository;

    $store = $store ?? (app()->bound('currentStore') ? app('currentStore') : null);
    $locale = $locale ?? app()->getLocale();
    $storeName = $store->name ?? config('app.name', 'Store');
    $storeLogo = $store?->logo_url;

    $companyName = $store->company_name ?? $store->ragione_sociale ?? $storeName;
    $companyAddress = $store->address ?? $store->company_address ?? null;
    $companyVat = $store->vat_number ?? $store->piva ?? $store->partita_iva ?? null;
    $companyEmail = $store->email ?? $store->company_email ?? null;
    $companyPhone = $store->phone ?? $store->company_phone ?? null;

    $navigationTree = collect($navigationTree ?? []);

    if ($navigationTree->isEmpty() && $store) {
        try {
            $navigationTree = app(CatalogRepository::class)->getNavigationTree($store, $locale);
        } catch (Throwable $exception) {
            $navigationTree = collect();
        }
    }

    $footerSocials = collect($footerSocials ?? ($store->social_links ?? $store->socials ?? []))
        ->map(function ($item, $key) {
            if (is_array($item)) {
                return [
                    'label' => (string) ($item['label'] ?? $key),
                    'url' => $item['url'] ?? null,
                    'icon' => (string) ($item['icon'] ?? ''),
                ];
            }

            return [
                'label' => is_string($key) ? $key : 'Social',
                'url' => is_string($item) ? $item : null,
                'icon' => '',
            ];
        })
        ->filter(fn (array $item) => filled($item['url'] ?? null))
        ->values();

    $socialIcon = function (string $label, string $icon = ''): string {
        if ($icon !== '') {
            return $icon;
        }

        return match (strtolower($label)) {
            'facebook' => 'fa-brands fa-facebook-f',
            'instagram' => 'fa-brands fa-instagram',
            'linkedin' => 'fa-brands fa-linkedin-in',
            'youtube' => 'fa-brands fa-youtube',
            'tiktok' => 'fa-brands fa-tiktok',
            'x', 'twitter' => 'fa-brands fa-x-twitter',
            default => 'fa-solid fa-link',
        };
    };
@endphp

<footer class="storefront-footer bg-white border-top mt-5 py-4">
    <div class="container-fluid px-3 px-lg-5">
        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-4">
                <a href="{{ route('storefront.home') }}" class="d-inline-flex align-items-center text-decoration-none mb-3" aria-label="{{ $storeName }}">
                    @if($storeLogo)
                        <img
                            src="{{ $storeLogo }}"
                            alt="{{ $storeName }}"
                            style="display: block; width: 100px; max-width: 150px; max-height: 30px; object-fit: contain;"
                        >
                    @else
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-dark text-white fw-bold" style="width: 36px; height: 36px;">
                            {{ mb_substr($storeName, 0, 1) }}
                        </span>

                        <span class="fw-bold text-body ms-2">{{ $storeName }}</span>
                    @endif
                </a>

                <div class="small text-body-secondary" style="line-height: 1.55; max-width: 22rem;">
                    <div class="text-body fw-semibold">{{ $companyName }}</div>

                    @if($companyAddress)
                        <div>{{ $companyAddress }}</div>
                    @endif

                    @if($companyVat)
                        <div>P. IVA {{ $companyVat }}</div>
                    @endif

                    @if($companyEmail)
                        <div>
                            <a href="mailto:{{ $companyEmail }}" class="text-body-secondary text-decoration-none">
                                {{ $companyEmail }}
                            </a>
                        </div>
                    @endif

                    @if($companyPhone)
                        <div>
                            <a href="tel:{{ preg_replace('/\s+/', '', (string) $companyPhone) }}" class="text-body-secondary text-decoration-none">
                                {{ $companyPhone }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-uppercase fw-bold mb-3" style="font-size: .72rem; letter-spacing: .06em;">Menu</h6>

                <ul class="list-unstyled d-flex flex-column gap-2 mb-0 small">
                    <li>
                        <a href="{{ route('storefront.home') }}" class="text-body-secondary text-decoration-none">Home</a>
                    </li>
                    <li>
                        <a href="{{ route('storefront.catalog.index') }}" class="text-body-secondary text-decoration-none">Catalogo</a>
                    </li>

                    @foreach($navigationTree->take(5) as $category)
                        @php
                            $categorySlug = $category['slug'] ?? null;
                            $categoryLabel = $category['label'] ?? $category['code'] ?? null;
                        @endphp

                        @if($categorySlug && $categoryLabel)
                            <li>
                                <a href="{{ route('storefront.category.show', $categorySlug) }}" class="text-body-secondary text-decoration-none">
                                    {{ $categoryLabel }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-uppercase fw-bold mb-3" style="font-size: .72rem; letter-spacing: .06em;">Link utili</h6>

                <ul class="list-unstyled d-flex flex-column gap-2 mb-0 small">
                    <li>
                        <a href="{{ route('storefront.cart.index') }}" class="text-body-secondary text-decoration-none">Carrello</a>
                    </li>
                    <li>
                        <a href="{{ route('storefront.checkout.show') }}" class="text-body-secondary text-decoration-none">Checkout</a>
                    </li>

                    @auth('customer')
                        <li>
                            <a href="{{ route('storefront.account.index') }}" class="text-body-secondary text-decoration-none">Il mio account</a>
                        </li>
                    @else
                        <li>
                            <a href="{{ route('storefront.login') }}" class="text-body-secondary text-decoration-none">Accedi</a>
                        </li>
                    @endauth

                    @if(Route::has('storefront.password.request'))
                        <li>
                            <a href="{{ route('storefront.password.request') }}" class="text-body-secondary text-decoration-none">Password dimenticata</a>
                        </li>
                    @endif
                </ul>
            </div>

            <div class="col-12 col-lg-4">
                <h6 class="text-uppercase fw-bold mb-3" style="font-size: .72rem; letter-spacing: .06em;">Social</h6>

                @if($footerSocials->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach($footerSocials as $social)
                            @php
                                $label = (string) ($social['label'] ?? 'Social');
                                $url = (string) ($social['url'] ?? '#');
                                $iconClass = $socialIcon($label, (string) ($social['icon'] ?? ''));
                            @endphp

                            <a
                                href="{{ $url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn btn-sm btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center"
                                style="width: 32px; height: 32px; padding: 0;"
                                aria-label="{{ $label }}"
                                title="{{ $label }}"
                            >
                                <i class="{{ $iconClass }}"></i>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="small text-body-secondary mb-3">
                        Seguici sui nostri canali social.
                    </div>
                @endif

                <div class="small text-body-secondary">
                    © {{ date('Y') }} {{ $storeName }}. Tutti i diritti riservati.
                </div>
            </div>
        </div>

        <div class="border-top mt-4 pt-3 d-flex flex-column flex-md-row justify-content-between gap-2 small text-body-secondary">
            <div>
                {{ $storeName }} · Store {{ $store?->is_b2b ? 'B2B' : 'B2C' }}
            </div>

            <div class="d-flex flex-wrap gap-3">
                <a href="{{ route('storefront.catalog.index') }}" class="text-body-secondary text-decoration-none">Catalogo</a>
                <a href="{{ route('storefront.cart.index') }}" class="text-body-secondary text-decoration-none">Carrello</a>
            </div>
        </div>
    </div>
</footer>
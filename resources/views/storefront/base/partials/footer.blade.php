<footer class="storefront-footer bg-white border-top mt-5 py-4">
    @php
        $footerIsB2b = $store?->isB2B() ?? false;
        $footerIsB2c = !$footerIsB2b;
    @endphp

    <div class="container-fluid px-3 px-lg-5">
        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-4">
                <a href="{{ route('storefront.home', $contextParams) }}" class="d-inline-flex align-items-center text-decoration-none mb-3" aria-label="{{ $storeName }}">
                    @if($storeLogo)
                        <img
                            src="{{ $storeLogo }}"
                            alt="{{ $storeName }}"
                            class="storefront-footer-logo"
                        >
                    @else
                        <span class="storefront-footer-logo-fallback d-inline-flex align-items-center justify-content-center rounded bg-dark text-white fw-bold">
                            {{ mb_substr($storeName, 0, 1) }}
                        </span>

                        <span class="fw-bold text-body ms-2">{{ $storeName }}</span>
                    @endif
                </a>

                <div class="storefront-footer-copy small text-body-secondary">
                    @php
                        $legalProfile = collect($legalProfile ?? [])->filter(fn ($value) => filled($value));
                        $legalCompany = $legalProfile->get('company') ?: $companyName;
                        $legalAddress = collect([$legalProfile->get('address'), $legalProfile->get('city'), $legalProfile->get('country')])
                            ->filter(fn ($value) => filled($value))
                            ->implode(', ');
                        $legalVat = $legalProfile->get('vat') ?: $companyVat;
                        $legalTaxCode = $legalProfile->get('tax_code');
                        $legalEmail = $legalProfile->get('email') ?: $companyEmail;
                        $legalPhone = $legalProfile->get('phone') ?: $companyPhone;
                    @endphp

                    <div class="text-body fw-semibold">{{ $legalCompany }}</div>

                    @if($legalAddress !== '')
                        <div>{{ $legalAddress }}</div>
                    @elseif($companyAddress)
                        <div>{{ $companyAddress }}</div>
                    @endif

                    @if($legalVat)
                        <div>P. IVA {{ $legalVat }}</div>
                    @endif

                    @if($legalTaxCode && $legalTaxCode !== $legalVat)
                        <div>C.F. {{ $legalTaxCode }}</div>
                    @endif

                    @if($legalProfile->get('sdi'))
                        <div>SDI {{ $legalProfile->get('sdi') }}</div>
                    @endif

                    @if($legalProfile->get('pec'))
                        <div>PEC {{ $legalProfile->get('pec') }}</div>
                    @endif

                    @if($legalProfile->get('rea'))
                        <div>REA {{ $legalProfile->get('rea') }}</div>
                    @endif

                    @if($legalProfile->get('company_register'))
                        <div>{{ $legalProfile->get('company_register') }}</div>
                    @endif

                    @if($legalEmail)
                        <div>
                            <a href="mailto:{{ $legalEmail }}" class="text-body-secondary text-decoration-none">
                                {{ $legalEmail }}
                            </a>
                        </div>
                    @endif

                    @if($legalPhone)
                        <div>
                            <a href="tel:{{ preg_replace('/\s+/', '', (string) $legalPhone) }}" class="text-body-secondary text-decoration-none">
                                {{ $legalPhone }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="storefront-footer-heading text-uppercase fw-bold mb-3">Menu</h6>

                <ul class="list-unstyled d-flex flex-column gap-2 mb-0 small">
                    <li>
                        <a href="{{ route('storefront.home', $contextParams) }}" class="text-body-secondary text-decoration-none">Home</a>
                    </li>
                    @if(Route::has('storefront.catalog.index'))
                        <li>
                            <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="text-body-secondary text-decoration-none">Catalogo</a>
                        </li>
                    @endif

                    @foreach($navigationTree->take(5) as $category)
                        @php
                            $categorySlug = $category['slug'] ?? null;
                            $categoryLabel = $category['label'] ?? $category['code'] ?? null;
                        @endphp

                        @if($categorySlug && $categoryLabel)
                            <li>
                                <a href="{{ route('storefront.category.show', array_merge(['slug' => $categorySlug], $contextParams)) }}" class="text-body-secondary text-decoration-none">
                                    {{ $categoryLabel }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="storefront-footer-heading text-uppercase fw-bold mb-3">Link utili</h6>

                <ul class="list-unstyled d-flex flex-column gap-2 mb-0 small">
                    @if(Route::has('storefront.cart.index'))
                        <li>
                            <a href="{{ route('storefront.cart.index', $contextParams) }}" class="text-body-secondary text-decoration-none">Carrello</a>
                        </li>
                    @endif

                    @if($footerIsB2c && Route::has('storefront.checkout.show'))
                        <li>
                            <a href="{{ route('storefront.checkout.show', $contextParams) }}" class="text-body-secondary text-decoration-none">Checkout</a>
                        </li>
                    @endif

                    @auth('customer')
                        <li>
                            <a href="{{ Route::has('storefront.account.index') ? route('storefront.account.index', $contextParams) : route('storefront.home', $contextParams) }}" class="text-body-secondary text-decoration-none">Il mio account</a>
                        </li>
                    @else
                        <li>
                            <a href="{{ Route::has('storefront.login') ? route('storefront.login', $contextParams) : route('storefront.home', $contextParams) }}" class="text-body-secondary text-decoration-none">Accedi</a>
                        </li>
                    @endauth

                    @if($footerIsB2b && auth('customer')->check() && Route::has('storefront.account.orders.index'))
                        <li>
                            <a href="{{ route('storefront.account.orders.index', $contextParams) }}" class="text-body-secondary text-decoration-none">I miei ordini</a>
                        </li>
                    @endif

                    @if($footerIsB2b && auth('customer')->check() && Route::has('storefront.account.documents.index'))
                        <li>
                            <a href="{{ route('storefront.account.documents.index', $contextParams) }}" class="text-body-secondary text-decoration-none">Documenti</a>
                        </li>
                    @endif

                    @if($footerIsB2c && auth('customer')->check() && Route::has('storefront.wishlist.index'))
                        <li>
                            <a href="{{ route('storefront.wishlist.index', $contextParams) }}" class="text-body-secondary text-decoration-none">Preferiti</a>
                        </li>
                    @endif

                    @if(!auth('customer')->check() && Route::has('storefront.password.request'))
                        <li>
                            <a href="{{ route('storefront.password.request', $contextParams) }}" class="text-body-secondary text-decoration-none">Password dimenticata</a>
                        </li>
                    @endif

                    @if($footerIsB2c && Route::has('storefront.store-locator.index'))
                        <li>
                            <a href="{{ route('storefront.store-locator.index', $contextParams) }}" class="text-body-secondary text-decoration-none">Punti vendita</a>
                        </li>
                    @endif

                    @if($footerIsB2c && Route::has('storefront.contact.index'))
                        <li>
                            <a href="{{ route('storefront.contact.index', $contextParams) }}" class="text-body-secondary text-decoration-none">{{ __('inquiries.links.contact') }}</a>
                        </li>
                    @endif

                    @if($footerIsB2c && Route::has('storefront.corporate-gift.index'))
                        <li>
                            <a href="{{ route('storefront.corporate-gift.index', $contextParams) }}" class="text-body-secondary text-decoration-none">{{ __('inquiries.links.corporate_gift') }}</a>
                        </li>
                    @endif
                </ul>
            </div>

            <div class="col-12 col-lg-4">
                <h6 class="storefront-footer-heading text-uppercase fw-bold mb-3">Social</h6>

                @if($footerSocials->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach($footerSocials as $social)
                            <a
                                href="{{ $social['url'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="storefront-footer-social-link btn btn-sm btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center"
                                aria-label="{{ $social['label'] }}"
                                title="{{ $social['label'] }}"
                            >
                                <i class="{{ $social['icon_class'] }}"></i>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="small text-body-secondary mb-3">
                        Seguici sui nostri canali social.
                    </div>
                @endif

                <div class="small text-body-secondary">
                    © {{ $currentYear }} {{ $storeName }}. Tutti i diritti riservati.
                </div>
            </div>
        </div>

        <div class="border-top mt-4 pt-3 d-flex flex-column flex-md-row justify-content-between gap-2 small text-body-secondary">
            <div>
                {{ $storeName }} · Store {{ $store?->channelLabel() ?? 'B2C' }}
            </div>

            <div class="d-flex flex-wrap gap-3">
                @if(Route::has('storefront.catalog.index'))
                    <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="text-body-secondary text-decoration-none">Catalogo</a>
                @endif

                @if(Route::has('storefront.cart.index'))
                    <a href="{{ route('storefront.cart.index', $contextParams) }}" class="text-body-secondary text-decoration-none">Carrello</a>
                @endif

                @if($footerIsB2b && auth('customer')->check() && Route::has('storefront.account.index'))
                    <a href="{{ route('storefront.account.index', $contextParams) }}" class="text-body-secondary text-decoration-none">Area cliente</a>
                @endif

                @if($footerIsB2b && auth('customer')->check() && Route::has('storefront.account.documents.index'))
                    <a href="{{ route('storefront.account.documents.index', $contextParams) }}" class="text-body-secondary text-decoration-none">Documenti</a>
                @endif

                @if(Route::has('storefront.privacy'))
                    <a href="{{ route('storefront.privacy', $contextParams) }}" class="text-body-secondary text-decoration-none">Privacy policy</a>
                @endif

                @if(Route::has('storefront.cookies'))
                    <a href="{{ route('storefront.cookies', $contextParams) }}" class="text-body-secondary text-decoration-none">Cookie policy</a>
                @endif

                @if(Route::has('storefront.shipping-returns'))
                    <a href="{{ route('storefront.shipping-returns', $contextParams) }}" class="text-body-secondary text-decoration-none">{{ __('legal.shipping_returns.title') }}</a>
                @endif

                @if($footerIsB2c && Route::has('storefront.store-locator.index'))
                    <a href="{{ route('storefront.store-locator.index', $contextParams) }}" class="text-body-secondary text-decoration-none">Punti vendita</a>
                @endif

                @if($footerIsB2c && Route::has('storefront.contact.index'))
                    <a href="{{ route('storefront.contact.index', $contextParams) }}" class="text-body-secondary text-decoration-none">{{ __('inquiries.links.contact') }}</a>
                @endif

                @if($footerIsB2c && Route::has('storefront.corporate-gift.index'))
                    <a href="{{ route('storefront.corporate-gift.index', $contextParams) }}" class="text-body-secondary text-decoration-none">{{ __('inquiries.links.corporate_gift') }}</a>
                @endif
            </div>
        </div>
    </div>
</footer>

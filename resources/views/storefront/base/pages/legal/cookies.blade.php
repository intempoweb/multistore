@extends($storefrontLayout)

@section('title', __('legal.cookies.title'))

@section('content')
@php
    $cookieCatalog = $cookieCatalog ?? ['categories' => [], 'cookies' => [], 'clear_patterns' => []];
    $cookieCategories = collect($cookieCatalog['categories'] ?? []);
    $cookieCategoriesByKey = $cookieCategories->keyBy('key');
    $cookieConsentName = (string) config('legal.cookie_consent.name', 'storefront_cookie_consent');
    $cookieConsentVersion = (string) config('legal.cookie_consent.version', '1');
    $cookieConsentDays = (int) config('legal.cookie_consent.days', 180);
@endphp

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <header class="mb-5">
                <span class="badge rounded-pill text-bg-light border mb-3">
                    {{ __('legal.cookies.last_updated') }}: {{ $legalUpdatedAt }}
                </span>

                <h1 class="display-5 fw-semibold mb-3">
                    {{ __('legal.cookies.title') }}
                </h1>

                <p class="lead text-body-secondary mb-0">
                    {{ __('legal.cookies.intro') }}
                </p>
            </header>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-4">
                        {{ __('legal.cookies.types.title') }}
                    </h2>

                    <div class="row g-3">
                        @foreach($cookieCategories as $category)
                            <div class="col-12 col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="fw-semibold mb-2">
                                        {{ $category['label'] }}
                                    </div>
                                    <p class="small text-body-secondary mb-3">{{ $category['description'] }}</p>
                                    @if((bool) ($category['required'] ?? false))
                                        <span class="badge text-bg-success">{{ __('legal.common.always_active') }}</span>
                                    @else
                                        <span class="badge text-bg-warning">{{ __('legal.common.consent_required') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex gap-3 align-items-start mb-4">
                        <div class="flex-shrink-0 text-primary fs-4">
                            <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                        </div>

                        <div>
                            <h2 class="h4 fw-semibold mb-2">
                                {{ __('legal.cookies.management.title') }}
                            </h2>

                            <p class="text-body-secondary mb-0">
                                {{ __('legal.cookies.management.text') }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="storefront-cookie-policy-panel"
                        data-cookie-consent-root
                        data-cookie-policy-panel
                        data-cookie-name="{{ $cookieConsentName }}"
                        data-cookie-version="{{ $cookieConsentVersion }}"
                        data-cookie-days="{{ $cookieConsentDays }}"
                        data-cookie-clear-patterns='@json($cookieCatalog['clear_patterns'] ?? [])'
                    >
                        <div class="storefront-cookie-policy-panel__title">
                            {{ __('legal.cookies.current_preferences') }}
                        </div>

                        <div class="storefront-cookie-consent__choices">
                            @foreach($cookieCategories as $category)
                                <label class="storefront-cookie-toggle">
                                    <span>
                                        <strong>{{ $category['label'] }}</strong>
                                        <small>{{ $category['description'] }}</small>
                                    </span>
                                    <input
                                        type="checkbox"
                                        data-cookie-category="{{ $category['key'] }}"
                                        @checked((bool) ($category['required'] ?? false))
                                        @disabled((bool) ($category['required'] ?? false))
                                    >
                                </label>
                            @endforeach
                        </div>

                        <div class="storefront-cookie-policy-panel__actions">
                            <button type="button" class="storefront-cookie-consent__button storefront-cookie-consent__button--ghost" data-cookie-necessary-only>
                                {{ __('legal.cookie_banner.necessary_only') }}
                            </button>
                            <button type="button" class="storefront-cookie-consent__button storefront-cookie-consent__button--ghost" data-cookie-save-preferences>
                                {{ __('legal.cookie_banner.save') }}
                            </button>
                            <button type="button" class="storefront-cookie-consent__button" data-cookie-accept-all>
                                {{ __('legal.cookie_banner.accept_all') }}
                            </button>
                        </div>

                        <div class="storefront-cookie-policy-panel__status" data-cookie-preferences-status hidden>
                            {{ __('legal.cookies.management.saved') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-4">
                        {{ __('legal.cookies.services.title') }}
                    </h2>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">{{ __('legal.cookies.services.table.category') }}</th>
                                    <th scope="col">{{ __('legal.cookies.services.table.service') }}</th>
                                    <th scope="col">{{ __('legal.cookies.services.table.provider') }}</th>
                                    <th scope="col">{{ __('legal.cookies.services.table.cookies') }}</th>
                                    <th scope="col">{{ __('legal.cookies.services.table.duration') }}</th>
                                    <th scope="col">{{ __('legal.cookies.services.table.purpose') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cookieCatalog['cookies'] ?? [] as $cookie)
                                    @php($category = $cookieCategoriesByKey->get($cookie['category'] ?? ''))
                                    <tr>
                                        <td>
                                            <span class="badge text-bg-light border">
                                                {{ $category['label'] ?? ($cookie['category'] ?? '') }}
                                            </span>
                                        </td>
                                        <td class="fw-semibold">{{ $cookie['service'] }}</td>
                                        <td>{{ $cookie['provider'] }}</td>
                                        <td>
                                            @foreach($cookie['names'] ?? [] as $name)
                                                <code>{{ $name }}</code>@unless($loop->last)<br>@endunless
                                            @endforeach
                                        </td>
                                        <td>{{ $cookie['duration'] }}</td>
                                        <td>{{ $cookie['purpose'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.cookies.installed_title') }}
                    </h2>

                    <p class="text-body-secondary mb-4">
                        {{ __('legal.cookies.installed_text') }}
                    </p>

                    <div
                        class="storefront-cookie-installed-list"
                        data-cookie-installed-list
                        data-empty-text="{{ __('legal.cookies.installed_empty') }}"
                    >
                        <p class="text-body-secondary mb-0">{{ __('legal.cookies.installed_empty') }}</p>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.privacy.owner.title') }}
                    </h2>

                    <p class="text-body-secondary mb-4">
                        {{ __('legal.privacy.owner.text') }}
                    </p>

                    <dl class="row mb-0 gy-3">
                        <dt class="col-sm-4 text-body-secondary">{{ __('legal.company.company') }}</dt>
                        <dd class="col-sm-8 mb-0 fw-semibold">{{ $legalProfile['company'] }}</dd>

                        @if(!empty($legalProfile['address']) || !empty($legalProfile['city']) || !empty($legalProfile['country']))
                            <dt class="col-sm-4 text-body-secondary">{{ __('legal.company.address') }}</dt>
                            <dd class="col-sm-8 mb-0">
                                {{ collect([$legalProfile['address'] ?? null, $legalProfile['city'] ?? null, $legalProfile['country'] ?? null])->filter()->implode(', ') }}
                            </dd>
                        @endif

                        @if(!empty($legalProfile['vat']))
                            <dt class="col-sm-4 text-body-secondary">{{ __('legal.company.vat') }}</dt>
                            <dd class="col-sm-8 mb-0">{{ $legalProfile['vat'] }}</dd>
                        @endif

                        @if(!empty($legalProfile['tax_code']))
                            <dt class="col-sm-4 text-body-secondary">{{ __('legal.company.tax_code') }}</dt>
                            <dd class="col-sm-8 mb-0">{{ $legalProfile['tax_code'] }}</dd>
                        @endif

                        @if(!empty($legalProfile['email']))
                            <dt class="col-sm-4 text-body-secondary">{{ __('legal.company.email') }}</dt>
                            <dd class="col-sm-8 mb-0">
                                <a href="mailto:{{ $legalProfile['email'] }}">{{ $legalProfile['email'] }}</a>
                            </dd>
                        @endif

                        @if(!empty($legalProfile['pec']))
                            <dt class="col-sm-4 text-body-secondary">{{ __('legal.company.pec') }}</dt>
                            <dd class="col-sm-8 mb-0">
                                <a href="mailto:{{ $legalProfile['pec'] }}">{{ $legalProfile['pec'] }}</a>
                            </dd>
                        @endif

                        @if(!empty($legalProfile['phone']))
                            <dt class="col-sm-4 text-body-secondary">{{ __('legal.company.phone') }}</dt>
                            <dd class="col-sm-8 mb-0">
                                <a href="tel:{{ preg_replace('/\s+/', '', (string) $legalProfile['phone']) }}">{{ $legalProfile['phone'] }}</a>
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@extends($storefrontLayout)

@section('title', __('legal.cookies.title'))

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-9">
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
                        <div class="col-12 col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="fw-semibold mb-2">
                                    {{ __('legal.cookies.types.technical') }}
                                </div>
                                <span class="badge text-bg-success">{{ __('legal.common.always_active') }}</span>
                            </div>
                        </div>

                        @if($legalServices['google_analytics_enabled'])
                            <div class="col-12 col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="fw-semibold mb-2">
                                        {{ __('legal.cookies.types.analytics') }}
                                    </div>
                                    <span class="badge text-bg-warning">{{ __('legal.common.consent_required') }}</span>
                                </div>
                            </div>
                        @endif

                        @if($legalServices['google_ads_enabled'])
                            <div class="col-12 col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="fw-semibold mb-2">
                                        {{ __('legal.cookies.types.marketing') }}
                                    </div>
                                    <span class="badge text-bg-warning">{{ __('legal.common.consent_required') }}</span>
                                </div>
                            </div>
                        @endif

                        @if($legalServices['instagram_enabled'] || $legalServices['google_maps_enabled'])
                            <div class="col-12 col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="fw-semibold mb-2">
                                        {{ __('legal.cookies.types.third_party') }}
                                    </div>
                                    <span class="badge text-bg-warning">{{ __('legal.common.consent_required') }}</span>
                                </div>
                            </div>
                        @endif
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
                                    <th scope="col">{{ __('legal.cookies.services.table.service') }}</th>
                                    <th scope="col">{{ __('legal.cookies.services.table.purpose') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-semibold">{{ __('legal.common.technical_cookies') }}</td>
                                    <td>{{ __('legal.cookies.types.technical') }}</td>
                                </tr>

                                @if($legalServices['google_analytics_enabled'])
                                    <tr>
                                        <td class="fw-semibold">{{ __('legal.cookies.services.google_analytics') }}</td>
                                        <td>{{ __('legal.cookies.types.analytics') }}</td>
                                    </tr>
                                @endif

                                @if($legalServices['google_ads_enabled'])
                                    <tr>
                                        <td class="fw-semibold">{{ __('legal.cookies.services.google_ads') }}</td>
                                        <td>{{ __('legal.cookies.types.marketing') }}</td>
                                    </tr>
                                @endif

                                @if($legalServices['google_maps_enabled'])
                                    <tr>
                                        <td class="fw-semibold">{{ __('legal.cookies.services.google_maps') }}</td>
                                        <td>{{ __('legal.cookies.types.third_party') }}</td>
                                    </tr>
                                @endif

                                @if($legalServices['instagram_enabled'])
                                    <tr>
                                        <td class="fw-semibold">{{ __('legal.cookies.services.instagram') }}</td>
                                        <td>{{ __('legal.cookies.types.third_party') }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="flex-shrink-0 text-primary fs-4">
                            <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                        </div>

                        <div>
                            <h2 class="h4 fw-semibold mb-3">
                                {{ __('legal.cookies.management.title') }}
                            </h2>

                            <p class="text-body-secondary mb-0">
                                {{ __('legal.cookies.management.text') }}
                            </p>
                        </div>
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

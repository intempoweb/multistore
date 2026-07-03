

@extends($storefrontLayout)

@section('title', __('legal.privacy.title'))

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-9">
            <header class="mb-5">
                <span class="badge rounded-pill text-bg-light border mb-3">
                    {{ __('legal.privacy.last_updated') }}: {{ $legalUpdatedAt }}
                </span>

                <h1 class="display-5 fw-semibold mb-3">
                    {{ __('legal.privacy.title') }}
                </h1>
            </header>

            <div class="card border-0 shadow-sm mb-4">
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

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.privacy.data_collected.title') }}
                    </h2>

                    <p class="text-body-secondary mb-4">
                        {{ __('legal.privacy.data_collected.text_' . $legalMode) }}
                    </p>

                    <ul class="mb-0 ps-3">
                        <li>{{ __('legal.privacy.data_collected.account') }}</li>
                        <li>{{ __('legal.privacy.data_collected.orders') }}</li>
                        <li>{{ __('legal.privacy.data_collected.payments') }}</li>
                        <li>{{ __('legal.privacy.data_collected.support') }}</li>
                        <li>{{ __('legal.privacy.data_collected.technical') }}</li>
                        @if($isB2b)
                            <li>{{ __('legal.privacy.data_collected.b2b_commercial') }}</li>
                        @endif
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.privacy.purposes.title') }}
                    </h2>

                    <p class="text-body-secondary mb-4">
                        {{ __('legal.privacy.purposes.text_' . $legalMode) }}
                    </p>

                    <ul class="mb-0 ps-3">
                        <li>{{ __('legal.privacy.purposes.ecommerce') }}</li>
                        <li>{{ __('legal.privacy.purposes.customer_area') }}</li>
                        <li>{{ __('legal.privacy.purposes.shipping') }}</li>
                        <li>{{ __('legal.privacy.purposes.invoicing') }}</li>
                        <li>{{ __('legal.privacy.purposes.security') }}</li>
                        <li>{{ __('legal.privacy.purposes.marketing_optional') }}</li>
                        <li>{{ __('legal.privacy.purposes.analytics_optional') }}</li>
                        @if($isB2b)
                            <li>{{ __('legal.privacy.purposes.b2b_commercial') }}</li>
                        @endif
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.privacy.legal_basis.title') }}
                    </h2>

                    <p class="text-body-secondary mb-4">
                        {{ __('legal.privacy.legal_basis.text') }}
                    </p>

                    <ul class="mb-0 ps-3">
                        <li>{{ __('legal.privacy.legal_basis.contract') }}</li>
                        <li>{{ __('legal.privacy.legal_basis.legal_obligation') }}</li>
                        <li>{{ __('legal.privacy.legal_basis.legitimate_interest') }}</li>
                        <li>{{ __('legal.privacy.legal_basis.consent') }}</li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.privacy.services.title') }}
                    </h2>

                    <p class="text-body-secondary mb-4">
                        {{ __('legal.privacy.services.text') }}
                    </p>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('legal.privacy.services.table.service') }}</th>
                                    <th>{{ __('legal.privacy.services.table.purpose') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-semibold">{{ __('legal.privacy.services.ecommerce_platform') }}</td>
                                    <td>{{ __('legal.privacy.services.ecommerce_platform_description') }}</td>
                                </tr>
                                @if($isB2b)
                                    <tr>
                                        <td class="fw-semibold">{{ __('legal.privacy.services.ecommerce_platform') }} B2B</td>
                                        <td>{{ __('legal.privacy.services.b2b_area_description') }}</td>
                                    </tr>
                                @endif
                                @if(!$isB2b && ($legalServices['stripe_enabled'] || $legalServices['paypal_enabled']))
                                    <tr>
                                        <td class="fw-semibold">Stripe / PayPal</td>
                                        <td>{{ __('legal.privacy.services.payment_description') }}</td>
                                    </tr>
                                @endif
                                @if(!$isB2b && $legalServices['sendcloud_enabled'])
                                    <tr>
                                        <td class="fw-semibold">Sendcloud</td>
                                        <td>{{ __('legal.privacy.services.shipping_description') }}</td>
                                    </tr>
                                @endif
                                @if($legalServices['google_analytics_enabled'])
                                    <tr>
                                        <td class="fw-semibold">Google Analytics</td>
                                        <td>{{ __('legal.privacy.services.analytics_description') }}</td>
                                    </tr>
                                @endif
                                @if($legalServices['google_ads_enabled'])
                                    <tr>
                                        <td class="fw-semibold">Google Ads</td>
                                        <td>{{ __('legal.privacy.services.ads_description') }}</td>
                                    </tr>
                                @endif
                                @if($legalServices['google_maps_enabled'])
                                    <tr>
                                        <td class="fw-semibold">Google Maps</td>
                                        <td>{{ __('legal.privacy.services.maps_description') }}</td>
                                    </tr>
                                @endif
                                @if($legalServices['instagram_enabled'])
                                    <tr>
                                        <td class="fw-semibold">Instagram</td>
                                        <td>{{ __('legal.privacy.services.instagram_description') }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.privacy.retention.title') }}
                    </h2>

                    <p class="text-body-secondary mb-0">
                        {{ __('legal.privacy.retention.text') }}
                    </p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.privacy.rights.title') }}
                    </h2>

                    <p class="text-body-secondary mb-4">
                        {{ __('legal.privacy.rights.text') }}
                    </p>

                    <ul class="mb-0 ps-3">
                        <li>{{ __('legal.privacy.rights.access') }}</li>
                        <li>{{ __('legal.privacy.rights.rectification') }}</li>
                        <li>{{ __('legal.privacy.rights.erasure') }}</li>
                        <li>{{ __('legal.privacy.rights.restriction') }}</li>
                        <li>{{ __('legal.privacy.rights.portability') }}</li>
                        <li>{{ __('legal.privacy.rights.objection') }}</li>
                        <li>{{ __('legal.privacy.rights.withdraw') }}</li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.privacy.contact.title') }}
                    </h2>

                    <p class="text-body-secondary mb-4">
                        {{ __('legal.privacy.contact.text') }}
                    </p>

                    <dl class="row mb-0 gy-3">
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
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

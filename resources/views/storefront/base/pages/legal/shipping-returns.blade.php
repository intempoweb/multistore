@extends($storefrontLayout)

@section('title', __('legal.shipping_returns.title'))

@section('content')
@php
    $returnDays = (int) ($shippingReturnsProfile['return_days'] ?? 14);
    $supportEmail = trim((string) (($shippingReturnsProfile['customer_care_email'] ?? null) ?: ($legalProfile['email'] ?? '')));
    $depositAddress = trim((string) ($shippingReturnsProfile['return_deposit_address'] ?? ''));
    $storeDomain = trim((string) ($store?->domain ?? ''));
    $siteUrl = $storeDomain !== ''
        ? (str_starts_with($storeDomain, 'http://') || str_starts_with($storeDomain, 'https://') ? $storeDomain : 'https://' . $storeDomain)
        : url('/');
    $emailReplacement = $supportEmail !== ''
        ? '<a href="mailto:' . e($supportEmail) . '">' . e($supportEmail) . '</a>'
        : e(__('legal.company.email'));
    $siteReplacement = '<a href="' . e($siteUrl) . '" target="_blank" rel="noopener">' . e($siteUrl) . '</a>';
    $returnParams = [
        'days' => $returnDays,
        'email' => $emailReplacement,
        'deposit' => e($depositAddress),
        'company' => e($legalProfile['company']),
        'site' => $siteReplacement,
    ];
@endphp

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-9">
            <header class="mb-5">
                <span class="badge rounded-pill text-bg-light border mb-3">
                    {{ __('legal.shipping_returns.last_updated') }}: {{ $legalUpdatedAt }}
                </span>

                <h1 class="display-5 fw-semibold mb-3">
                    {{ __('legal.shipping_returns.title') }}
                </h1>

                <p class="lead text-body-secondary mb-0">
                    {{ __('legal.shipping_returns.intro_' . $legalMode) }}
                </p>
            </header>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.shipping_returns.shipping.title') }}
                    </h2>

                    <p class="text-body-secondary">
                        {{ __('legal.shipping_returns.shipping.' . $legalMode . '_text') }}
                    </p>

                    <p class="text-body-secondary mb-0">
                        {{ __('legal.shipping_returns.shipping.tracking') }}
                    </p>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.shipping_returns.returns.title') }}
                    </h2>

                    <p class="text-body-secondary">
                        @if($isB2b)
                            {{ __('legal.shipping_returns.returns.b2b_text') }}
                        @else
                            {!! __('legal.shipping_returns.returns.b2c_order_locked') !!}
                        @endif
                    </p>

                    @if($isB2b)
                        <p class="text-body-secondary mb-0">
                            {{ __('legal.shipping_returns.returns.condition') }}
                        </p>
                    @else
                        <div class="d-grid gap-3 text-body-secondary">
                            <p class="mb-0">{!! __('legal.shipping_returns.returns.b2c_request', $returnParams) !!}</p>
                            <p class="mb-0">{!! __('legal.shipping_returns.returns.b2c_shipping', $returnParams) !!}</p>
                            <p class="mb-0">{{ __('legal.shipping_returns.returns.b2c_instructions') }}</p>
                            <p class="mb-0">{{ __('legal.shipping_returns.returns.b2c_refund') }}</p>
                            <p class="mb-0">{{ __('legal.shipping_returns.returns.b2c_rejection') }}</p>
                            <p class="mb-0">{!! __('legal.shipping_returns.returns.b2c_damaged', $returnParams) !!}</p>
                            <p class="mb-0">{!! __('legal.shipping_returns.returns.b2c_retailers', $returnParams) !!}</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 fw-semibold mb-3">
                        {{ __('legal.shipping_returns.how_to_request.title') }}
                    </h2>

                    <p class="text-body-secondary mb-4">
                        {{ __('legal.shipping_returns.how_to_request.text') }}
                    </p>

                    <dl class="row mb-0 gy-3">
                        <dt class="col-sm-4 text-body-secondary">{{ __('legal.company.company') }}</dt>
                        <dd class="col-sm-8 mb-0 fw-semibold">{{ $legalProfile['company'] }}</dd>

                        @if($supportEmail !== '')
                            <dt class="col-sm-4 text-body-secondary">{{ __('legal.company.email') }}</dt>
                            <dd class="col-sm-8 mb-0">
                                <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
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

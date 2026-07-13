@php
    $cookieConsentName = (string) config('legal.cookie_consent.name', 'storefront_cookie_consent');
    $cookieConsentVersion = (string) config('legal.cookie_consent.version', '1');
    $cookieConsentDays = (int) config('legal.cookie_consent.days', 180);
    $cookieConsentValue = 'accepted:' . $cookieConsentVersion;
@endphp

<div
    class="storefront-cookie-consent"
    data-cookie-consent
    data-cookie-name="{{ $cookieConsentName }}"
    data-cookie-value="{{ $cookieConsentValue }}"
    data-cookie-days="{{ $cookieConsentDays }}"
    aria-label="{{ __('legal.cookie_banner.aria_label') }}"
    hidden
>
    <div class="storefront-cookie-consent__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" focusable="false">
            <path d="M20.3 13.4A8.6 8.6 0 1 1 10.6 3.7c.1 1 .9 1.8 1.9 1.8.4 0 .8-.1 1.1-.3.1 1.6 1.4 2.9 3 2.9.5 0 .9-.1 1.3-.3-.2.5-.3 1-.3 1.5 0 1.8 1.2 3.4 2.7 4.1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
            <path d="M8.2 9.2h.01M11.7 14.1h.01M7.9 16.5h.01M14.8 11.2h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
        </svg>
    </div>

    <div class="storefront-cookie-consent__text">
        {{ __('legal.cookie_banner.text') }}
        @if(Route::has('storefront.privacy'))
            <a href="{{ route('storefront.privacy', $contextParams ?? []) }}">{{ __('legal.cookie_banner.privacy') }}</a>
        @endif
        @if(Route::has('storefront.cookies'))
            <a href="{{ route('storefront.cookies', $contextParams ?? []) }}">{{ __('legal.cookie_banner.cookies') }}</a>
        @endif
    </div>

    <button type="button" class="storefront-cookie-consent__button" data-cookie-consent-accept>
        {{ __('legal.cookie_banner.accept') }}
    </button>
</div>

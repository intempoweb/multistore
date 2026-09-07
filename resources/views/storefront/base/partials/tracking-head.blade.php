@php
    $trackingStore = $store ?? current_store();
    $gtmContainerId = $trackingStore?->gtmContainerId();
    $gscVerificationCode = $trackingStore?->gscVerificationCode();
@endphp
@if($gscVerificationCode)
    <meta name="google-site-verification" content="{{ $gscVerificationCode }}">
@endif
@if($gtmContainerId)
    {{-- Caricato solo dopo consenso alla categoria "analytics" (vedi storefront-cookie-consent) --}}
    <script type="text/plain" data-cookie-script data-cookie-category="analytics">
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $gtmContainerId }}');
    </script>
@endif

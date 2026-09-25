@php
    $trackingStore = $store ?? current_store();
    $gtmContainerId = $trackingStore?->gtmContainerId();
    $gscVerificationCode = $trackingStore?->gscVerificationCode();
    $metaPixelId = $trackingStore?->metaPixelId();
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
@if($metaPixelId)
    {{-- Caricato solo dopo consenso alla categoria "marketing" (vedi storefront-cookie-consent) --}}
    <script type="text/plain" data-cookie-script data-cookie-category="marketing">
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '{{ $metaPixelId }}');
        fbq('track', 'PageView');
    </script>
@endif

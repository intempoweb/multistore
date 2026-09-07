@php
    $payload = is_array($payload ?? null) ? $payload : null;
    $dedupeKey = trim((string) ($dedupeKey ?? ''));
@endphp

@if($payload && filled($payload['event'] ?? null))
    <script type="text/plain" data-cookie-script data-cookie-category="analytics">
        (function () {
            window.dataLayer = window.dataLayer || [];

            var payload = @json($payload);
            var dedupeKey = @json($dedupeKey);
            var storageKey = dedupeKey ? 'ga4_ecommerce_event:' + dedupeKey : '';

            if (storageKey) {
                try {
                    if (window.sessionStorage.getItem(storageKey) === '1') {
                        return;
                    }

                    window.sessionStorage.setItem(storageKey, '1');
                } catch (error) {
                    // sessionStorage non disponibile: invia comunque l'evento.
                }
            }

            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push(payload);
        })();
    </script>
@endif

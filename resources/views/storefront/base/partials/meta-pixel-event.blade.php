@php
    $payload = is_array($payload ?? null) ? $payload : null;
    $dedupeKey = trim((string) ($dedupeKey ?? ''));
    $trackingStore = $store ?? current_store();
@endphp

@if($payload && filled($payload['event'] ?? null) && filled($trackingStore?->metaPixelId()))
    <script type="text/plain" data-cookie-script data-cookie-category="marketing">
        (function () {
            if (typeof window.fbq !== 'function') {
                return;
            }

            var payload = @json($payload);
            var dedupeKey = @json($dedupeKey);
            var storageKey = dedupeKey ? 'meta_pixel_event:' + dedupeKey : '';

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

            var eventName = payload.event;
            var parameters = payload.parameters || {};
            var options = payload.eventID ? { eventID: payload.eventID } : undefined;

            if (options) {
                window.fbq('track', eventName, parameters, options);
            } else {
                window.fbq('track', eventName, parameters);
            }
        })();
    </script>
@endif

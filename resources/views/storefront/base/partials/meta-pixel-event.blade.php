@php
    $payload = is_array($payload ?? null) ? $payload : null;
    $dedupeKey = trim((string) ($dedupeKey ?? ''));
    $trackingStore = $store ?? current_store();
@endphp

@if($payload && filled($payload['event'] ?? null) && filled($trackingStore?->metaPixelId()))
    <script type="text/plain" data-cookie-script data-cookie-category="marketing">
        (function () {
            var payload = @json($payload);
            var dedupeKey = @json($dedupeKey);

            var storageKey = dedupeKey
                ? 'meta_pixel_event:' + dedupeKey
                : '';

            var eventName = payload.event;
            var parameters = payload.parameters || {};
            var options = payload.eventID
                ? { eventID: payload.eventID }
                : undefined;

            var attempts = 0;
            var maxAttempts = 20;
            var retryDelay = 250;

            function alreadyTracked() {
                if (!storageKey) {
                    return false;
                }

                try {
                    return window.sessionStorage.getItem(storageKey) === '1';
                } catch (error) {
                    return false;
                }
            }

            function markTracked() {
                if (!storageKey) {
                    return;
                }

                try {
                    window.sessionStorage.setItem(storageKey, '1');
                } catch (error) {
                    // sessionStorage non disponibile.
                }
            }

            function trackEvent() {
                if (alreadyTracked()) {
                    return;
                }

                if (typeof window.fbq !== 'function') {
                    attempts++;

                    if (attempts < maxAttempts) {
                        window.setTimeout(trackEvent, retryDelay);
                    }

                    return;
                }

                try {
                    if (options) {
                        window.fbq('track', eventName, parameters, options);
                    } else {
                        window.fbq('track', eventName, parameters);
                    }

                    markTracked();
                } catch (error) {
                    console.warn(
                        '[Meta Pixel] Impossibile inviare evento:',
                        eventName,
                        error
                    );
                }
            }

            trackEvent();
        })();
    </script>
@endif
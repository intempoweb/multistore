(function () {
    let map = null;
    let infoWindow = null;
    let markers = new Map();
    let userMarker = null;
    let initialBoundsApplied = false;
    let payloadCache = null;
    let searchController = null;

    function payload() {
        if (payloadCache !== null) {
            return payloadCache;
        }

        const payloadElement = document.querySelector('[data-store-locator-payload]');

        if (!payloadElement) {
            payloadCache = {
                locations: Array.isArray(window.storeLocatorData)
                    ? window.storeLocatorData
                    : [],
                i18n: window.storeLocatorI18n || {},
                search: {}
            };

            return payloadCache;
        }

        try {
            const parsed = JSON.parse(
                payloadElement.textContent || '{}'
            );

            payloadCache = {
                locations: Array.isArray(parsed.locations)
                    ? parsed.locations
                    : [],

                i18n: parsed.i18n
                    && typeof parsed.i18n === 'object'
                    ? parsed.i18n
                    : {},

                search: parsed.search
                    && typeof parsed.search === 'object'
                    ? parsed.search
                    : {}
            };
        } catch (error) {
            console.warn(
                'Invalid store locator payload',
                error
            );

            payloadCache = {
                locations: [],
                i18n: {},
                search: {}
            };
        }

        return payloadCache;
    }

    function translate(key, fallback) {
        const messages = payload().i18n;
        const value = messages[key];

        return typeof value === 'string'
            && value.trim() !== ''
            ? value
            : fallback;
    }

    function numberOrNull(value) {
        if (
            value === null
            || value === undefined
            || value === ''
        ) {
            return null;
        }

        const number = Number(value);

        return Number.isFinite(number)
            ? number
            : null;
    }

    function queryCoordinate(name) {
        return numberOrNull(
            new URLSearchParams(
                window.location.search
            ).get(name)
        );
    }

    function userPositionFromQuery() {
        const lat = queryCoordinate('lat');
        const lng = queryCoordinate('lng');

        if (lat === null || lng === null) {
            return null;
        }

        return {
            lat,
            lng
        };
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function hasText(value) {
        return String(value ?? '').trim() !== '';
    }

    function telHref(value) {
        const phone = String(value ?? '')
            .replace(/[^\d+]/g, '');

        return phone !== ''
            ? `tel:${phone}`
            : null;
    }

    function normalizeExternalUrl(value) {
        const url = String(value ?? '').trim();

        if (url === '') {
            return null;
        }

        return /^https?:\/\//i.test(url)
            ? url
            : `https://${url.replace(/^\/+/, '')}`;
    }

    function directionsUrl(location) {
        const lat = numberOrNull(
            location.latitude
        );

        const lng = numberOrNull(
            location.longitude
        );

        if (lat === null || lng === null) {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='
            + encodeURIComponent(`${lat},${lng}`);
    }

    function allCards() {
        return Array.from(
            document.querySelectorAll(
                '[data-store-locator-card]'
            )
        );
    }

    function setActiveCard(
        locationId,
        scrollIntoView = false
    ) {
        const id = String(locationId);
        let activeCard = null;

        allCards().forEach(card => {
            const isActive =
                String(card.dataset.locationId) === id;

            card.classList.toggle(
                'active',
                isActive
            );

            if (isActive) {
                activeCard = card;
            }
        });

        if (
            scrollIntoView
            && activeCard
        ) {
            activeCard.scrollIntoView({
                block: 'nearest',
                behavior: 'smooth'
            });
        }
    }

    function infoWindowContent(location) {
        const name = escapeHtml(
            location.name
            || translate(
                'defaultStoreName',
                'Store'
            )
        );

        const address = escapeHtml(
            location.address_line || ''
        );

        const distance =
            location.distance_km !== null
            && location.distance_km !== undefined
                ? `
                    <span class="store-locator-infowindow-distance">
                        ${escapeHtml(location.distance_km)} km
                    </span>
                `
                : '';

        const phoneHref = telHref(
            location.phone
        );

        const email = hasText(location.email)
            ? String(location.email).trim()
            : null;

        const websiteUrl =
            normalizeExternalUrl(
                location.website
            );

        const mapUrl =
            directionsUrl(location);

        const details = [
            phoneHref && hasText(location.phone)
                ? `
                    <a
                        class="store-locator-infowindow-detail"
                        href="${escapeHtml(phoneHref)}"
                    >
                        <i class="fa-solid fa-phone"></i>
                        <span>
                            ${escapeHtml(location.phone)}
                        </span>
                    </a>
                `
                : '',

            email
                ? `
                    <a
                        class="store-locator-infowindow-detail"
                        href="mailto:${escapeHtml(email)}"
                    >
                        <i class="fa-regular fa-envelope"></i>
                        <span>
                            ${escapeHtml(email)}
                        </span>
                    </a>
                `
                : '',

            websiteUrl
                ? `
                    <a
                        class="store-locator-infowindow-detail"
                        href="${escapeHtml(websiteUrl)}"
                        target="_blank"
                        rel="noopener"
                    >
                        <i class="fa-solid fa-globe"></i>
                        <span>
                            ${escapeHtml(
                                String(
                                    location.website
                                ).trim()
                            )}
                        </span>
                    </a>
                `
                : ''
        ]
            .filter(Boolean)
            .join('');

        const actions = [
            phoneHref && hasText(location.phone)
                ? `
                    <a
                        class="store-locator-infowindow-action"
                        href="${escapeHtml(phoneHref)}"
                    >
                        <i class="fa-solid fa-phone"></i>
                        ${escapeHtml(
                            translate(
                                'call',
                                'Call'
                            )
                        )}
                    </a>
                `
                : '',

            email
                ? `
                    <a
                        class="store-locator-infowindow-action"
                        href="mailto:${escapeHtml(email)}"
                    >
                        <i class="fa-regular fa-envelope"></i>
                        ${escapeHtml(
                            translate(
                                'email',
                                'Email'
                            )
                        )}
                    </a>
                `
                : '',

            websiteUrl
                ? `
                    <a
                        class="store-locator-infowindow-action"
                        href="${escapeHtml(websiteUrl)}"
                        target="_blank"
                        rel="noopener"
                    >
                        <i class="fa-solid fa-globe"></i>
                        ${escapeHtml(
                            translate(
                                'website',
                                'Website'
                            )
                        )}
                    </a>
                `
                : '',

            mapUrl
                ? `
                    <a
                        class="store-locator-infowindow-action store-locator-infowindow-action-primary"
                        href="${escapeHtml(mapUrl)}"
                        target="_blank"
                        rel="noopener"
                    >
                        <i class="fa-solid fa-route"></i>
                        ${escapeHtml(
                            translate(
                                'directions',
                                'Directions'
                            )
                        )}
                    </a>
                `
                : ''
        ]
            .filter(Boolean)
            .join('');

        return `
            <article class="store-locator-infowindow-card">
                <header class="store-locator-infowindow-header">
                    <span
                        class="store-locator-infowindow-pin"
                        aria-hidden="true"
                    >
                        <i class="fa-solid fa-location-dot"></i>
                    </span>

                    <div class="store-locator-infowindow-heading">
                        <h3>${name}</h3>
                        ${distance}
                    </div>
                </header>

                <div class="store-locator-infowindow-body">
                    ${
                        address
                            ? `
                                <p class="store-locator-infowindow-address">
                                    ${address}
                                </p>
                            `
                            : ''
                    }

                    ${
                        details
                            ? `
                                <div class="store-locator-infowindow-details">
                                    ${details}
                                </div>
                            `
                            : ''
                    }
                </div>

                ${
                    actions
                        ? `
                            <footer class="store-locator-infowindow-footer">
                                ${actions}
                            </footer>
                        `
                        : ''
                }
            </article>
        `;
    }

    function selectLocation(
        locationId,
        options = {}
    ) {
        const marker = markers.get(
            String(locationId)
        );

        if (
            !marker
            || !map
            || !infoWindow
        ) {
            return;
        }

        const location =
            marker.__storeLocatorLocation;

        setActiveCard(
            locationId,
            options.scrollCard === true
        );

        infoWindow.setContent(
            infoWindowContent(location)
        );

        infoWindow.open({
            map,
            anchor: marker
        });

        map.panTo(
            marker.getPosition()
        );

        const targetZoom =
            options.zoom ?? 13;

        if (
            (map.getZoom() ?? 0) < targetZoom
            || options.forceZoom === true
        ) {
            map.setZoom(targetZoom);
        }
    }

    function buildUserMarker(position) {
        if (
            !map
            || !position
            || !window.google
        ) {
            return null;
        }

        return new google.maps.Marker({
            map,
            position,

            title: translate(
                'yourPosition',
                'Your location'
            ),

            zIndex: 9999,

            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 8,
                fillColor: '#0d6efd',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 3
            }
        });
    }

    function applyInitialViewport(
        bounds,
        userPosition
    ) {
        if (
            !map
            || initialBoundsApplied
        ) {
            return;
        }

        initialBoundsApplied = true;

        /*
        |--------------------------------------------------------------------------
        | Posizione browser
        |--------------------------------------------------------------------------
        |
        | Se l'utente ha scelto "Usa la mia posizione", la mappa deve restare
        | centrata sulla sua posizione. Non usiamo fitBounds() su tutti i
        | negozi perché eventuali risultati lontani allargherebbero troppo
        | la viewport.
        |
        */

        if (userPosition) {
            map.setCenter(
                userPosition
            );

            map.setZoom(9);

            return;
        }

        if (!bounds.isEmpty()) {
            map.fitBounds(
                bounds,
                64
            );
        }
    }

    function bindCards() {
        allCards().forEach(card => {
            card.addEventListener(
                'click',
                event => {
                    if (
                        event.target.closest(
                            'a, button'
                        )
                    ) {
                        return;
                    }

                    selectLocation(
                        card.dataset.locationId,
                        {
                            zoom: 13,
                            forceZoom: true
                        }
                    );
                }
            );
        });
    }

    function cardHtml(location) {
        const name = escapeHtml(
            location.name
            || translate(
                'defaultStoreName',
                'Store'
            )
        );

        const address = escapeHtml(
            location.address_line || ''
        );

        const distance =
            location.distance_km !== null
            && location.distance_km !== undefined
                ? `
                    <div class="small fw-semibold text-nowrap text-muted">
                        ${escapeHtml(
                            location.distance_km
                        )} km
                    </div>
                `
                : '';

        const phoneHref =
            telHref(location.phone);

        const email =
            hasText(location.email)
                ? String(
                    location.email
                ).trim()
                : null;

        const websiteUrl =
            normalizeExternalUrl(
                location.website
            );

        const mapUrl =
            directionsUrl(location);

        const actions = [
            phoneHref
            && hasText(location.phone)
                ? `
                    <a
                        class="btn btn-sm btn-light border rounded-pill px-3"
                        href="${escapeHtml(phoneHref)}"
                    >
                        ${escapeHtml(
                            translate(
                                'call',
                                'Call'
                            )
                        )}
                    </a>
                `
                : '',

            email
                ? `
                    <a
                        class="btn btn-sm btn-light border rounded-pill px-3"
                        href="mailto:${escapeHtml(email)}"
                    >
                        ${escapeHtml(
                            translate(
                                'email',
                                'Email'
                            )
                        )}
                    </a>
                `
                : '',

            websiteUrl
                ? `
                    <a
                        class="btn btn-sm btn-light border rounded-pill px-3"
                        href="${escapeHtml(websiteUrl)}"
                        target="_blank"
                        rel="noopener"
                    >
                        ${escapeHtml(
                            translate(
                                'website',
                                'Website'
                            )
                        )}
                    </a>
                `
                : '',

            mapUrl
                ? `
                    <a
                        class="btn btn-sm btn-outline-dark rounded-pill px-3"
                        href="${escapeHtml(mapUrl)}"
                        target="_blank"
                        rel="noopener"
                    >
                        ${escapeHtml(
                            translate(
                                'directions',
                                'Directions'
                            )
                        )}
                    </a>
                `
                : ''
        ]
            .filter(Boolean)
            .join('');

        return `
            <article
                class="store-locator-card border-bottom p-3 p-md-4"
                data-store-locator-card
                data-location-id="${escapeHtml(location.id)}"
            >
                <div class="d-flex gap-3">
                    <div
                        class="store-locator-pin flex-shrink-0 rounded-circle bg-dark text-white d-flex align-items-center justify-content-center"
                    >
                        <i class="fa-solid fa-location-dot"></i>
                    </div>

                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex justify-content-between gap-3 mb-1">
                            <h3 class="h6 fw-semibold mb-0 text-truncate">
                                ${name}
                            </h3>

                            ${distance}
                        </div>

                        ${
                            address
                                ? `
                                    <p class="small text-muted mb-3">
                                        ${address}
                                    </p>
                                `
                                : ''
                        }

                        ${
                            actions
                                ? `
                                    <div class="d-flex flex-wrap gap-2">
                                        ${actions}
                                    </div>
                                `
                                : ''
                        }
                    </div>
                </div>
            </article>
        `;
    }

    function updateResultCount(count) {
        const element =
            document.querySelector(
                '[data-store-locator-result-count]'
            );

        if (!element) {
            return;
        }

        const label =
            count === 1
                ? translate(
                    'storeSingular',
                    'store'
                )
                : translate(
                    'storePlural',
                    'stores'
                );

        element.textContent =
            `${count} ${label}`;
    }

    function renderLocations(locations) {
        const list =
            document.querySelector(
                '[data-store-locator-list]'
            );

        if (!list) {
            return;
        }

        if (
            !Array.isArray(locations)
            || locations.length === 0
        ) {
            list.innerHTML = `
                <div class="p-4 p-md-5 text-center text-muted">
                    <div
                        class="storefront-icon-56 rounded-circle bg-light border d-inline-flex align-items-center justify-content-center mb-3"
                    >
                        <i class="fa-regular fa-face-frown"></i>
                    </div>

                    <p class="mb-0 small">
                        ${escapeHtml(
                            translate(
                                'noSearchResults',
                                'No stores found.'
                            )
                        )}
                    </p>
                </div>
            `;

            updateResultCount(0);

            return;
        }

        list.innerHTML =
            locations
                .map(cardHtml)
                .join('');

        updateResultCount(
            locations.length
        );

        bindCards();
    }

    function clearStoreMarkers() {
        markers.forEach(marker => {
            marker.setMap(null);
        });

        markers = new Map();

        if (infoWindow) {
            infoWindow.close();
        }
    }

    function removeUserMarker() {
        if (!userMarker) {
            return;
        }

        userMarker.setMap(null);
        userMarker = null;
    }

    function showLocationsOnMap(
        locations,
        options = {}
    ) {
        if (
            !map
            || !window.google
        ) {
            return;
        }

        clearStoreMarkers();

        const bounds =
            new google.maps.LatLngBounds();

        const searchCenter =
            options.searchCenter
            && numberOrNull(
                options.searchCenter.lat
            ) !== null
            && numberOrNull(
                options.searchCenter.lng
            ) !== null
                ? {
                    lat: numberOrNull(
                        options.searchCenter.lat
                    ),
                    lng: numberOrNull(
                        options.searchCenter.lng
                    )
                }
                : null;

        let count = 0;

        locations.forEach(location => {
            const lat =
                numberOrNull(
                    location.latitude
                );

            const lng =
                numberOrNull(
                    location.longitude
                );

            if (
                lat === null
                || lng === null
            ) {
                return;
            }

            const marker =
                new google.maps.Marker({
                    map,

                    position: {
                        lat,
                        lng
                    },

                    title:
                        location.name
                        || translate(
                            'defaultStoreName',
                            'Store'
                        ),

                    zIndex: 100
                });

            marker.__storeLocatorLocation =
                location;

            marker.addListener(
                'click',
                () => {
                    selectLocation(
                        location.id,
                        {
                            zoom: 13,
                            scrollCard: true
                        }
                    );
                }
            );

            markers.set(
                String(location.id),
                marker
            );

            bounds.extend(
                marker.getPosition()
            );

            count += 1;
        });

        const mapElement =
            document.querySelector(
                '[data-store-locator-map]'
            );

        if (mapElement) {
            mapElement.dataset.markerCount =
                String(count);
        }

        if (
            searchCenter
            && count === 0
        ) {
            map.setCenter(
                searchCenter
            );

            map.setZoom(10);

            return;
        }

        if (
            count === 1
            && options.focusFirst === true
        ) {
            const first =
                locations.find(
                    location =>
                        markers.has(
                            String(
                                location.id
                            )
                        )
                );

            if (first) {
                selectLocation(
                    first.id,
                    {
                        zoom: 13,
                        forceZoom: true
                    }
                );
            }

            return;
        }

        if (count > 0) {
            if (searchCenter) {
                bounds.extend(
                    searchCenter
                );
            }

            map.fitBounds(
                bounds,
                64
            );
        }
    }

    function updateBrowserUrl(
        query,
        sku
    ) {
        const url =
            new URL(
                window.location.href
            );

        /*
        |--------------------------------------------------------------------------
        | Ricerca testuale
        |--------------------------------------------------------------------------
        |
        | Una località esplicita sostituisce eventuali coordinate provenienti
        | dalla geolocalizzazione browser.
        |
        | Se invece la ricerca contiene soltanto lo SKU, eventuali lat/lng
        | presenti nell'URL vengono mantenuti.
        |
        */

        if (hasText(query)) {
            url.searchParams.delete('lat');
            url.searchParams.delete('lng');
        }

        if (hasText(query)) {
            url.searchParams.set(
                'q',
                String(query).trim()
            );
        } else {
            url.searchParams.delete('q');
        }

        if (hasText(sku)) {
            url.searchParams.set(
                'sku',
                String(sku).trim()
            );
        } else {
            url.searchParams.delete('sku');
        }

        window.history.replaceState(
            {},
            '',
            url.toString()
        );
    }

    function resetBrowserUrl(sku = '') {
        const url =
            new URL(
                window.location.href
            );

        url.searchParams.delete('q');
        url.searchParams.delete('lat');
        url.searchParams.delete('lng');

        if (hasText(sku)) {
            url.searchParams.set(
                'sku',
                String(sku).trim()
            );
        } else {
            url.searchParams.delete('sku');
        }

        window.history.replaceState(
            {},
            '',
            url.toString()
        );
    }

    function bindStoreSearch() {
        const form =
            document.querySelector(
                '[data-store-locator-search-form]'
            );

        const input =
            document.querySelector(
                '[data-store-locator-search]'
            );

        const productInput =
            document.querySelector(
                '[data-store-locator-product]'
            );

        const clearButton =
            document.querySelector(
                '[data-store-locator-search-clear]'
            );

        const submitButton =
            document.querySelector(
                '[data-store-locator-search-submit]'
            );

        const status =
            document.querySelector(
                '[data-store-locator-search-status]'
            );

        const config =
            payload().search || {};

        const endpoint =
            String(
                config.endpoint || ''
            ).trim();

        if (
            !form
            || !input
            || !productInput
            || endpoint === ''
        ) {
            return;
        }

        const setStatus = (
            message,
            isError = false
        ) => {
            if (!status) {
                return;
            }

            status.textContent =
                message || '';

            status.classList.toggle(
                'text-danger',
                isError
            );

            status.classList.toggle(
                'text-muted',
                !isError
            );
        };

        const setLoading =
            loading => {
                input.disabled = loading;
                productInput.disabled = loading;

                if (submitButton) {
                    submitButton.disabled =
                        loading;
                }

                if (clearButton) {
                    clearButton.disabled =
                        loading;
                }
            };

        const runSearch =
            async (
                query,
                sku
            ) => {
                if (searchController) {
                    searchController.abort();
                }

                searchController =
                    new AbortController();

                setLoading(true);

                setStatus(
                    translate(
                        'searching',
                        'Searching…'
                    )
                );

                try {
                    const url =
                        new URL(
                            endpoint,
                            window.location.origin
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Limite risultati
                    |--------------------------------------------------------------------------
                    |
                    | Manteniamo lo stesso limite utilizzato dal caricamento
                    | server-side della pagina, evitando che la ricerca AJAX
                    | passi da 120 a 30 risultati.
                    |
                    */

                    url.searchParams.set(
                        'limit',
                        '120'
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Località / posizione browser
                    |--------------------------------------------------------------------------
                    |
                    | Se è presente una località inviamo soltanto q e lasciamo
                    | che il backend la geocodifichi.
                    |
                    | Se q è vuota ma nell'URL sono presenti lat/lng, manteniamo
                    | invece la posizione browser. Questo permette di cambiare
                    | lo SKU senza perdere l'ordinamento per distanza.
                    |
                    */

                    if (hasText(query)) {
                        url.searchParams.set(
                            'q',
                            String(query).trim()
                        );
                    } else {
                        const position =
                            userPositionFromQuery();

                        if (position) {
                            url.searchParams.set(
                                'lat',
                                position.lat
                            );

                            url.searchParams.set(
                                'lng',
                                position.lng
                            );
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Prodotto
                    |--------------------------------------------------------------------------
                    |
                    | Lo SKU viene letto dal campo visibile del form.
                    |
                    */

                    if (hasText(sku)) {
                        url.searchParams.set(
                            'sku',
                            String(sku).trim()
                        );
                    }

                    const response =
                        await fetch(
                            url.toString(),
                            {
                                headers: {
                                    'Accept':
                                        'application/json'
                                },

                                signal:
                                    searchController.signal
                            }
                        );

                    if (!response.ok) {
                        throw new Error(
                            `HTTP ${response.status}`
                        );
                    }

                    const data =
                        await response.json();

                    const locations =
                        Array.isArray(
                            data.items
                        )
                            ? data.items
                            : [];

                    const search =
                        data.search
                        && typeof data.search === 'object'
                            ? data.search
                            : {};

                    const product =
                        data.product
                        && typeof data.product === 'object'
                            ? data.product
                            : {};

                    const searchResolved =
                        search.resolved !== false;

                    const productResolved =
                        product.resolved !== false;

                    const searchLatitude =
                        numberOrNull(
                            search.latitude
                        );

                    const searchLongitude =
                        numberOrNull(
                            search.longitude
                        );

                    renderLocations(
                        locations
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Centro mappa dopo ricerca AJAX
                    |--------------------------------------------------------------------------
                    |
                    | Per una ricerca testuale il centro arriva dal geocoding.
                    |
                    | Per una ricerca basata sulla posizione browser manteniamo
                    | invece il marker utente e la viewport centrata sulla
                    | posizione corrente.
                    |
                    */

                    const browserPosition =
                        !hasText(query)
                            ? userPositionFromQuery()
                            : null;

                    if (browserPosition) {
                        removeUserMarker();

                        userMarker =
                            buildUserMarker(
                                browserPosition
                            );

                        showLocationsOnMap(
                            locations
                        );

                        map.setCenter(
                            browserPosition
                        );

                        map.setZoom(9);
                    } else {
                        removeUserMarker();

                        showLocationsOnMap(
                            locations,
                            {
                                focusFirst:
                                    locations.length === 1,

                                searchCenter:
                                    searchLatitude !== null
                                    && searchLongitude !== null
                                        ? {
                                            lat:
                                                searchLatitude,
                                            lng:
                                                searchLongitude
                                        }
                                        : null
                            }
                        );
                    }

                    updateBrowserUrl(
                        query,
                        sku
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | SKU inesistente
                    |--------------------------------------------------------------------------
                    |
                    | Uno SKU richiesto ma non risolto non deve mai trasformarsi
                    | in una ricerca generica di tutti i punti vendita.
                    |
                    */

                    if (
                        hasText(sku)
                        && !productResolved
                    ) {
                        setStatus(
                            translate(
                                'invalidProduct',
                                'The product code entered is not valid.'
                            ),
                            true
                        );

                        return;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Località non risolta
                    |--------------------------------------------------------------------------
                    */

                    if (
                        hasText(query)
                        && !searchResolved
                    ) {
                        setStatus(
                            translate(
                                'noSearchResults',
                                'No stores found.'
                            ),
                            true
                        );

                        return;
                    }

                    if (
                        locations.length === 0
                    ) {
                        setStatus(
                            translate(
                                'noSearchResults',
                                'No stores found.'
                            )
                        );

                        return;
                    }

                    setStatus('');
                } catch (error) {
                    if (
                        error.name ===
                        'AbortError'
                    ) {
                        return;
                    }

                    console.warn(
                        'Store locator search failed',
                        error
                    );

                    setStatus(
                        translate(
                            'searchError',
                            'Unable to complete the search.'
                        ),
                        true
                    );
                } finally {
                    setLoading(false);
                }
            };

        /*
        |--------------------------------------------------------------------------
        | Submit esplicito
        |--------------------------------------------------------------------------
        |
        | Casi supportati:
        |
        | - solo località
        | - solo prodotto
        | - località + prodotto
        | - posizione browser + prodotto
        | - nessun filtro
        |
        */

        form.addEventListener(
            'submit',
            event => {
                event.preventDefault();

                const query =
                    input.value.trim();

                const sku =
                    productInput.value.trim();

                if (clearButton) {
                    clearButton.hidden =
                        query === '';
                }

                if (
                    query !== ''
                    && query.length < 2
                ) {
                    setStatus(
                        translate(
                            'searchMinLength',
                            'Enter at least 2 characters for the location.'
                        ),
                        true
                    );

                    input.focus();

                    return;
                }

                if (
                    query === ''
                    && sku === ''
                ) {
                    window.location.href =
                        form.action;

                    return;
                }

                runSearch(
                    query,
                    sku
                );
            }
        );

        input.addEventListener(
            'input',
            () => {
                if (clearButton) {
                    clearButton.hidden =
                        input.value.trim() === '';
                }

                setStatus('');
            }
        );

        productInput.addEventListener(
            'input',
            () => {
                setStatus('');
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Reset località
        |--------------------------------------------------------------------------
        |
        | La X cancella esclusivamente la località.
        | L'eventuale SKU digitato viene mantenuto.
        |
        */

        if (clearButton) {
            clearButton.addEventListener(
                'click',
                () => {
                    if (searchController) {
                        searchController.abort();
                    }

                    input.value = '';
                    clearButton.hidden = true;

                    setStatus('');

                    const sku =
                        productInput.value.trim();

                    resetBrowserUrl(
                        sku
                    );

                    const url =
                        new URL(
                            form.action,
                            window.location.origin
                        );

                    if (hasText(sku)) {
                        url.searchParams.set(
                            'sku',
                            sku
                        );
                    }

                    window.location.href =
                        url.toString();
                }
            );
        }
    }

    function bindGeolocationButtons() {
        document
            .querySelectorAll(
                '[data-store-locator-geolocate]'
            )
            .forEach(button => {
                button.addEventListener(
                    'click',
                    () => {
                        if (
                            !navigator.geolocation
                        ) {
                            return;
                        }

                        button.disabled = true;

                        navigator.geolocation
                            .getCurrentPosition(
                                position => {
                                    const url =
                                        new URL(
                                            window.location.href
                                        );

                                    /*
                                    |--------------------------------------------------------------------------
                                    | Geolocalizzazione browser
                                    |--------------------------------------------------------------------------
                                    |
                                    | La posizione browser sostituisce una
                                    | eventuale ricerca testuale precedente.
                                    |
                                    | Lo SKU viene letto prima dal campo
                                    | visibile, così anche uno SKU appena
                                    | digitato viene mantenuto.
                                    |
                                    */

                                    url.searchParams.delete(
                                        'q'
                                    );

                                    const productInput =
                                        document.querySelector(
                                            '[data-store-locator-product]'
                                        );

                                    const sku =
                                        productInput
                                            ? productInput.value.trim()
                                            : (
                                                url.searchParams.get('sku')
                                                || ''
                                            ).trim();

                                    if (sku !== '') {
                                        url.searchParams.set(
                                            'sku',
                                            sku
                                        );
                                    } else {
                                        url.searchParams.delete(
                                            'sku'
                                        );
                                    }

                                    url.searchParams.set(
                                        'lat',
                                        position.coords.latitude
                                    );

                                    url.searchParams.set(
                                        'lng',
                                        position.coords.longitude
                                    );

                                    window.location.href =
                                        url.toString();
                                },

                                () => {
                                    button.disabled =
                                        false;
                                },

                                {
                                    enableHighAccuracy:
                                        true,

                                    timeout:
                                        9000,

                                    maximumAge:
                                        300000
                                }
                            );
                    }
                );
            });
    }

    window.initStoreLocatorMap =
        function () {
            const mapElement =
                document.querySelector(
                    '[data-store-locator-map]'
                );

            const locations =
                payload().locations;

            if (
                !mapElement
                || !window.google
            ) {
                return;
            }

            map =
                new google.maps.Map(
                    mapElement,
                    {
                        center: {
                            lat: 43.7696,
                            lng: 11.2558
                        },

                        zoom: 6,

                        mapTypeControl:
                            false,

                        streetViewControl:
                            false,

                        fullscreenControl:
                            true,

                        clickableIcons:
                            false,

                        styles: [
                            {
                                featureType:
                                    'poi',

                                stylers: [
                                    {
                                        visibility:
                                            'off'
                                    }
                                ]
                            },
                            {
                                featureType:
                                    'transit',

                                stylers: [
                                    {
                                        visibility:
                                            'off'
                                    }
                                ]
                            },
                            {
                                featureType:
                                    'road',

                                elementType:
                                    'labels.icon',

                                stylers: [
                                    {
                                        visibility:
                                            'off'
                                    }
                                ]
                            }
                        ]
                    }
                );

            infoWindow =
                new google.maps.InfoWindow();

            markers =
                new Map();

            initialBoundsApplied =
                false;

            const bounds =
                new google.maps.LatLngBounds();

            const userPosition =
                userPositionFromQuery();

            /*
            |--------------------------------------------------------------------------
            | Centro della ricerca testuale
            |--------------------------------------------------------------------------
            |
            | Quando la pagina viene caricata direttamente con ?q=..., il
            | backend ha già geocodificato la località e inserito le coordinate
            | nel payload.
            |
            */

            const searchConfig =
                payload().search || {};

            const resolvedSearchLatitude =
                numberOrNull(
                    searchConfig.latitude
                );

            const resolvedSearchLongitude =
                numberOrNull(
                    searchConfig.longitude
                );

            const resolvedSearchPosition =
                !userPosition
                && hasText(searchConfig.query)
                && resolvedSearchLatitude !== null
                && resolvedSearchLongitude !== null
                    ? {
                        lat:
                            resolvedSearchLatitude,
                        lng:
                            resolvedSearchLongitude
                    }
                    : null;

            let markerCount = 0;

            if (userPosition) {
                userMarker =
                    buildUserMarker(
                        userPosition
                    );

                bounds.extend(
                    userPosition
                );
            }

            locations.forEach(
                location => {
                    const lat =
                        numberOrNull(
                            location.latitude
                        );

                    const lng =
                        numberOrNull(
                            location.longitude
                        );

                    if (
                        lat === null
                        || lng === null
                    ) {
                        return;
                    }

                    const marker =
                        new google.maps.Marker({
                            map,

                            position: {
                                lat,
                                lng
                            },

                            title:
                                location.name
                                || translate(
                                    'defaultStoreName',
                                    'Store'
                                ),

                            zIndex: 100
                        });

                    marker.__storeLocatorLocation =
                        location;

                    marker.addListener(
                        'click',
                        () => {
                            selectLocation(
                                location.id,
                                {
                                    zoom: 13,
                                    scrollCard: true
                                }
                            );
                        }
                    );

                    markers.set(
                        String(
                            location.id
                        ),
                        marker
                    );

                    bounds.extend(
                        marker.getPosition()
                    );

                    markerCount += 1;
                }
            );

            if (resolvedSearchPosition) {
                bounds.extend(
                    resolvedSearchPosition
                );
            }

            mapElement.dataset.markerCount =
                String(markerCount);

            bindCards();

            google.maps.event
                .addListenerOnce(
                    map,
                    'idle',
                    () => {
                        if (
                            resolvedSearchPosition
                            && markerCount === 0
                        ) {
                            initialBoundsApplied =
                                true;

                            map.setCenter(
                                resolvedSearchPosition
                            );

                            map.setZoom(10);

                            return;
                        }

                        applyInitialViewport(
                            bounds,
                            userPosition
                        );
                    }
                );

            window.setTimeout(
                () => {
                    if (
                        resolvedSearchPosition
                        && markerCount === 0
                    ) {
                        if (
                            !initialBoundsApplied
                        ) {
                            initialBoundsApplied =
                                true;

                            map.setCenter(
                                resolvedSearchPosition
                            );

                            map.setZoom(10);
                        }

                        return;
                    }

                    applyInitialViewport(
                        bounds,
                        userPosition
                    );
                },
                250
            );
        };

    document.addEventListener(
        'DOMContentLoaded',
        () => {
            bindGeolocationButtons();
            bindStoreSearch();
        }
    );
})();
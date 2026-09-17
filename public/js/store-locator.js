(function () {
    let map = null;
    let infoWindow = null;
    let markers = new Map();
    let userMarker = null;
    let initialBoundsApplied = false;
    let payloadCache = null;

    function payload() {
        if (payloadCache !== null) {
            return payloadCache;
        }

        const payloadElement = document.querySelector('[data-store-locator-payload]');

        if (!payloadElement) {
            payloadCache = {
                locations: Array.isArray(window.storeLocatorData) ? window.storeLocatorData : [],
                i18n: window.storeLocatorI18n || {},
                search: {}
            };

            return payloadCache;
        }

        try {
            const parsed = JSON.parse(payloadElement.textContent || '{}');

            payloadCache = {
                locations: Array.isArray(parsed.locations) ? parsed.locations : [],
                i18n: parsed.i18n && typeof parsed.i18n === 'object' ? parsed.i18n : {},
                search: parsed.search && typeof parsed.search === 'object' ? parsed.search : {}
            };
        } catch (error) {
            console.warn('Invalid store locator payload', error);
            payloadCache = { locations: [], i18n: {}, search: {} };
        }

        return payloadCache;
    }

    function translate(key, fallback) {
        const messages = payload().i18n;
        const value = messages[key];

        return typeof value === 'string' && value.trim() !== '' ? value : fallback;
    }

    function numberOrNull(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        const number = Number(value);
        return Number.isFinite(number) ? number : null;
    }

    function queryCoordinate(name) {
        return numberOrNull(new URLSearchParams(window.location.search).get(name));
    }

    function userPositionFromQuery() {
        const lat = queryCoordinate('lat');
        const lng = queryCoordinate('lng');

        if (lat === null || lng === null) {
            return null;
        }

        return { lat, lng };
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
        const phone = String(value ?? '').replace(/[^\d+]/g, '');

        return phone !== '' ? `tel:${phone}` : null;
    }

    function normalizeExternalUrl(value) {
        const url = String(value ?? '').trim();

        if (url === '') {
            return null;
        }

        return /^https?:\/\//i.test(url) ? url : `https://${url.replace(/^\/+/, '')}`;
    }

    function directionsUrl(location) {
        const lat = numberOrNull(location.latitude);
        const lng = numberOrNull(location.longitude);

        if (lat === null || lng === null) {
            return null;
        }

        return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(`${lat},${lng}`)}`;
    }

    function allCards() {
        return Array.from(document.querySelectorAll('[data-store-locator-card]'));
    }

    function setActiveCard(locationId, scrollIntoView = false) {
        const id = String(locationId);
        let activeCard = null;

        allCards().forEach(card => {
            const isActive = String(card.dataset.locationId) === id;
            card.classList.toggle('active', isActive);

            if (isActive) {
                activeCard = card;
            }
        });

        if (scrollIntoView && activeCard) {
            activeCard.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function infoWindowContent(location) {
        const name = escapeHtml(location.name || translate('defaultStoreName', 'Store'));
        const address = escapeHtml(location.address_line || '');
        const distance = location.distance_km !== null && location.distance_km !== undefined
            ? `<span class="store-locator-infowindow-distance">${escapeHtml(location.distance_km)} km</span>`
            : '';
        const phoneHref = telHref(location.phone);
        const email = hasText(location.email) ? String(location.email).trim() : null;
        const websiteUrl = normalizeExternalUrl(location.website);
        const mapUrl = directionsUrl(location);

        const details = [
            phoneHref && hasText(location.phone)
                ? `<a class="store-locator-infowindow-detail" href="${escapeHtml(phoneHref)}"><i class="fa-solid fa-phone"></i><span>${escapeHtml(location.phone)}</span></a>`
                : '',
            email
                ? `<a class="store-locator-infowindow-detail" href="mailto:${escapeHtml(email)}"><i class="fa-regular fa-envelope"></i><span>${escapeHtml(email)}</span></a>`
                : '',
            websiteUrl
                ? `<a class="store-locator-infowindow-detail" href="${escapeHtml(websiteUrl)}" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i><span>${escapeHtml(String(location.website).trim())}</span></a>`
                : ''
        ].filter(Boolean).join('');

        const actions = [
            phoneHref && hasText(location.phone)
                ? `<a class="store-locator-infowindow-action" href="${escapeHtml(phoneHref)}"><i class="fa-solid fa-phone"></i>${escapeHtml(translate('call', 'Call'))}</a>`
                : '',
            email
                ? `<a class="store-locator-infowindow-action" href="mailto:${escapeHtml(email)}"><i class="fa-regular fa-envelope"></i>${escapeHtml(translate('email', 'Email'))}</a>`
                : '',
            websiteUrl
                ? `<a class="store-locator-infowindow-action" href="${escapeHtml(websiteUrl)}" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i>${escapeHtml(translate('website', 'Website'))}</a>`
                : '',
            mapUrl
                ? `<a class="store-locator-infowindow-action store-locator-infowindow-action-primary" href="${escapeHtml(mapUrl)}" target="_blank" rel="noopener"><i class="fa-solid fa-route"></i>${escapeHtml(translate('directions', 'Directions'))}</a>`
                : ''
        ].filter(Boolean).join('');

        return `
            <article class="store-locator-infowindow-card">
                <header class="store-locator-infowindow-header">
                    <span class="store-locator-infowindow-pin" aria-hidden="true">
                        <i class="fa-solid fa-location-dot"></i>
                    </span>
                    <div class="store-locator-infowindow-heading">
                        <h3>${name}</h3>
                        ${distance}
                    </div>
                </header>
                <div class="store-locator-infowindow-body">
                    ${address ? `<p class="store-locator-infowindow-address">${address}</p>` : ''}
                    ${details ? `<div class="store-locator-infowindow-details">${details}</div>` : ''}
                </div>
                ${actions ? `<footer class="store-locator-infowindow-footer">${actions}</footer>` : ''}
            </article>
        `;
    }

    function selectLocation(locationId, options = {}) {
        const marker = markers.get(String(locationId));

        if (!marker || !map || !infoWindow) {
            return;
        }

        const location = marker.__storeLocatorLocation;

        setActiveCard(locationId, options.scrollCard === true);

        infoWindow.setContent(infoWindowContent(location));
        infoWindow.open({ map, anchor: marker });

        map.panTo(marker.getPosition());

        const targetZoom = options.zoom ?? 13;
        if ((map.getZoom() ?? 0) < targetZoom || options.forceZoom === true) {
            map.setZoom(targetZoom);
        }
    }

    function buildUserMarker(position) {
        if (!map || !position || !window.google) {
            return null;
        }

        return new google.maps.Marker({
            map,
            position,
            title: translate('yourPosition', 'Your location'),
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

    function applyInitialViewport(bounds, userPosition) {
        if (!map || initialBoundsApplied) {
            return;
        }

        initialBoundsApplied = true;

        if (userPosition) {
            map.setCenter(userPosition);
            map.setZoom(9);
            return;
        }

        if (!bounds.isEmpty()) {
            map.fitBounds(bounds, 64);
        }
    }

    function bindCards() {
        allCards().forEach(card => {
            card.addEventListener('click', event => {
                if (event.target.closest('a, button')) {
                    return;
                }

                selectLocation(card.dataset.locationId, {
                    zoom: 13,
                    forceZoom: true
                });
            });
        });
    }

    function cardHtml(location) {
        const name = escapeHtml(location.name || translate('defaultStoreName', 'Store'));
        const address = escapeHtml(location.address_line || '');
        const distance = location.distance_km !== null && location.distance_km !== undefined
            ? `<div class="small fw-semibold text-nowrap text-muted">${escapeHtml(location.distance_km)} km</div>`
            : '';
        const phoneHref = telHref(location.phone);
        const email = hasText(location.email) ? String(location.email).trim() : null;
        const websiteUrl = normalizeExternalUrl(location.website);
        const mapUrl = directionsUrl(location);

        const actions = [
            phoneHref && hasText(location.phone)
                ? `<a class="btn btn-sm btn-light border rounded-pill px-3" href="${escapeHtml(phoneHref)}">${escapeHtml(translate('call', 'Call'))}</a>`
                : '',
            email
                ? `<a class="btn btn-sm btn-light border rounded-pill px-3" href="mailto:${escapeHtml(email)}">${escapeHtml(translate('email', 'Email'))}</a>`
                : '',
            websiteUrl
                ? `<a class="btn btn-sm btn-light border rounded-pill px-3" href="${escapeHtml(websiteUrl)}" target="_blank" rel="noopener">${escapeHtml(translate('website', 'Website'))}</a>`
                : '',
            mapUrl
                ? `<a class="btn btn-sm btn-outline-dark rounded-pill px-3" href="${escapeHtml(mapUrl)}" target="_blank" rel="noopener">${escapeHtml(translate('directions', 'Directions'))}</a>`
                : ''
        ].filter(Boolean).join('');

        return `
            <article class="store-locator-card border-bottom p-3 p-md-4" data-store-locator-card data-location-id="${escapeHtml(location.id)}">
                <div class="d-flex gap-3">
                    <div class="store-locator-pin flex-shrink-0 rounded-circle bg-dark text-white d-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex justify-content-between gap-3 mb-1">
                            <h3 class="h6 fw-semibold mb-0 text-truncate">${name}</h3>
                            ${distance}
                        </div>
                        ${address ? `<p class="small text-muted mb-3">${address}</p>` : ''}
                        ${actions ? `<div class="d-flex flex-wrap gap-2">${actions}</div>` : ''}
                    </div>
                </div>
            </article>`;
    }

    function updateResultCount(count) {
        const element = document.querySelector('[data-store-locator-result-count]');
        if (!element) return;
        const label = count === 1
            ? translate('storeSingular', 'store')
            : translate('storePlural', 'stores');
        element.textContent = `${count} ${label}`;
    }

    function renderLocations(locations) {
        const list = document.querySelector('[data-store-locator-list]');
        if (!list) return;

        if (!Array.isArray(locations) || locations.length === 0) {
            list.innerHTML = `<div class="p-4 p-md-5 text-center text-muted"><i class="fa-regular fa-face-frown mb-3"></i><p class="mb-0 small">${escapeHtml(translate('noSearchResults', 'No stores found.'))}</p></div>`;
            updateResultCount(0);
            return;
        }

        list.innerHTML = locations.map(cardHtml).join('');
        updateResultCount(locations.length);
        bindCards();
    }

    function clearStoreMarkers() {
        markers.forEach(marker => marker.setMap(null));
        markers = new Map();
    }

    function showLocationsOnMap(locations, focusFirst = false) {
        if (!map || !window.google) return;

        clearStoreMarkers();
        const bounds = new google.maps.LatLngBounds();
        let count = 0;

        locations.forEach(location => {
            const lat = numberOrNull(location.latitude);
            const lng = numberOrNull(location.longitude);
            if (lat === null || lng === null) return;

            const marker = new google.maps.Marker({
                map,
                position: { lat, lng },
                title: location.name || translate('defaultStoreName', 'Store'),
                zIndex: 100
            });
            marker.__storeLocatorLocation = location;
            marker.addListener('click', () => selectLocation(location.id, { zoom: 13, scrollCard: true }));
            markers.set(String(location.id), marker);
            bounds.extend(marker.getPosition());
            count += 1;
        });

        const mapElement = document.querySelector('[data-store-locator-map]');
        if (mapElement) mapElement.dataset.markerCount = String(count);

        if (count === 1 && focusFirst) {
            const first = locations.find(location => markers.has(String(location.id)));
            if (first) selectLocation(first.id, { zoom: 13, forceZoom: true });
        } else if (count > 0) {
            map.fitBounds(bounds, 64);
        }
    }

    function bindStoreSearch() {
        const input = document.querySelector('[data-store-locator-search]');
        const clearButton = document.querySelector('[data-store-locator-search-clear]');
        const status = document.querySelector('[data-store-locator-search-status]');
        const config = payload().search || {};
        const endpoint = String(config.endpoint || '').trim();
        const initialLocations = Array.isArray(payload().locations) ? payload().locations : [];
        let timer = null;
        let controller = null;

        if (!input || endpoint === '') return;

        const setStatus = (message, isError = false) => {
            if (!status) return;
            status.textContent = message || '';
            status.classList.toggle('text-danger', isError);
            status.classList.toggle('text-muted', !isError);
        };

        const runSearch = async () => {
            const query = input.value.trim();
            if (clearButton) clearButton.hidden = query === '';

            if (query.length < 2) {
                if (controller) controller.abort();
                renderLocations(initialLocations);
                showLocationsOnMap(initialLocations);
                setStatus('');
                return;
            }

            if (controller) controller.abort();
            controller = new AbortController();
            setStatus(translate('searching', 'Searching…'));

            try {
                const url = new URL(endpoint, window.location.origin);
                url.searchParams.set('q', query);
                url.searchParams.set('limit', '30');
                if (hasText(config.sku)) url.searchParams.set('sku', config.sku);

                const userPosition = userPositionFromQuery();
                if (userPosition) {
                    url.searchParams.set('lat', userPosition.lat);
                    url.searchParams.set('lng', userPosition.lng);
                }

                const response = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const data = await response.json();
                const locations = Array.isArray(data.items) ? data.items : [];
                renderLocations(locations);
                showLocationsOnMap(locations, locations.length === 1);
                setStatus('');
            } catch (error) {
                if (error.name === 'AbortError') return;
                console.warn('Store locator search failed', error);
                setStatus(translate('searchError', 'Unable to complete the search.'), true);
            }
        };

        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(runSearch, 300);
        });

        if (clearButton) {
            clearButton.addEventListener('click', () => {
                input.value = '';
                clearButton.hidden = true;
                renderLocations(initialLocations);
                showLocationsOnMap(initialLocations);
                setStatus('');
                input.focus();
            });
        }
    }

    function bindGeolocationButtons() {
        document.querySelectorAll('[data-store-locator-geolocate]').forEach(button => {
            button.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    return;
                }

                button.disabled = true;

                navigator.geolocation.getCurrentPosition(position => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('lat', position.coords.latitude);
                    url.searchParams.set('lng', position.coords.longitude);
                    window.location.href = url.toString();
                }, () => {
                    button.disabled = false;
                }, {
                    enableHighAccuracy: true,
                    timeout: 9000,
                    maximumAge: 300000
                });
            });
        });
    }

    window.initStoreLocatorMap = function () {
        const mapElement = document.querySelector('[data-store-locator-map]');
        const locations = payload().locations;

        if (!mapElement || !window.google) {
            return;
        }

        map = new google.maps.Map(mapElement, {
            center: { lat: 43.7696, lng: 11.2558 },
            zoom: 6,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            clickableIcons: false,
            styles: [
                { featureType: 'poi', stylers: [{ visibility: 'off' }] },
                { featureType: 'transit', stylers: [{ visibility: 'off' }] },
                { featureType: 'road', elementType: 'labels.icon', stylers: [{ visibility: 'off' }] }
            ]
        });

        infoWindow = new google.maps.InfoWindow();
        markers = new Map();
        initialBoundsApplied = false;

        const bounds = new google.maps.LatLngBounds();
        const userPosition = userPositionFromQuery();
        let markerCount = 0;

        if (userPosition) {
            userMarker = buildUserMarker(userPosition);
            bounds.extend(userPosition);
        }

        locations.forEach(location => {
            const lat = numberOrNull(location.latitude);
            const lng = numberOrNull(location.longitude);

            if (lat === null || lng === null) {
                return;
            }

            const marker = new google.maps.Marker({
                map,
                position: { lat, lng },
                title: location.name || translate('defaultStoreName', 'Store'),
                zIndex: 100
            });

            marker.__storeLocatorLocation = location;

            marker.addListener('click', () => {
                selectLocation(location.id, {
                    zoom: 13,
                    scrollCard: true
                });
            });

            markers.set(String(location.id), marker);
            bounds.extend(marker.getPosition());
            markerCount += 1;
        });

        mapElement.dataset.markerCount = String(markerCount);

        bindCards();

        google.maps.event.addListenerOnce(map, 'idle', () => {
            applyInitialViewport(bounds, userPosition);
        });

        window.setTimeout(() => {
            applyInitialViewport(bounds, userPosition);
        }, 250);
    };

    document.addEventListener('DOMContentLoaded', () => {
        bindGeolocationButtons();
        bindStoreSearch();
    });
})();

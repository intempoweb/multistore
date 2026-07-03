(function () {
    let map = null;
    let infoWindow = null;
    let markers = new Map();
    let userMarker = null;
    let initialBoundsApplied = false;

    function translate(key, fallback) {
        const messages = window.storeLocatorI18n || {};
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
            ? `<br><small>${escapeHtml(location.distance_km)} km</small>`
            : '';

        return `
            <div style="min-width:220px">
                <strong>${name}</strong><br>
                <span style="color:#6b7280">${address}</span>
                ${distance}
            </div>
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
        const locations = Array.isArray(window.storeLocatorData) ? window.storeLocatorData : [];

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
                title: location.name || translate('defaultStoreName', 'Store')
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
        });

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
    });
})();

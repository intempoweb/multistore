

window.initStoreLocatorMap = function () {
    const mapElement = document.querySelector('[data-store-locator-map]');
    const locations = Array.isArray(window.storeLocatorData) ? window.storeLocatorData : [];

    if (!mapElement || !window.google || !locations.length) {
        return;
    }

    const map = new google.maps.Map(mapElement, {
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

    const bounds = new google.maps.LatLngBounds();
    const infoWindow = new google.maps.InfoWindow();
    const markers = new Map();

    locations.forEach(location => {
        if (location.latitude === null || location.longitude === null) {
            return;
        }

        const marker = new google.maps.Marker({
            map,
            position: {
                lat: Number(location.latitude),
                lng: Number(location.longitude)
            },
            title: location.name || 'Punto vendita'
        });

        marker.addListener('click', () => {
            document.querySelectorAll('[data-store-locator-card]').forEach(c => c.classList.remove('active'));
            document.querySelector('[data-location-id="' + location.id + '"]')?.classList.add('active');

            infoWindow.setContent(`
                <div style="min-width:220px">
                    <strong>${location.name ?? 'Punto vendita'}</strong><br>
                    <span style="color:#6b7280">${location.address_line ?? ''}</span>
                    ${location.distance_km !== null ? `<br><small>${location.distance_km} km</small>` : ''}
                </div>
            `);

            infoWindow.open({ map, anchor: marker });
            map.panTo(marker.getPosition());
            map.setZoom(Math.max(map.getZoom(), 13));
        });

        markers.set(String(location.id), marker);
        bounds.extend(marker.getPosition());
    });

    if (!bounds.isEmpty()) {
        map.fitBounds(bounds, 64);
    }

    document.querySelectorAll('[data-store-locator-card]').forEach(card => {
        card.addEventListener('click', () => {
            const marker = markers.get(card.dataset.locationId);
            if (marker) {
                google.maps.event.trigger(marker, 'click');
            }
        });
    });
};

document.addEventListener('DOMContentLoaded', () => {
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
});
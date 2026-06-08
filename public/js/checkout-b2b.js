document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('.checkout-page');

    if (!root || root.dataset.checkoutMode !== 'b2b') {
        return;
    }

    const storageKey = root.dataset.shippingStorageKey || 'checkout_shipping_address_current';

    const cards = Array.from(document.querySelectorAll('[data-shipping-card]'));
    const radios = Array.from(document.querySelectorAll('[data-shipping-radio]'));

    if (!cards.length || !radios.length) {
        return;
    }

    function readStoredAddressId() {
        try {
            return window.localStorage.getItem(storageKey);
        } catch (error) {
            return null;
        }
    }

    function writeStoredAddressId(value) {
        try {
            window.localStorage.setItem(storageKey, String(value));
        } catch (error) {
            // localStorage non disponibile.
        }
    }

    function syncCouponForms(value) {
        document.querySelectorAll('[data-shipping-address-hidden]').forEach(function (input) {
            input.value = value || '';
        });
    }

    function syncShippingSelection() {
        let selectedValue = null;

        cards.forEach(function (card) {
            const radio = card.querySelector('[data-shipping-radio]');
            const badge = card.querySelector('[data-shipping-selected-badge]');
            const selected = Boolean(radio && radio.checked);

            if (selected && radio) {
                selectedValue = radio.value;
            }

            card.classList.toggle('border-primary', selected);
            card.classList.toggle('shadow-sm', selected);
            card.classList.toggle('border-light-subtle', !selected);

            if (badge) {
                badge.classList.toggle('d-none', !selected);
            }
        });

        syncCouponForms(selectedValue);
    }

    function selectRadio(radio, persist) {
        if (!radio) {
            return;
        }

        radio.checked = true;

        if (persist) {
            writeStoredAddressId(radio.value);
        }

        radio.dispatchEvent(new Event('change', { bubbles: true }));
        syncShippingSelection();
    }

    const storedAddressId = readStoredAddressId();

    if (storedAddressId) {
        const storedRadio = radios.find(function (radio) {
            return String(radio.value) === String(storedAddressId);
        });

        if (storedRadio) {
            selectRadio(storedRadio, false);
        }
    } else {
        const checkedRadio = radios.find(function (radio) {
            return radio.checked;
        });

        if (checkedRadio) {
            writeStoredAddressId(checkedRadio.value);
            syncCouponForms(checkedRadio.value);
        }
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function (event) {
            const radio = card.querySelector('[data-shipping-radio]');

            if (!radio) {
                return;
            }

            if (event.target !== radio) {
                event.preventDefault();
            }

            selectRadio(radio, true);
        });
    });

    radios.forEach(function (radio) {
        radio.addEventListener('click', function (event) {
            event.stopPropagation();
            selectRadio(radio, true);
        });

        radio.addEventListener('change', function () {
            if (radio.checked) {
                writeStoredAddressId(radio.value);
            }

            syncShippingSelection();
        });
    });

    syncShippingSelection();
});
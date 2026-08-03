(function () {
    function todayKey() {
        return new Date().toISOString().slice(0, 10);
    }

    function storageKey(popup) {
        return popup.dataset.popupKey || '';
    }

    function optOutKey(popup) {
        var key = storageKey(popup);

        return key ? key + ':optout' : '';
    }

    function hasOptedOut(popup) {
        var key = optOutKey(popup);

        return key ? localStorage.getItem(key) === '1' : false;
    }

    function shouldShow(popup) {
        var key = storageKey(popup);
        var frequency = popup.dataset.popupFrequency || 'once_session';

        if (hasOptedOut(popup)) {
            return false;
        }

        if (!key || frequency === 'always') {
            return true;
        }

        if (frequency === 'once_session') {
            return sessionStorage.getItem(key) !== '1';
        }

        if (frequency === 'once_day') {
            return localStorage.getItem(key) !== todayKey();
        }

        if (frequency === 'once_forever') {
            return localStorage.getItem(key) !== '1';
        }

        return true;
    }

    function shouldOptOut(popup) {
        var input = popup.querySelector('[data-storefront-popup-optout]');

        return input ? input.checked : false;
    }

    function markSeen(popup) {
        var key = storageKey(popup);
        var frequency = popup.dataset.popupFrequency || 'once_session';

        if (!key) {
            return;
        }

        if (shouldOptOut(popup)) {
            localStorage.setItem(optOutKey(popup), '1');
        }

        if (frequency === 'always') {
            return;
        }

        if (frequency === 'once_session') {
            sessionStorage.setItem(key, '1');
            return;
        }

        if (frequency === 'once_day') {
            localStorage.setItem(key, todayKey());
            return;
        }

        if (frequency === 'once_forever') {
            localStorage.setItem(key, '1');
        }
    }

    function closePopup(popup) {
        markSeen(popup);
        popup.classList.remove('is-visible');
        window.setTimeout(function () {
            popup.hidden = true;
        }, 180);
    }

    function openPopup(popup) {
        popup.hidden = false;
        window.requestAnimationFrame(function () {
            popup.classList.add('is-visible');
        });
    }

    function initPopup(popup) {
        if (!shouldShow(popup)) {
            return;
        }

        popup.querySelectorAll('[data-storefront-popup-close]').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                closePopup(popup);
            });
        });

        popup.querySelectorAll('[data-storefront-popup-cta]').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                markSeen(popup);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !popup.hidden) {
                closePopup(popup);
            }
        });

        var delay = parseInt(popup.dataset.popupDelay || '0', 10);
        window.setTimeout(function () {
            openPopup(popup);
        }, Number.isFinite(delay) ? delay : 0);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-storefront-popup]').forEach(initPopup);
    });
})();

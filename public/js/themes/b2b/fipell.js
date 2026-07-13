(function () {
    'use strict';

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        callback();
    };

    ready(() => {
        const header = document.querySelector('[data-fipell-header]');

        if (!header) return;

        const scrollThreshold = 72;
        let ticking = false;

        const updateHeaderState = () => {
            header.classList.toggle('is-scrolled', window.scrollY > scrollThreshold);
            ticking = false;
        };

        updateHeaderState();

        window.addEventListener('scroll', () => {
            if (ticking) return;

            ticking = true;
            window.requestAnimationFrame(updateHeaderState);
        }, { passive: true });
    });
}());

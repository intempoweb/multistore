(function () {
    const AUTOPLAY_INTERVAL = 3200;

    const nextScrollLeft = function (track) {
        const slide = track.querySelector('.ready-product-slide');
        const step = slide ? slide.getBoundingClientRect().width + 28 : 260;
        const max = track.scrollWidth - track.clientWidth - 4;

        if (track.scrollLeft >= max) {
            return 0;
        }

        return Math.min(track.scrollLeft + step, max);
    };

    const previousScrollLeft = function (track) {
        const slide = track.querySelector('.ready-product-slide');
        const step = slide ? slide.getBoundingClientRect().width + 28 : 260;

        return Math.max(track.scrollLeft - step, 0);
    };

    const initProductTabs = function (section) {
        const tabs = Array.from(section.querySelectorAll('[data-ready-tab]'));
        const panels = Array.from(section.querySelectorAll('[data-ready-panel]'));
        let timer = null;
        let paused = false;

        const activePanel = function () {
            return panels.find((panel) => panel.classList.contains('is-active')) || panels[0] || null;
        };

        const activeTrack = function () {
            return activePanel()?.querySelector('[data-ready-product-track]') || null;
        };

        const updateArrows = function () {
            panels.forEach(function (panel) {
                const track = panel.querySelector('[data-ready-product-track]');
                const prev = panel.querySelector('[data-ready-products-prev]');
                const next = panel.querySelector('[data-ready-products-next]');
                const canScroll = track && track.scrollWidth > track.clientWidth + 4;

                if (prev) prev.hidden = !canScroll;
                if (next) next.hidden = !canScroll;
            });
        };

        const stop = function () {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        };

        const start = function () {
            stop();

            timer = window.setInterval(function () {
                if (paused || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    return;
                }

                const track = activeTrack();

                if (!track || track.scrollWidth <= track.clientWidth) {
                    return;
                }

                track.scrollTo({
                    left: nextScrollLeft(track),
                    behavior: 'smooth',
                });
            }, AUTOPLAY_INTERVAL);
        };

        const activate = function (key) {
            tabs.forEach(function (tab) {
                const isActive = tab.dataset.readyTab === key;
                tab.classList.toggle('is-active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            panels.forEach(function (panel) {
                const isActive = panel.dataset.readyPanel === key;
                panel.classList.toggle('is-active', isActive);
                panel.hidden = !isActive;

                if (isActive) {
                    const track = panel.querySelector('[data-ready-product-track]');
                    if (track) track.scrollLeft = 0;
                }
            });

            updateArrows();
            start();
        };

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activate(tab.dataset.readyTab);
            });
        });

        panels.forEach(function (panel) {
            const track = panel.querySelector('[data-ready-product-track]');
            const prev = panel.querySelector('[data-ready-products-prev]');
            const next = panel.querySelector('[data-ready-products-next]');

            if (prev && track) {
                prev.addEventListener('click', function () {
                    paused = true;
                    track.scrollTo({ left: previousScrollLeft(track), behavior: 'smooth' });
                });
            }

            if (next && track) {
                next.addEventListener('click', function () {
                    paused = true;
                    track.scrollTo({ left: nextScrollLeft(track), behavior: 'smooth' });
                });
            }

            if (track) {
                track.addEventListener('scroll', updateArrows, { passive: true });
            }
        });

        section.addEventListener('mouseenter', function () { paused = true; });
        section.addEventListener('mouseleave', function () { paused = false; });
        section.addEventListener('focusin', function () { paused = true; });
        section.addEventListener('focusout', function () { paused = false; });

        updateArrows();
        window.addEventListener('resize', updateArrows, { passive: true });
        start();
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ready-product-tabs]').forEach(initProductTabs);
    });
})();

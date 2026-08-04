(function () {
    const AUTOPLAY_INTERVAL = 3200;
    const TOPBAR_INTERVAL = 4000;
    const TOPBAR_FADE_DURATION = 350;

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

    const initHeaderGlass = function () {
        const header = document.querySelector('.ready-header');

        if (!header || !document.body.classList.contains('ready-home-page')) {
            return;
        }

        const sync = function () {
            header.classList.toggle('is-glass', window.scrollY > 18);
        };

        sync();
        window.addEventListener('scroll', sync, { passive: true });
    };

    const initTopbarMessages = function () {
        const message = document.getElementById('ready-shipping-message');

        if (!message) {
            return;
        }

        let messages = [];

        try {
            messages = JSON.parse(message.dataset.messages || '[]');
        } catch (error) {
            return;
        }

        messages = messages.filter(function (item) {
            return typeof item === 'string' && item.trim() !== '';
        });

        if (messages.length < 2) {
            return;
        }

        let currentIndex = 0;
        let interval = null;
        let changeTimeout = null;

        const showNextMessage = function () {
            message.classList.add('is-hidden');

            changeTimeout = window.setTimeout(function () {
                currentIndex = (currentIndex + 1) % messages.length;
                message.textContent = messages[currentIndex];
                message.classList.remove('is-hidden');
            }, TOPBAR_FADE_DURATION);
        };

        const stop = function () {
            if (interval !== null) {
                window.clearInterval(interval);
                interval = null;
            }

            if (changeTimeout !== null) {
                window.clearTimeout(changeTimeout);
                changeTimeout = null;
            }
        };

        const start = function () {
            stop();

            if (document.hidden) {
                return;
            }

            interval = window.setInterval(showNextMessage, TOPBAR_INTERVAL);
        };

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stop();
                return;
            }

            message.classList.remove('is-hidden');
            start();
        });

        start();
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
                if (
                    paused ||
                    window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ) {
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

                    if (track) {
                        track.scrollLeft = 0;
                    }
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

                    track.scrollTo({
                        left: previousScrollLeft(track),
                        behavior: 'smooth',
                    });
                });
            }

            if (next && track) {
                next.addEventListener('click', function () {
                    paused = true;

                    track.scrollTo({
                        left: nextScrollLeft(track),
                        behavior: 'smooth',
                    });
                });
            }

            if (track) {
                track.addEventListener('scroll', updateArrows, {
                    passive: true,
                });
            }
        });

        section.addEventListener('mouseenter', function () {
            paused = true;
        });

        section.addEventListener('mouseleave', function () {
            paused = false;
        });

        section.addEventListener('focusin', function () {
            paused = true;
        });

        section.addEventListener('focusout', function () {
            paused = false;
        });

        updateArrows();

        window.addEventListener('resize', updateArrows, {
            passive: true,
        });

        start();
    };

    document.addEventListener('DOMContentLoaded', function () {
        initHeaderGlass();
        initTopbarMessages();

        document
            .querySelectorAll('[data-ready-product-tabs]')
            .forEach(initProductTabs);
    });
})();
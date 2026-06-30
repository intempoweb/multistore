(function () {
    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    ready(() => {
        const searchPanel = document.querySelector('[data-intempo-search-panel]');

        document.querySelectorAll('[data-intempo-search-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!searchPanel) return;

                const nextHidden = !searchPanel.hidden;
                searchPanel.hidden = nextHidden;

                if (!nextHidden) {
                    searchPanel.querySelector('input')?.focus();
                }
            });
        });

        const closeSearch = () => {
            if (searchPanel) searchPanel.hidden = true;
        };

        const megaItems = Array.from(document.querySelectorAll('[data-intempo-mega-item]'));
        const closeMegas = (except = null) => {
            megaItems.forEach((item) => {
                if (item === except) return;

                item.classList.remove('is-open');
                item.querySelector('[data-intempo-mega-trigger]')?.setAttribute('aria-expanded', 'false');
            });
        };

        megaItems.forEach((item) => {
            const trigger = item.querySelector('[data-intempo-mega-trigger]');
            const panel = item.querySelector('[data-intempo-mega-panel]');

            if (!trigger || !panel) return;

            const open = () => {
                closeMegas(item);
                closeSearch();
                item.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            };

            const close = () => {
                item.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            };

            item.addEventListener('mouseenter', open);
            item.addEventListener('focusin', open);
            item.addEventListener('mouseleave', close);
            item.addEventListener('focusout', (event) => {
                if (!item.contains(event.relatedTarget)) close();
            });

            trigger.addEventListener('click', (event) => {
                if (window.matchMedia('(hover: none)').matches) {
                    if (!item.classList.contains('is-open')) {
                        event.preventDefault();
                        open();
                    }
                }
            });
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-intempo-header]')) {
                closeMegas();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMegas();
                closeSearch();
            }
        });

        const hero = document.querySelector('[data-intempo-home-hero]');
        const slides = hero ? Array.from(hero.querySelectorAll('[data-intempo-hero-slide]')) : [];
        let current = 0;
        let autoplay = null;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const show = (next) => {
            if (!slides.length) return;

            current = (next + slides.length) % slides.length;
            slides.forEach((slide, index) => {
                const active = index === current;
                slide.classList.toggle('is-active', active);

                const video = slide.querySelector('video');
                if (video) {
                    active ? video.play().catch(() => {}) : video.pause();
                }
            });

            const label = hero.querySelector('[data-intempo-hero-current]');
            if (label) label.textContent = String(current + 1);
        };

        const stopAutoplay = () => {
            if (autoplay) window.clearInterval(autoplay);
            autoplay = null;
        };

        const startAutoplay = () => {
            if (reducedMotion || slides.length <= 1) return;
            stopAutoplay();
            autoplay = window.setInterval(() => show(current + 1), 5200);
        };

        hero?.querySelector('[data-intempo-hero-prev]')?.addEventListener('click', () => {
            show(current - 1);
            startAutoplay();
        });

        hero?.querySelector('[data-intempo-hero-next]')?.addEventListener('click', () => {
            show(current + 1);
            startAutoplay();
        });

        hero?.addEventListener('mouseenter', stopAutoplay);
        hero?.addEventListener('mouseleave', startAutoplay);

        show(0);
        startAutoplay();
    });
})();

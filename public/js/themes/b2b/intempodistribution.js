(function () {
    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    ready(() => {
        const header = document.querySelector('[data-intempo-b2b-header]');
        const searchPanel = document.querySelector('[data-intempo-b2b-search-panel]');
        const megaItems = Array.from(document.querySelectorAll('[data-intempo-b2b-mega-item]'));

        const closeSearch = () => {
            if (searchPanel) searchPanel.hidden = true;
        };

        const closeMegas = (except = null) => {
            megaItems.forEach((item) => {
                if (item === except) return;
                item.classList.remove('is-open');
                item.querySelector('[data-intempo-b2b-mega-trigger]')?.setAttribute('aria-expanded', 'false');
            });
        };

        document.querySelectorAll('[data-intempo-b2b-search-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!searchPanel) return;
                const willOpen = searchPanel.hidden;
                closeMegas();
                searchPanel.hidden = !willOpen;
                if (willOpen) searchPanel.querySelector('input')?.focus();
            });
        });

        megaItems.forEach((item) => {
            const trigger = item.querySelector('[data-intempo-b2b-mega-trigger]');
            const panel = item.querySelector('[data-intempo-b2b-mega-panel]');
            if (!trigger || !panel) return;

            const open = () => {
                closeSearch();
                closeMegas(item);
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
                if (window.matchMedia('(hover: none)').matches && !item.classList.contains('is-open')) {
                    event.preventDefault();
                    open();
                }
            });
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-intempo-b2b-header]')) {
                closeSearch();
                closeMegas();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSearch();
                closeMegas();
            }
        });

        if (header) {
            const update = () => header.classList.toggle('is-scrolled', window.scrollY > 24);
            update();
            window.addEventListener('scroll', update, { passive: true });
        }
    });
})();

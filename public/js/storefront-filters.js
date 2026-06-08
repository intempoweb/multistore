(() => {
    const FORM_SELECTOR = '[data-storefront-filters-form]';
    const INPUT_SELECTOR = '[data-storefront-filter-input]';
    const DEFAULT_PRODUCTS_SELECTOR = '.storefront-product-results';
    const DEFAULT_SIDEBAR_SELECTOR = '.storefront-sidebar-wrapper';
    const MOBILE_QUERY = window.matchMedia('(max-width: 991.98px)');

    const buildFilteredUrl = (form) => {
        const url = new URL(form.action || window.location.href, window.location.origin);
        url.search = '';
        url.hash = '';

        form.querySelectorAll(`${INPUT_SELECTOR}:checked`).forEach((input) => {
            if (input.dataset.attributeSlug && input.dataset.valueSlug) {
                url.searchParams.append(input.dataset.attributeSlug, input.dataset.valueSlug);
            }
        });

        return url;
    };

    const activeFilterCount = () =>
        document.querySelectorAll(`${DEFAULT_SIDEBAR_SELECTOR} ${INPUT_SELECTOR}:checked`).length;

    const updateMobileButton = () => {
        const badge = document.querySelector('[data-storefront-filter-count]');
        const count = activeFilterCount();

        if (badge) {
            badge.textContent = count;
            badge.classList.toggle('d-none', count <= 0);
        }
    };

    const closeFilters = () => {
        document.body.classList.remove('storefront-filter-drawer-open');
        document.body.style.overflow = '';
    };

    const openFilters = () => {
        document.body.classList.add('storefront-filter-drawer-open');
        document.body.style.overflow = 'hidden';
    };

    const ensureMobileFilterUI = () => {
        if (!document.querySelector(DEFAULT_SIDEBAR_SELECTOR)) return;

        if (!document.querySelector('[data-storefront-filter-backdrop]')) {
            const backdrop = document.createElement('button');
            backdrop.type = 'button';
            backdrop.className = 'storefront-filter-backdrop';
            backdrop.setAttribute('data-storefront-filter-backdrop', '');
            backdrop.setAttribute('aria-label', 'Chiudi filtri');
            backdrop.addEventListener('click', closeFilters);
            document.body.appendChild(backdrop);
        }

        if (!document.querySelector('[data-storefront-filter-mobile-trigger]')) {
            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'storefront-filter-mobile-trigger';
            trigger.setAttribute('data-storefront-filter-mobile-trigger', '');
            trigger.innerHTML = `
                <i class="fa-solid fa-sliders"></i>
                <span>Filtri</span>
                <strong class="d-none" data-storefront-filter-count>0</strong>
            `;
            trigger.addEventListener('click', openFilters);
            document.body.appendChild(trigger);
        }

        updateMobileButton();
    };

    const ensureFormMobileActions = (form) => {
        if (form.querySelector('[data-storefront-filter-mobile-actions]')) return;

        const actions = document.createElement('div');
        actions.className = 'storefront-filter-mobile-actions';
        actions.setAttribute('data-storefront-filter-mobile-actions', '');
        actions.innerHTML = `
            <button type="button" class="btn btn-dark w-100" data-storefront-filter-close>
                Vedi prodotti
            </button>
        `;

        actions.querySelector('[data-storefront-filter-close]').addEventListener('click', () => {
            closeFilters();

            document.querySelector(DEFAULT_PRODUCTS_SELECTOR)?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        });

        form.appendChild(actions);
    };

    const setLoading = (form, active) => {
        const loading = form.querySelector('[data-storefront-filter-loading]');

        if (loading) {
            loading.classList.toggle('d-none', !active);
        }

        form.classList.toggle('opacity-75', active);
        form.style.pointerEvents = active ? 'none' : '';
    };

    const init = () => {
        ensureMobileFilterUI();

        document.querySelectorAll(FORM_SELECTOR).forEach((form) => {
            ensureFormMobileActions(form);

            if (form.dataset.filtersBound === '1') return;

            form.dataset.filtersBound = '1';

            let timer = null;

            const run = () => {
                clearTimeout(timer);

                timer = setTimeout(async () => {
                    const url = buildFilteredUrl(form);
                    const productSelector = form.dataset.storefrontFiltersTarget || DEFAULT_PRODUCTS_SELECTOR;
                    const sidebarSelector = form.dataset.storefrontSidebarTarget || DEFAULT_SIDEBAR_SELECTOR;

                    setLoading(form, true);

                    try {
                        const response = await fetch(url.toString(), {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) throw new Error('Filter request failed');

                        const html = await response.text();
                        const doc = new DOMParser().parseFromString(html, 'text/html');

                        const currentProducts = document.querySelector(productSelector);
                        const nextProducts = doc.querySelector(productSelector);

                        const currentSidebar = document.querySelector(sidebarSelector);
                        const nextSidebar = doc.querySelector(sidebarSelector);

                        if (!currentProducts || !nextProducts) {
                            throw new Error('Products target missing');
                        }

                        currentProducts.replaceWith(nextProducts);

                        if (currentSidebar && nextSidebar) {
                            currentSidebar.replaceWith(nextSidebar);
                        }

                        window.history.pushState({}, '', url.toString());

                        init();
                        updateMobileButton();
                    } catch (error) {
                        window.location.href = url.toString();
                    } finally {
                        setLoading(form, false);
                    }
                }, 120);
            };

            form.querySelectorAll(INPUT_SELECTOR).forEach((input) => {
                input.addEventListener('change', run);
            });

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                run();
            });
        });
    };

    document.addEventListener('DOMContentLoaded', init);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeFilters();
    });

    window.addEventListener('popstate', () => window.location.reload());
})();
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('[data-storefront-search-form]');
    if (!forms.length) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const toInt = (value, fallback = 1) => {
        const parsed = parseInt(value, 10);
        return Number.isNaN(parsed) ? fallback : parsed;
    };

    const debounce = (callback, delay = 240) => {
        let timer = null;

        return (...args) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => callback(...args), delay);
        };
    };

    const parseResponse = async (response) => {
        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            return response.json();
        }

        return { message: await response.text() };
    };

    const normalizeQty = (input) => {
        if (!input) return;

        const min = Math.max(1, toInt(input.dataset.qtyMin || input.min || '1', 1));
        const step = Math.max(1, toInt(input.dataset.qtyStep || input.step || '1', 1));

        let value = toInt(input.value || min, min);

        if (value <= 0) value = min;
        value = Math.max(value, min);

        if (step > 1) {
            const offset = value - min;
            if (offset > 0) value = min + (Math.ceil(offset / step) * step);
        }

        input.value = String(value);
    };

    const refreshAfterCartChange = async (payload = {}) => {
        document.dispatchEvent(new CustomEvent('cart:updated', { detail: payload }));
        document.dispatchEvent(new CustomEvent('storefront:cart-updated', { detail: payload }));

        if (typeof window.loadMiniCart === 'function') {
            await window.loadMiniCart();
        }

        if (window.location.pathname.includes('/cart') || window.location.pathname.includes('/checkout')) {
            window.location.reload();
        }
    };

    const firstReadable = (...values) => {
        return values
            .map((value) => String(value ?? '').trim())
            .find((value) => value !== '') || '';
    };

    const uniqueReadable = (...values) => {
        return values
            .flatMap((value) => Array.isArray(value) ? value : [value])
            .map((value) => String(value ?? '').trim())
            .filter((value, index, list) => value !== '' && list.indexOf(value) === index);
    };

    const truncateText = (value, maxLength = 120) => {
        const text = String(value ?? '')
            .replace(/<[^>]*>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        if (text.length <= maxLength) return text;

        return `${text.slice(0, maxLength).replace(/\s+\S*$/, '').trim()}…`;
    };

    forms.forEach((form) => {
        const input = form.querySelector('[data-storefront-search-input], [data-search-input]');
        const clearButton = form.querySelector('[data-storefront-search-clear], [data-search-clear]');
        const suggestions = form.querySelector('[data-storefront-search-suggestions], [data-search-suggestions]');
        const suggestionsInner = form.querySelector('[data-storefront-search-suggestions-inner], [data-search-suggestions-inner]');

        const suggestUrl = form.dataset.suggestUrl || form.dataset.searchSuggestUrl || document.body.dataset.searchSuggestUrl || '';
        const searchUrl = form.dataset.searchUrl || document.body.dataset.searchUrl || form.action;
        const cartAddUrl = form.dataset.cartAddUrl || document.body.dataset.cartAddUrl || '';
        const minChars = toInt(form.dataset.searchMinChars || '2', 2);

        let abortController = null;
        let activeIndex = -1;

        if (!input || !suggestions || !suggestionsInner || !suggestUrl) return;

        const hideSuggestions = () => {
            suggestions.classList.add('d-none');
            suggestions.classList.remove('is-open');
            form.classList.remove('is-open');
            suggestions.style.display = 'none';
            input.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        };

        const showSuggestions = () => {
            suggestions.classList.remove('d-none');
            suggestions.classList.add('is-open');
            form.classList.add('is-open');
            suggestions.style.display = 'block';
            suggestions.style.zIndex = '9999';
            input.setAttribute('aria-expanded', 'true');
        };

        const renderState = (message, className = 'storefront-search-suggestions-empty') => {
            suggestionsInner.innerHTML = `<div class="${className}">${escapeHtml(message)}</div>`;
            showSuggestions();
        };

        const itemImage = (item) => item.thumbnail || item.image || '';

        const renderSuggestions = (payload, query) => {
            const items = Array.isArray(payload.items) ? payload.items : [];

            if (!items.length) {
                renderState('Nessun suggerimento trovato.');
                return;
            }

            const allResultsUrl = payload.search_url || `${searchUrl}?q=${encodeURIComponent(query)}`;

            suggestionsInner.innerHTML = `
                <div class="storefront-search-suggestions-header">
                    <p class="storefront-search-suggestions-title">Suggerimenti</p>
                    <span class="storefront-search-suggestions-count">${items.length} risultati</span>
                </div>

                <div class="storefront-search-suggest-list">
                    ${items.map((item, index) => {
                        const sku = firstReadable(item.product_sku, item.sku);
                        const name = firstReadable(item.name, sku);
                        const description = truncateText(firstReadable(
                            item.description,
                            item.short_description
                        ), 90);
                        const image = itemImage(item);

                        const category = firstReadable(
                            item.category_label,
                            item.category_path,
                            item.category
                        );

                        const attributes = uniqueReadable(
                            item.color,
                            item.format
                        );

                        const qtyMin = Math.max(1, toInt(item.quantity_min || item.min_order_qty || 1, 1));
                        const qtyStep = Math.max(1, toInt(item.quantity_step || item.pack_multiple || 1, 1));
                        const canAddToCart = Boolean(cartAddUrl && sku);

                        return `
                            <div class="storefront-search-suggest-item" role="option" data-search-suggestion-item data-search-suggestion-index="${index}">
                                <a href="${escapeHtml(item.url)}" class="storefront-search-suggest-link">
                                    <div class="storefront-search-suggest-thumb">
                                        ${
                                            image
                                                ? `<img src="${escapeHtml(image)}" alt="${escapeHtml(name)}" loading="lazy">`
                                                : `<i class="fa-solid fa-box"></i>`
                                        }
                                    </div>

                                    <div class="storefront-search-suggest-body">
                                        <span class="storefront-search-suggest-title">${escapeHtml(name)}</span>
                                        ${description ? `<span class="storefront-search-suggest-description">${escapeHtml(description)}</span>` : ''}

                                        <div class="storefront-search-suggest-meta">
                                            <span>SKU ${escapeHtml(sku)}</span>
                                            ${category ? `<span class="storefront-search-suggest-category">Categoria: ${escapeHtml(category)}</span>` : ''}
                                            ${attributes.length ? `<span class="storefront-search-suggest-attributes">${escapeHtml(attributes.join(' / '))}</span>` : ''}
                                        </div>
                                    </div>

                                    <div class="storefront-search-suggest-price">
                                        ${item.price ? escapeHtml(item.price) : ''}
                                    </div>
                                </a>

                                ${
                                    canAddToCart
                                        ? `
                                            <div class="storefront-search-suggest-cart" data-search-suggest-cart-box>
                                                <input type="hidden" data-search-suggest-sku value="${escapeHtml(sku)}">

                                                <label class="storefront-search-suggest-qty-label">
                                                    Q.tà
                                                    <input
                                                        type="number"
                                                        data-search-suggest-qty
                                                        data-qty-min="${qtyMin}"
                                                        data-qty-step="${qtyStep}"
                                                        value="${qtyMin}"
                                                        min="${qtyMin}"
                                                        step="${qtyStep}"
                                                    >
                                                </label>

                                                <button
                                                    type="button"
                                                    class="storefront-search-suggest-cart-btn"
                                                    data-search-suggest-cart-btn
                                                    aria-label="Aggiungi al carrello"
                                                >
                                                    <i class="fa-solid fa-cart-plus"></i>
                                                </button>

                                                <div class="storefront-search-suggest-min">
                                                    Min. ${qtyMin}${qtyStep > 1 ? ` · Step ${qtyStep}` : ''}
                                                </div>
                                            </div>
                                        `
                                        : ''
                                }
                            </div>
                        `;
                    }).join('')}
                </div>

                <div class="storefront-search-suggestions-footer">
                    <a href="${escapeHtml(allResultsUrl)}" class="storefront-search-suggest-all">Vedi tutti i risultati</a>
                </div>
            `;

            showSuggestions();
        };

        const fetchSuggestions = debounce(async () => {
            const query = input.value.trim();

            clearButton?.classList.toggle('d-none', query === '');

            if (query.length < minChars) {
                hideSuggestions();
                return;
            }

            if (abortController) abortController.abort();

            abortController = new AbortController();
            renderState('Ricerca in corso...', 'storefront-search-suggestions-loading');

            try {
                const url = new URL(suggestUrl, window.location.origin);
                url.searchParams.set('q', query);

                const response = await fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: abortController.signal,
                    credentials: 'same-origin',
                });

                if (!response.ok) throw new Error(`Errore suggerimenti (${response.status})`);

                renderSuggestions(await response.json(), query);
            } catch (error) {
                if (error.name === 'AbortError') return;
                console.error('[search] errore suggerimenti:', error);
                renderState('Suggerimenti non disponibili.');
            }
        }, 240);

        suggestionsInner.addEventListener('click', async function (event) {
            const button = event.target.closest('[data-search-suggest-cart-btn]');
            if (!button) return;

            event.preventDefault();
            event.stopPropagation();

            const box = button.closest('[data-search-suggest-cart-box]');
            const sku = box?.querySelector('[data-search-suggest-sku]')?.value || '';
            const qtyInput = box?.querySelector('[data-search-suggest-qty]');

            if (!cartAddUrl || !sku || !qtyInput) return;

            normalizeQty(qtyInput);

            const formData = new FormData();
            formData.append('sku', sku);
            formData.append('qty', qtyInput.value);

            button.disabled = true;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

            try {
                const response = await fetch(cartAddUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    body: formData,
                    credentials: 'same-origin',
                });

                const payload = await parseResponse(response);

                if (!response.ok) {
                    throw new Error(payload.message || `Errore carrello (${response.status})`);
                }

                button.innerHTML = '<i class="fa-solid fa-check"></i>';
                await refreshAfterCartChange(payload);
            } catch (error) {
                console.error('[search] errore add to cart:', error);
                button.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            } finally {
                window.setTimeout(() => {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                }, 1200);
            }
        });

        suggestionsInner.addEventListener('change', (event) => {
            const qtyInput = event.target.closest('[data-search-suggest-qty]');
            if (qtyInput) normalizeQty(qtyInput);
        });

        suggestionsInner.addEventListener('blur', (event) => {
            const qtyInput = event.target.closest('[data-search-suggest-qty]');
            if (qtyInput) normalizeQty(qtyInput);
        }, true);

        input.addEventListener('input', fetchSuggestions);

        input.addEventListener('focus', () => {
            if (input.value.trim().length >= minChars) fetchSuggestions();
        });

        input.addEventListener('keydown', function (event) {
            const items = Array.from(suggestions.querySelectorAll('[data-search-suggestion-item]'));

            if (!items.length || suggestions.classList.contains('d-none')) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                activeIndex = activeIndex >= items.length - 1 ? 0 : activeIndex + 1;
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = activeIndex <= 0 ? items.length - 1 : activeIndex - 1;
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                items[activeIndex].querySelector('a')?.click();
                return;
            } else if (event.key === 'Escape') {
                hideSuggestions();
                return;
            } else {
                return;
            }

            items.forEach((item, index) => {
                item.classList.toggle('is-active', index === activeIndex);
                item.classList.toggle('active', index === activeIndex);
            });

            items[activeIndex]?.scrollIntoView({ block: 'nearest' });
        });

        clearButton?.addEventListener('click', function () {
            input.value = '';
            clearButton.classList.add('d-none');
            hideSuggestions();
            input.focus();
        });

        form.addEventListener('submit', function (event) {
            const query = input.value.trim();

            if (query.length < minChars) {
                event.preventDefault();
                input.focus();
                hideSuggestions();
            }
        });

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) hideSuggestions();
        });
    });
});

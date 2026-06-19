document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const minicartUrl = document.body.dataset.minicartUrl || '';
    const isB2B = document.body.dataset.storefrontSiteType === 'b2b';

    const minicartContainer = document.querySelector('[data-minicart-container]');
    const minicartOffcanvasElement = document.getElementById('storefrontMinicart');

    const minicartOffcanvas = minicartOffcanvasElement && window.bootstrap
        ? bootstrap.Offcanvas.getOrCreateInstance(minicartOffcanvasElement)
        : null;

    function toInteger(value, fallback = 1) {
        const parsed = parseInt(value, 10);

        return Number.isNaN(parsed) ? fallback : parsed;
    }

    function formatCount(value) {
        return Number(value || 0).toLocaleString('it-IT', {
            maximumFractionDigits: 0
        });
    }

    function formatPrice(value) {
        if (value === '' || value === null || value === undefined || Number.isNaN(Number(value))) {
            return '—';
        }

        return '€ ' + Number(value).toLocaleString('it-IT', {
            minimumFractionDigits: 3,
            maximumFractionDigits: 3
        });
    }

    function normalizeQty(input) {
        if (!input) return;

        const min = Math.max(1, toInteger(input.dataset.qtyMin || input.min, 1));
        const step = Math.max(1, toInteger(input.dataset.qtyStep || input.step, 1));

        let value = toInteger(input.value, min);

        if (value < min) {
            value = min;
        }

        if (step > 1) {
            const offset = value - min;

            if (offset > 0) {
                value = min + Math.ceil(offset / step) * step;
            }
        }

        input.value = String(value);
    }

    async function parseResponse(response) {
        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            return response.json();
        }

        return {
            message: await response.text()
        };
    }

    async function refreshMinicart(open = false) {
        if (!minicartUrl || !minicartContainer) return;

        try {
            const response = await fetch(minicartUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Errore refresh minicart');
            }

            minicartContainer.innerHTML = await response.text();

            const minicart = minicartContainer.querySelector('[data-minicart]');
            const count = Number(minicart?.dataset.cartCount || 0);

            document.querySelectorAll('[data-minicart-count-badge], [data-cart-count-badge]').forEach((badge) => {
                badge.textContent = formatCount(count);
                badge.classList.toggle('d-none', count <= 0);
            });

            if (open && minicartOffcanvas) {
                minicartOffcanvas.show();
            }
        } catch (error) {
            console.error('Errore aggiornamento minicart:', error);
        }
    }

    function showFeedback(card, message, type = 'success') {
        const feedback = card.querySelector('[data-product-card-feedback]');

        if (!feedback) return;

        feedback.classList.remove(
            'd-none',
            'text-success',
            'text-danger',
            'text-muted',
            'is-success',
            'is-error'
        );

        feedback.classList.add(type === 'error' ? 'text-danger' : 'text-success');
        feedback.classList.add(type === 'error' ? 'is-error' : 'is-success');
        feedback.textContent = message;

        window.clearTimeout(Number(feedback.dataset.timeoutId || 0));

        feedback.dataset.timeoutId = String(window.setTimeout(() => {
            feedback.classList.add('d-none');
            feedback.textContent = '';
        }, 2800));
    }

    function setWishlistButtonState(button, added) {
        if (!button) return;

        const icon = button.querySelector('[data-product-card-wishlist-icon], i');
        const label = button.querySelector('[data-product-card-wishlist-label]');
        const text = added ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti';

        button.classList.toggle('is-active', added);
        button.setAttribute('aria-pressed', added ? 'true' : 'false');
        button.setAttribute('aria-label', text);

        if (label) {
            label.textContent = text;
        }

        if (icon) {
            icon.classList.toggle('fa-solid', added);
            icon.classList.toggle('fa-regular', !added);
            icon.classList.add('fa-heart');
            icon.classList.remove('fa-spinner', 'fa-spin');
        }
    }

    function setButtonLoading(button, loading, loadingHtml = '<i class="fa-solid fa-spinner fa-spin"></i>') {
        if (!button) return '';

        if (loading) {
            const originalHtml = button.innerHTML;

            button.disabled = true;
            button.dataset.originalHtml = originalHtml;
            button.innerHTML = loadingHtml;

            return originalHtml;
        }

        button.disabled = false;

        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }

        return '';
    }

    document.querySelectorAll('[data-product-card]').forEach(function (card) {
        const variants = card.querySelectorAll('[data-product-card-variant]');
        const addToCartForm = card.querySelector('[data-product-card-add-to-cart-form]');
        const wishlistButton = card.querySelector('[data-product-card-wishlist-toggle]');

        const skuInput = card.querySelector('[data-product-card-sku]');
        const skuLabel = card.querySelector('[data-product-card-sku-label]');
        const barcodeLabel = card.querySelector('[data-product-card-barcode-label]');
        const barcodeRow = card.querySelector('[data-product-card-barcode-row]');
        const links = card.querySelectorAll('[data-product-card-link]');
        const image = card.querySelector('[data-product-card-image]');
        const imageLink = card.querySelector('[data-product-card-image-link]');
        let hoverImage = card.querySelector('[data-product-card-hover-image]');
        const priceNode = card.querySelector('[data-product-card-price]');

        const qtyInput = card.querySelector('[data-product-card-qty]');
        const qtyMinus = card.querySelector('[data-product-card-qty-minus]');
        const qtyPlus = card.querySelector('[data-product-card-qty-plus]');
        const qtyMinLabel = card.querySelector('[data-product-card-qty-min-label]');
        const packNote = card.querySelector('[data-product-card-pack-note]');
        const packLabel = card.querySelector('[data-product-card-pack-multiple-label]');

        function setQtyRules(min, step, pack) {
            if (!qtyInput) return;

            const qtyMin = Math.max(1, toInteger(min, 1));
            const qtyStep = Math.max(1, toInteger(step, qtyMin));
            const packMultiple = Math.max(1, toInteger(pack, 1));

            qtyInput.min = String(qtyMin);
            qtyInput.step = String(qtyStep);
            qtyInput.dataset.qtyMin = String(qtyMin);
            qtyInput.dataset.qtyStep = String(qtyStep);

            if (qtyMinLabel) {
                qtyMinLabel.textContent = qtyMin.toLocaleString('it-IT');
            }

            if (packLabel) {
                packLabel.textContent = packMultiple.toLocaleString('it-IT');
            }

            if (packNote) {
                packNote.classList.toggle('d-none', packMultiple <= 1);
            }

            normalizeQty(qtyInput);
        }

        function changeQty(direction) {
            if (!qtyInput) return;

            normalizeQty(qtyInput);

            const current = Math.max(1, toInteger(qtyInput.value || qtyInput.min, 1));
            const min = Math.max(1, toInteger(qtyInput.dataset.qtyMin || qtyInput.min, 1));
            const step = Math.max(1, toInteger(qtyInput.dataset.qtyStep || qtyInput.step, 1));

            qtyInput.value = String(direction > 0 ? current + step : Math.max(min, current - step));

            normalizeQty(qtyInput);
            qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function setHoverImage(hoverSrc, primarySrc = '') {
            if (!imageLink) return;

            const normalizedHoverSrc = String(hoverSrc || '').trim();
            const normalizedPrimarySrc = String(primarySrc || image?.getAttribute('src') || '').trim();
            const hasValidHover = normalizedHoverSrc !== '' && normalizedHoverSrc !== normalizedPrimarySrc;

            imageLink.classList.toggle('has-hover-image', hasValidHover);

            if (!hasValidHover) {
                hoverImage?.classList.add('d-none');
                return;
            }

            if (!hoverImage) {
                hoverImage = document.createElement('img');
                hoverImage.className = 'card-img-top product-listing-image-hover position-absolute top-0 start-0 w-100 h-100';
                hoverImage.loading = 'lazy';
                hoverImage.alt = image?.alt || '';
                hoverImage.setAttribute('data-product-card-hover-image', '');
                imageLink.appendChild(hoverImage);
            }

            hoverImage.src = normalizedHoverSrc;
            hoverImage.removeAttribute('srcset');
            hoverImage.classList.remove('d-none');
        }

        qtyInput?.addEventListener('change', () => normalizeQty(qtyInput));
        qtyInput?.addEventListener('blur', () => normalizeQty(qtyInput));
        qtyMinus?.addEventListener('click', () => changeQty(-1));
        qtyPlus?.addEventListener('click', () => changeQty(1));

        wishlistButton?.addEventListener('click', async function () {
            const url = wishlistButton.dataset.wishlistUrl || '';
            const sku = skuInput?.value || wishlistButton.dataset.wishlistSku || card.dataset.productSku || '';

            if (!url || !sku) return;

            setButtonLoading(wishlistButton, true);

            try {
                const formData = new FormData();
                formData.append('sku', sku);

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: formData,
                    credentials: 'same-origin'
                });

                const payload = await parseResponse(response);

                if (!response.ok) {
                    throw new Error(payload.message || 'Errore preferiti');
                }

                setButtonLoading(wishlistButton, false);
                setWishlistButtonState(wishlistButton, Boolean(payload.added));

                showFeedback(card, payload.message || 'Preferiti aggiornati.');

                document.dispatchEvent(new CustomEvent('wishlist:updated', {
                    detail: payload
                }));
            } catch (error) {
                console.error(error);
                setButtonLoading(wishlistButton, false);
                showFeedback(card, error.message || 'Errore aggiornamento preferiti', 'error');
            }
        });

        variants.forEach(function (button) {
            button.addEventListener('click', function () {
                const variantSku = button.dataset.variantSku;

                if (!variantSku) return;

                const variantType = button.dataset.variantType || '';
                const variantUrl = button.dataset.variantUrl || '';
                const variantImage = button.datasetVariantImage || button.dataset.variantImage || '';
                const variantHoverImage = button.dataset.variantHoverImage || '';
                const variantPrice = button.dataset.variantPrice || '';
                const variantBarcode = button.dataset.variantBarcode || '';

                variants.forEach(function (item) {
                    if ((item.dataset.variantType || '') !== variantType) return;

                    item.classList.remove('is-active', 'is-selected');
                    item.setAttribute('aria-pressed', 'false');

                    item.querySelector('.product-listing-option-swatch')?.classList.remove('is-active', 'is-selected');
                    item.querySelector('.product-listing-option-pill')?.classList.remove('is-active', 'is-selected');
                });

                button.classList.add('is-active', 'is-selected');
                button.setAttribute('aria-pressed', 'true');

                button.querySelector('.product-listing-option-swatch')?.classList.add('is-active', 'is-selected');
                button.querySelector('.product-listing-option-pill')?.classList.add('is-active', 'is-selected');

                if (variantUrl) {
                    links.forEach((link) => {
                        link.href = variantUrl;
                    });
                }

                if (skuInput) {
                    skuInput.value = variantSku;
                }

                if (skuLabel) {
                    skuLabel.textContent = variantSku;
                }

                if (barcodeLabel) {
                    barcodeLabel.textContent = variantBarcode !== '' ? variantBarcode : '—';
                }

                if (barcodeRow) {
                    barcodeRow.classList.toggle('d-none', variantBarcode === '');
                }

                if (wishlistButton) {
                    wishlistButton.dataset.wishlistSku = variantSku;
                    setWishlistButtonState(wishlistButton, false);
                }

                card.dataset.productSku = variantSku;

                if (image && variantImage) {
                    image.src = variantImage;
                    image.removeAttribute('srcset');
                }

                setHoverImage(variantHoverImage, variantImage);

                if (priceNode) {
                    priceNode.textContent = variantPrice !== '' ? formatPrice(variantPrice) : '—';
                }

                setQtyRules(
                    button.dataset.variantQtyMin,
                    button.dataset.variantQtyStep,
                    button.dataset.variantPackMultiple
                );
            });
        });

        if (addToCartForm) {
            addToCartForm.addEventListener('submit', async function (event) {
                event.preventDefault();

                normalizeQty(qtyInput);

                const submitButton = addToCartForm.querySelector('button[type="submit"]');

                setButtonLoading(submitButton, true);

                try {
                    const response = await fetch(addToCartForm.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: new FormData(addToCartForm),
                        credentials: 'same-origin'
                    });

                    const payload = await parseResponse(response);

                    if (!response.ok) {
                        throw new Error(payload.message || 'Errore carrello');
                    }

                    showFeedback(card, payload.message || 'Prodotto aggiunto.');

                    await refreshMinicart(!isB2B);

                    document.dispatchEvent(new CustomEvent('cart:updated', {
                        detail: payload
                    }));
                } catch (error) {
                    console.error(error);
                    showFeedback(card, error.message || 'Errore aggiunta carrello', 'error');
                } finally {
                    setButtonLoading(submitButton, false);
                }
            });
        }

        normalizeQty(qtyInput);
    });

    refreshMinicart(false);
});

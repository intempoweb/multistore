/*
|--------------------------------------------------------------------------
| Storefront UI
|--------------------------------------------------------------------------
| Gestione frontend storefront:
| - Mini cart header via AJAX
| - Aggiornamento/rimozione righe minicart
| - Product page: prezzo dinamico per quantità solo per display
| - Product page: add to cart AJAX
| - Cart page: normalizzazione quantità
| - Reload automatico su pagine carrello/checkout dopo modifiche minicart
|
| Nota importante:
| Il prezzo reale del carrello NON viene mai calcolato qui.
| Add to cart invia solo SKU + quantità/FormData: il prezzo lo risolve il backend.
 */
document.addEventListener('DOMContentLoaded', function () {
    const storefrontRoot = document.body;
    const minicartContainer = document.querySelector('[data-minicart-container]') || document.getElementById('minicart-container');
    const minicartOffcanvas = document.getElementById('storefrontMinicart');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let miniCartLoaded = false;
    let miniCartLoadingPromise = null;

    /*
     |--------------------------------------------------------------------------
     | Shared helpers
     |--------------------------------------------------------------------------
     */
    const toInt = (value, fallback = 1) => {
        const parsed = parseInt(value, 10);

        return Number.isNaN(parsed) ? fallback : parsed;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const normalizeQty = (input) => {
        if (!input) {
            return;
        }

        const min = Math.max(1, toInt(input.dataset.qtyMin || input.min || '1', 1));
        const step = Math.max(1, toInt(input.dataset.qtyStep || input.step || '1', 1));
        const max = input.max !== undefined && input.max !== null && input.max !== ''
            ? toInt(input.max, null)
            : null;

        let value = toInt(input.value || min, min);

        if (value <= 0) {
            value = min;
        }

        value = Math.max(value, min);

        if (step > 1) {
            const offset = value - min;

            if (offset > 0) {
                value = min + (Math.ceil(offset / step) * step);
            }
        }

        if (max !== null && !Number.isNaN(max) && value > max) {
            value = max;

            if (step > 1 && value > min) {
                const offset = value - min;
                value = min + (Math.floor(offset / step) * step);
            }

            if (value < min) {
                value = min;
            }
        }

        input.value = String(value);
    };

    const formatPrice = (value) => {
        const number = Number(value);

        if (value === null || value === undefined || value === '' || Number.isNaN(number)) {
            return '—';
        }

        return '€ ' + number.toLocaleString('it-IT', {
            minimumFractionDigits: 3,
            maximumFractionDigits: 3,
        });
    };


    const parseResponse = async (response) => {
        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            return response.json();
        }

        return {
            message: await response.text(),
        };
    };

    const isCartPage = () => window.location.pathname.includes('/cart');
    const isCheckoutPage = () => window.location.pathname.includes('/checkout');
    const isCartOrCheckoutPage = () => isCartPage() || isCheckoutPage();

    const updateMiniCartCount = (countValue) => {
        const parsed = parseFloat(countValue || 0);
        const count = Math.max(0, Math.round(Number.isNaN(parsed) ? 0 : parsed));

        document.querySelectorAll('[data-cart-count-badge], [data-minicart-count-badge]').forEach((el) => {
            el.textContent = count.toLocaleString('it-IT');
            el.classList.toggle('d-none', count <= 0);
            el.style.display = '';
        });
    };



    const refreshAfterCartChange = async (payload = null) => {
        if (payload && Object.prototype.hasOwnProperty.call(payload, 'cart_count')) {
            updateMiniCartCount(payload.cart_count);
        }

        if (isCartOrCheckoutPage()) {
            window.location.reload();
            return;
        }

        const shouldRefreshMiniCart = minicartOffcanvas?.classList.contains('show') || miniCartLoaded;

        if (shouldRefreshMiniCart && typeof window.loadMiniCart === 'function') {
            await window.loadMiniCart({ force: true, showSpinner: false });
        }
    };

    /*
     |--------------------------------------------------------------------------
     | Mini cart header
     |--------------------------------------------------------------------------
     */
    const minicartUrl = storefrontRoot?.dataset.minicartUrl || '';

    const renderMiniCartError = (message) => {
        if (!minicartContainer) {
            return;
        }

        minicartContainer.innerHTML = `
            <div class="rounded border bg-light-subtle p-3 text-center">
                <div class="text-muted small">${escapeHtml(message)}</div>
            </div>
        `;
    };

    const loadMiniCart = async (options = {}) => {
        const force = Boolean(options.force);
        const showSpinner = options.showSpinner !== false;

        if (!minicartContainer) {
            return;
        }

        if (!minicartUrl) {
            renderMiniCartError('URL minicart non configurato.');
            return;
        }

        if (miniCartLoaded && !force) {
            return;
        }

        if (miniCartLoadingPromise && !force) {
            return miniCartLoadingPromise;
        }

        if (showSpinner && !miniCartLoaded) {
            minicartContainer.innerHTML = `
                <div class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Caricamento carrello...
                </div>
            `;
        }

        miniCartLoadingPromise = (async () => {
            try {
                const response = await fetch(minicartUrl, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(`Errore caricamento minicart (${response.status})`);
                }

                const html = await response.text();

                if (!html || html.trim() === '') {
                    throw new Error('Minicart vuoto');
                }

                minicartContainer.innerHTML = html;
                miniCartLoaded = true;

                const wrapper = minicartContainer.querySelector('[data-minicart]');
                updateMiniCartCount(wrapper ? (wrapper.dataset.cartCount || 0) : 0);

                if (!wrapper) {
                    console.warn('Markup minicart caricato ma contenitore [data-minicart] non trovato.');
                }
            } catch (error) {
                renderMiniCartError('Impossibile caricare il carrello.');
                updateMiniCartCount(0);
                console.error(error);
            } finally {
                miniCartLoadingPromise = null;
            }
        })();

        return miniCartLoadingPromise;
    };

    window.loadMiniCart = loadMiniCart;


    /*
     |--------------------------------------------------------------------------
     | Header category menu
     |--------------------------------------------------------------------------
     */
    const initHeaderCategoryMenu = () => {
        const desktopMediaQuery = window.matchMedia('(min-width: 1200px)');
        const dropdowns = document.querySelectorAll('.storefront-main-nav .dropdown');

        const closeDropdown = (dropdown) => {
            const toggle = dropdown.querySelector('.storefront-nav-link');
            const menu = dropdown.querySelector('.storefront-category-menu');

            dropdown.classList.remove('show');
            toggle?.classList.remove('show');
            toggle?.setAttribute('aria-expanded', 'false');
            menu?.classList.remove('show');
        };

        const closeOtherDropdowns = (currentDropdown) => {
            dropdowns.forEach((dropdown) => {
                if (dropdown !== currentDropdown) {
                    closeDropdown(dropdown);
                }
            });
        };

        dropdowns.forEach((dropdown) => {
            const toggle = dropdown.querySelector('.storefront-nav-link');
            const menu = dropdown.querySelector('.storefront-category-menu');
            let closeTimer = null;

            if (!toggle || !menu) {
                return;
            }

            const openMenu = () => {
                if (!desktopMediaQuery.matches) {
                    return;
                }

                if (closeTimer) {
                    window.clearTimeout(closeTimer);
                    closeTimer = null;
                }

                closeOtherDropdowns(dropdown);

                dropdown.classList.add('show');
                toggle.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
                menu.classList.add('show');
            };

            const closeMenu = () => {
                if (!desktopMediaQuery.matches) {
                    return;
                }

                closeTimer = window.setTimeout(() => {
                    closeDropdown(dropdown);
                }, 140);
            };

            dropdown.addEventListener('mouseenter', openMenu);
            dropdown.addEventListener('mouseleave', closeMenu);
            dropdown.addEventListener('focusin', openMenu);
            dropdown.addEventListener('focusout', closeMenu);

            toggle.addEventListener('click', function (event) {
                if (desktopMediaQuery.matches) {
                    window.location.href = toggle.href;
                    return;
                }

                event.preventDefault();

                const isOpen = dropdown.classList.contains('show');
                closeOtherDropdowns(dropdown);

                dropdown.classList.toggle('show', !isOpen);
                toggle.classList.toggle('show', !isOpen);
                toggle.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
                menu.classList.toggle('show', !isOpen);
            });
        });

        const resetDropdowns = () => {
            dropdowns.forEach((dropdown) => closeDropdown(dropdown));
        };

        if (typeof desktopMediaQuery.addEventListener === 'function') {
            desktopMediaQuery.addEventListener('change', resetDropdowns);
        } else if (typeof desktopMediaQuery.addListener === 'function') {
            desktopMediaQuery.addListener(resetDropdowns);
        }

        document.addEventListener('click', function (event) {
            const clickedInsideMenu = event.target.closest('.storefront-main-nav .dropdown');

            if (!clickedInsideMenu && !desktopMediaQuery.matches) {
                resetDropdowns();
            }
        });
    };

    initHeaderCategoryMenu();

    /*
     |--------------------------------------------------------------------------
     | Cart page
     |--------------------------------------------------------------------------
     */
    document.querySelectorAll('.cart-qty-input').forEach((input) => {
        input.addEventListener('change', function () {
            normalizeQty(input);
        });

        input.addEventListener('blur', function () {
            normalizeQty(input);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                setTimeout(() => normalizeQty(input), 0);
            }
        });
    });

    document.querySelectorAll('.cart-update-form').forEach((form) => {
        form.addEventListener('submit', function () {
            const input = form.querySelector('.cart-qty-input');

            if (input) {
                normalizeQty(input);
            }
        });
    });

    /*
     |--------------------------------------------------------------------------
     | Product page
     |--------------------------------------------------------------------------
     */
    const productPage = document.querySelector('[data-product-page]');
    const productQtyInput = document.getElementById('product-quantity-input');
    const productPriceDisplay = document.getElementById('product-price-display');
    const productPriceNote = document.getElementById('product-price-note');
    const addToCartForm = document.getElementById('product-add-to-cart-form');
    const addToCartButton = document.getElementById('product-add-to-cart-button');
    const addToCartFeedback = document.getElementById('product-add-to-cart-feedback');

    if (productPage && productQtyInput && productPriceDisplay) {
        const galleryThumbs = Array.from(document.querySelectorAll('[data-product-gallery-thumb]'));
        const mainImage = document.querySelector('[data-product-main-image]') || document.getElementById('product-main-image');
        const imageStage = document.querySelector('[data-product-image-stage]') || mainImage?.closest('.product-main-image-clean');
        const imageLens = document.querySelector('[data-product-image-lens]');
        let activeGalleryIndex = 0;
        let galleryAutoplayTimer = null;

        const setActiveGalleryThumb = (activeButton) => {
            galleryThumbs.forEach((thumb) => {
                const isActive = thumb === activeButton;

                thumb.classList.toggle('active', isActive);
                thumb.classList.toggle('border-dark', isActive);
                thumb.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        };

        const resetProductLens = () => {
            if (!imageStage) {
                return;
            }

            imageStage.classList.remove('is-lens-active');
            imageStage.style.removeProperty('--lens-image');
            imageStage.style.removeProperty('--lens-x');
            imageStage.style.removeProperty('--lens-y');
            imageStage.style.removeProperty('--lens-bg-x');
            imageStage.style.removeProperty('--lens-bg-y');
            imageStage.style.removeProperty('--lens-bg-width');
            imageStage.style.removeProperty('--lens-bg-height');

            if (imageLens) {
                imageLens.style.removeProperty('background-image');
            }
        };

        const setMainProductImage = (index, scrollThumb = true) => {
            if (!mainImage || galleryThumbs.length === 0) {
                return;
            }

            const normalizedIndex = ((index % galleryThumbs.length) + galleryThumbs.length) % galleryThumbs.length;
            const button = galleryThumbs[normalizedIndex];
            const imageUrl = button?.dataset.imageUrl;

            if (!button || !imageUrl) {
                return;
            }

            activeGalleryIndex = normalizedIndex;
            setActiveGalleryThumb(button);

            if (scrollThumb) {
                button.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'nearest',
                });
            }

            const currentImagePath = new URL(mainImage.currentSrc || mainImage.src, window.location.href).pathname;
            const nextImagePath = new URL(imageUrl, window.location.href).pathname;

            if (currentImagePath === nextImagePath) {
                return;
            }

            mainImage.classList.add('is-changing');
            resetProductLens();

            window.setTimeout(() => {
                mainImage.src = imageUrl;
                imageStage?.setAttribute('data-zoom-image', imageUrl);
                mainImage.classList.remove('is-changing');
            }, 120);
        };

        const stopGalleryAutoplay = () => {
            if (galleryAutoplayTimer) {
                window.clearInterval(galleryAutoplayTimer);
                galleryAutoplayTimer = null;
            }
        };

        const startGalleryAutoplay = () => {
            stopGalleryAutoplay();

            if (galleryThumbs.length <= 1) {
                return;
            }

            galleryAutoplayTimer = window.setInterval(() => {
                setMainProductImage(activeGalleryIndex + 1, false);
            }, 4200);
        };

        galleryThumbs.forEach((button, index) => {
            button.setAttribute('aria-pressed', index === 0 ? 'true' : 'false');

            if (index === 0) {
                button.classList.add('active', 'border-dark');
            }

            button.addEventListener('click', function () {
                stopGalleryAutoplay();
                setMainProductImage(index);
            });
        });

        if (imageStage && mainImage && imageLens) {
            imageStage.addEventListener('pointermove', function (event) {
                if (window.matchMedia('(max-width: 767.98px)').matches) {
                    resetProductLens();
                    return;
                }

                const rect = imageStage.getBoundingClientRect();

                if (rect.width <= 0 || rect.height <= 0) {
                    return;
                }

                const pointerX = Math.min(Math.max(event.clientX - rect.left, 0), rect.width);
                const pointerY = Math.min(Math.max(event.clientY - rect.top, 0), rect.height);
                const percentX = (pointerX / rect.width) * 100;
                const percentY = (pointerY / rect.height) * 100;
                const zoomImage = imageStage.dataset.zoomImage || mainImage.currentSrc || mainImage.src;
                const zoomFactor = 2.2;
                const bgWidth = rect.width * zoomFactor;
                const bgHeight = rect.height * zoomFactor;
                const lensSize = imageLens.offsetWidth || 210;
                const bgX = -(((percentX / 100) * bgWidth) - (lensSize / 2));
                const bgY = -(((percentY / 100) * bgHeight) - (lensSize / 2));

                imageStage.style.setProperty('--lens-image', `url("${zoomImage}")`);
                imageStage.style.setProperty('--lens-x', `${percentX}%`);
                imageStage.style.setProperty('--lens-y', `${percentY}%`);
                imageStage.style.setProperty('--lens-bg-width', `${bgWidth}px`);
                imageStage.style.setProperty('--lens-bg-height', `${bgHeight}px`);
                imageStage.style.setProperty('--lens-bg-x', `${bgX}px`);
                imageStage.style.setProperty('--lens-bg-y', `${bgY}px`);
                imageStage.classList.add('is-lens-active');
            });

            imageStage.addEventListener('pointerenter', stopGalleryAutoplay);

            imageStage.addEventListener('pointerleave', function () {
                resetProductLens();
                startGalleryAutoplay();
            });
        }

        startGalleryAutoplay();

        const basePrice = parseFloat(productPriceDisplay.dataset.basePrice || '');
        let priceBreaks = [];

        try {
            priceBreaks = JSON.parse(productQtyInput.dataset.priceBreaks || '[]');
        } catch (error) {
            priceBreaks = [];
        }

        const showFeedback = (message, type = 'success') => {
            if (!addToCartFeedback) {
                return;
            }

            addToCartFeedback.classList.remove('d-none', 'text-success', 'text-danger', 'text-muted');
            addToCartFeedback.classList.add(type === 'error' ? 'text-danger' : 'text-success');
            addToCartFeedback.textContent = message;
        };

        const resolveTierPrice = (qty) => {
            if (!Array.isArray(priceBreaks) || priceBreaks.length === 0) {
                return Number.isNaN(basePrice) ? null : basePrice;
            }

            const normalizedQty = Number(qty);
            if (Number.isNaN(normalizedQty) || normalizedQty <= 0) {
                return Number.isNaN(basePrice) ? null : basePrice;
            }

            let matched = null;

            for (const tier of priceBreaks) {
                const qtyFrom = Number(tier.qty_from ?? 0);
                const qtyTo = tier.qty_to === null || tier.qty_to === undefined || tier.qty_to === ''
                    ? null
                    : Number(tier.qty_to);
                const price = Number(tier.price ?? tier.price_net ?? NaN);

                if (Number.isNaN(price)) {
                    continue;
                }

                const meetsFrom = normalizedQty >= qtyFrom;
                const meetsTo = qtyTo === null || Number.isNaN(qtyTo) || normalizedQty <= qtyTo;

                if (meetsFrom && meetsTo) {
                    matched = price;
                }
            }

            if (matched !== null) {
                return matched;
            }

            return Number.isNaN(basePrice) ? null : basePrice;
        };

        const updateDisplayedPrice = () => {
            normalizeQty(productQtyInput);

            const qty = Number(productQtyInput.value || productQtyInput.min || 1);
            const resolvedPrice = resolveTierPrice(qty);
            productPriceDisplay.textContent = formatPrice(resolvedPrice);

            if (productPriceNote && Array.isArray(priceBreaks) && priceBreaks.length > 0) {
                productPriceNote.textContent = 'Prezzo calcolato per quantità: ' + qty.toLocaleString('it-IT');
            }
        };

        productQtyInput.addEventListener('input', updateDisplayedPrice);
        productQtyInput.addEventListener('change', updateDisplayedPrice);
        productQtyInput.addEventListener('blur', updateDisplayedPrice);
        updateDisplayedPrice();

        if (addToCartForm && addToCartButton) {
            addToCartForm.addEventListener('submit', async function (event) {
                event.preventDefault();

                normalizeQty(productQtyInput);

                addToCartButton.disabled = true;
                const originalHtml = addToCartButton.innerHTML;
                addToCartButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Aggiunta in corso...';

                try {
                    const response = await fetch(addToCartForm.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: new FormData(addToCartForm),
                        credentials: 'same-origin',
                    });

                    const payload = await parseResponse(response);

                    if (!response.ok) {
                        throw new Error(payload.message || 'Errore durante aggiunta al carrello');
                    }

                    showFeedback(payload.message || 'Prodotto aggiunto al carrello.');
                    document.dispatchEvent(new CustomEvent('cart:updated', { detail: payload }));
                    await refreshAfterCartChange(payload);
                } catch (error) {
                    showFeedback(error.message || 'Impossibile aggiungere il prodotto al carrello.', 'error');
                    console.error(error);
                } finally {
                    addToCartButton.disabled = false;
                    addToCartButton.innerHTML = originalHtml;
                }
            });
        }
    }

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('[data-minicart-update-form]');
        if (!form) {
            return;
        }

        event.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');
        const minicartQtyInput = form.querySelector('input[name="qty"]');

        if (submitButton) {
            submitButton.disabled = true;
        }

        if (minicartQtyInput) {
            minicartQtyInput.readOnly = true;
            normalizeQty(minicartQtyInput);
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: new FormData(form),
                credentials: 'same-origin',
            });

            const payload = await parseResponse(response);

            if (!response.ok) {
                throw new Error(payload.message || `Errore aggiornamento riga minicart (${response.status})`);
            }

            await refreshAfterCartChange(payload);
        } catch (error) {
            console.error(error);
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }

            if (minicartQtyInput) {
                minicartQtyInput.readOnly = false;
            }
        }
    });

    document.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-cart-remove]');
        if (!button) {
            return;
        }

        event.preventDefault();

        try {
            const response = await fetch(button.dataset.removeUrl, {
                method: button.dataset.method || 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            const payload = await parseResponse(response);

            if (!response.ok) {
                throw new Error(payload.message || `Errore rimozione riga (${response.status})`);
            }

            await refreshAfterCartChange(payload);
        } catch (error) {
            console.error(error);
        }
    });

    if (minicartOffcanvas) {
        minicartOffcanvas.addEventListener('show.bs.offcanvas', function () {
            loadMiniCart({ force: true });
        });
    }
});
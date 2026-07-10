(function () {
    'use strict';

    const onReady = function (callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        callback();
    };

    const initHero = function () {
        document.querySelectorAll('[data-ciak-hero]').forEach(function (hero) {
            const slides = Array.from(hero.querySelectorAll('[data-ciak-hero-slide]'));
            const previousButton = hero.querySelector('[data-ciak-hero-prev]');
            const nextButton = hero.querySelector('[data-ciak-hero-next]');
            const currentLabel = hero.querySelector('[data-ciak-hero-current]');
            const autoplayDelay = Math.max(2500, Number(hero.dataset.ciakHeroInterval || 6000));

            if (!slides.length) return;

            let current = Math.max(0, slides.findIndex(function (slide) {
                return slide.classList.contains('is-active');
            }));
            let autoplayTimer = null;

            const stopAutoplay = function () {
                if (autoplayTimer !== null) {
                    window.clearInterval(autoplayTimer);
                    autoplayTimer = null;
                }
            };

            const playActiveVideo = function (slide) {
                const video = slide.querySelector('video');

                if (!video) return;

                video.loop = true;
                video.muted = true;
                video.playsInline = true;
                video.currentTime = 0;
                video.play().catch(function () {});
            };

            const pauseInactiveVideo = function (slide) {
                const video = slide.querySelector('video');

                if (!video) return;

                video.pause();
                video.currentTime = 0;
            };

            const show = function (next) {
                current = (next + slides.length) % slides.length;

                slides.forEach(function (slide, index) {
                    const active = index === current;

                    slide.classList.toggle('is-active', active);
                    slide.setAttribute('aria-hidden', active ? 'false' : 'true');

                    if (active) {
                        playActiveVideo(slide);
                    } else {
                        pauseInactiveVideo(slide);
                    }
                });

                if (currentLabel) {
                    currentLabel.textContent = String(current + 1);
                }
            };

            const startAutoplay = function () {
                stopAutoplay();

                if (slides.length < 2 || document.hidden) return;

                autoplayTimer = window.setInterval(function () {
                    show(current + 1);
                }, autoplayDelay);
            };

            const restartAutoplay = function () {
                show(current);
                startAutoplay();
            };

            previousButton?.addEventListener('click', function () {
                current = (current - 1 + slides.length) % slides.length;
                restartAutoplay();
            });

            nextButton?.addEventListener('click', function () {
                current = (current + 1) % slides.length;
                restartAutoplay();
            });

            hero.addEventListener('mouseenter', stopAutoplay);
            hero.addEventListener('mouseleave', startAutoplay);
            hero.addEventListener('focusin', stopAutoplay);
            hero.addEventListener('focusout', function (event) {
                if (!hero.contains(event.relatedTarget)) {
                    startAutoplay();
                }
            });

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    stopAutoplay();
                    slides.forEach(pauseInactiveVideo);
                    return;
                }

                show(current);
                startAutoplay();
            });

            show(current);
            startAutoplay();
        });
    };

    const initStickyHeader = function () {
        const header = document.querySelector('.ciak-header');

        if (!header) return;

        const update = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 8);
        };

        update();
        window.addEventListener('scroll', update, { passive: true });
    };

    const initBrandHomeScroll = function () {
        const brandLinks = Array.from(document.querySelectorAll('.ciak-header .ciak-brand[href]'));

        if (!brandLinks.length) return;

        brandLinks.forEach(function (link) {
            link.addEventListener('click', function (event) {
                // Keep expected browser behavior for new-tab/new-window actions.
                if (
                    event.defaultPrevented ||
                    event.button !== 0 ||
                    event.metaKey ||
                    event.ctrlKey ||
                    event.shiftKey ||
                    event.altKey
                ) {
                    return;
                }

                const targetUrl = new URL(link.href, window.location.origin);
                const currentUrl = new URL(window.location.href);

                const isSamePage =
                    targetUrl.origin === currentUrl.origin &&
                    targetUrl.pathname === currentUrl.pathname &&
                    targetUrl.search === currentUrl.search;

                if (!isSamePage) return;

                event.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    };

    const initFormats = function () {
        document.querySelectorAll('[data-ciak-formats]').forEach(function (section) {
            const tabs = Array.from(section.querySelectorAll('[data-ciak-format-tab]'));
            const panels = Array.from(section.querySelectorAll('[data-ciak-format-panel]'));
            let revealTimer = null;
            const outlineHoldDelay = 900;

            if (!tabs.length || !panels.length) return;

            const resetPanel = function (panel) {
                panel.classList.remove('is-previewing', 'is-detail-ready');
            };

            const showOutlineThenColor = function (panel) {
                window.clearTimeout(revealTimer);

                resetPanel(panel);

                panel.offsetHeight;

                window.requestAnimationFrame(function () {
                    panel.classList.add('is-previewing');

                    revealTimer = window.setTimeout(function () {
                        panel.classList.remove('is-previewing');
                        panel.classList.add('is-detail-ready');
                    }, outlineHoldDelay);
                });
            };

            const activate = function (index, shouldScrollTab) {
                tabs.forEach(function (tab) {
                    const active = Number(tab.dataset.ciakFormatIndex || 0) === index;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                panels.forEach(function (panel) {
                    const active = Number(panel.dataset.ciakFormatIndex || 0) === index;

                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;

                    if (!active) {
                        resetPanel(panel);
                    }
                });

                const activePanel = panels.find(function (panel) {
                    return Number(panel.dataset.ciakFormatIndex || 0) === index;
                });

                if (activePanel) {
                    showOutlineThenColor(activePanel);
                }

                // Scroll active tab into view smoothly
                const activeTab = tabs.find(function (tab) {
                    return Number(tab.dataset.ciakFormatIndex || 0) === index;
                });
                
                if (activeTab && shouldScrollTab) {
                    window.requestAnimationFrame(function () {
                        activeTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    });
                }
            };

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activate(Number(tab.dataset.ciakFormatIndex || 0), true);
                });
            });

            const initial = tabs.find(function (tab) {
                return tab.classList.contains('is-active');
            });

            // On first render keep the page at its current vertical position.
            activate(Number(initial?.dataset.ciakFormatIndex || 0), false);
        });
    };

    const initFormatStickyNavigation = function () {
        const wrapper = document.querySelector('[data-ciak-format-stories-wrapper]');
        if (!wrapper) return;

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                wrapper.classList.toggle('is-scrolled', !entry.isIntersecting);
            });
        }, {
            threshold: [0, 1]
        });

        observer.observe(wrapper);
    };

    const initAboutVision = function () {
        document.querySelectorAll('[data-ciak-about-vision]').forEach(function (section) {
            const tabs = Array.from(section.querySelectorAll('[data-ciak-about-vision-tab]'));
            const panels = Array.from(section.querySelectorAll('[data-ciak-about-vision-panel]'));

            if (!tabs.length || !panels.length) return;

            let activeTarget = '';

            const activate = function (target, options) {
                const settings = Object.assign({
                    focusTab: false,
                    scrollTab: false,
                }, options || {});

                const nextTab = tabs.find(function (tab) {
                    return tab.dataset.ciakAboutVisionTarget === target;
                });

                const nextPanel = panels.find(function (panel) {
                    return panel.dataset.ciakAboutVisionPanelKey === target;
                });

                if (!nextTab || !nextPanel) return;

                activeTarget = target;

                tabs.forEach(function (tab) {
                    const active = tab === nextTab;

                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.setAttribute('tabindex', active ? '0' : '-1');
                });

                panels.forEach(function (panel) {
                    const active = panel === nextPanel;

                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;
                    panel.setAttribute('aria-hidden', active ? 'false' : 'true');
                });

                section.dataset.ciakAboutVisionActive = target;

                if (settings.focusTab) {
                    nextTab.focus({ preventScroll: true });
                }

                if (settings.scrollTab) {
                    window.requestAnimationFrame(function () {
                        nextTab.scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest',
                            inline: 'center',
                        });
                    });
                }
            };

            tabs.forEach(function (tab, index) {
                tab.addEventListener('click', function () {
                    activate(tab.dataset.ciakAboutVisionTarget || '', {
                        scrollTab: true,
                    });
                });

                tab.addEventListener('keydown', function (event) {
                    let nextIndex = null;

                    if (event.key === 'ArrowRight') {
                        nextIndex = (index + 1) % tabs.length;
                    } else if (event.key === 'ArrowLeft') {
                        nextIndex = (index - 1 + tabs.length) % tabs.length;
                    } else if (event.key === 'Home') {
                        nextIndex = 0;
                    } else if (event.key === 'End') {
                        nextIndex = tabs.length - 1;
                    }

                    if (nextIndex === null) return;

                    event.preventDefault();

                    activate(tabs[nextIndex].dataset.ciakAboutVisionTarget || '', {
                        focusTab: true,
                        scrollTab: true,
                    });
                });
            });

            const initialTab = tabs.find(function (tab) {
                return tab.classList.contains('is-active') || tab.getAttribute('aria-selected') === 'true';
            }) || tabs[0];

            if (initialTab) {
                activate(initialTab.dataset.ciakAboutVisionTarget || '', {
                    focusTab: false,
                    scrollTab: false,
                });
            }

            const activateFromHash = function () {
                const hashTarget = window.location.hash.replace('#', '');

                if (!hashTarget || !tabs.some(function (tab) {
                    return tab.dataset.ciakAboutVisionTarget === hashTarget;
                })) {
                    return;
                }

                activate(hashTarget, {
                    focusTab: false,
                    scrollTab: true,
                });
            };

            activateFromHash();
            window.addEventListener('hashchange', activateFromHash);

            section.addEventListener('ciak:about-vision:activate', function (event) {
                const target = event.detail && event.detail.target;

                if (!target || target === activeTarget) return;

                activate(target, {
                    focusTab: false,
                    scrollTab: true,
                });
            });
        });
    };

    const initInstagram = function () {
        document.querySelectorAll('[data-ciak-instagram]').forEach(function (section) {
            const grid = section.querySelector('[data-ciak-instagram-grid]');
            const button = section.querySelector('[data-ciak-instagram-more]');
            const endpoint = section.dataset.instagramUrl || '';

            if (!grid || !button || !endpoint) return;

            const formatNumber = function (value) {
                const number = Number(value || 0);
                return new Intl.NumberFormat('it-IT').format(number);
            };

            const escapeHtml = function (value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            const cardHtml = function (item) {
                const isVideo = item.type === 'video';
                const permalink = item.permalink || '';
                const media = isVideo
                    ? '<video autoplay muted loop playsinline preload="metadata" poster="' + escapeHtml(item.poster || '') + '"><source src="' + escapeHtml(item.desktop || '') + '"></video>'
                    : '<picture>' + (item.mobile ? '<source media="(max-width:767px)" srcset="' + escapeHtml(item.mobile) + '">' : '') + '<img src="' + escapeHtml(item.desktop || '') + '" alt="' + escapeHtml(item.alt || 'Instagram') + '" loading="lazy" decoding="async"></picture>';
                const hasLikes = item.likes !== null && item.likes !== undefined;
                const hasComments = item.comments !== null && item.comments !== undefined;
                const metrics = (hasLikes || hasComments)
                    ? '<span class="ciak-instagram-metrics">' +
                        (hasLikes ? '<span><i data-lucide="heart"></i>' + formatNumber(item.likes) + '</span>' : '') +
                        (hasComments ? '<span><i data-lucide="message-circle"></i>' + formatNumber(item.comments) + '</span>' : '') +
                      '</span>'
                    : '';
                const inner = media +
                    '<figcaption>' +
                        '<span class="ciak-instagram-badge"><i data-lucide="instagram"></i> Instagram</span>' +
                        metrics +
                        '<span class="ciak-instagram-caption">' + escapeHtml(item.alt || '') + '</span>' +
                    '</figcaption>';

                return '<figure class="ciak-instagram-card ' + (isVideo ? 'is-video' : '') + '" data-ciak-instagram-card>' +
                    (permalink ? '<a href="' + escapeHtml(permalink) + '" target="_blank" rel="noopener" aria-label="Apri il post Instagram">' + inner + '</a>' : inner) +
                    '</figure>';
            };

            button.addEventListener('click', async function () {
                const offset = Number(button.dataset.offset || 12);
                button.disabled = true;
                button.classList.add('is-loading');

                try {
                    const url = new URL(endpoint, window.location.origin);
                    url.searchParams.set('offset', String(offset));
                    url.searchParams.set('limit', '12');

                    const response = await fetch(url.toString(), {
                        headers: { Accept: 'application/json' },
                    });

                    if (!response.ok) throw new Error('Instagram gallery request failed');

                    const payload = await response.json();
                    const items = Array.isArray(payload.items) ? payload.items : [];

                    if (items.length) {
                        grid.insertAdjacentHTML('beforeend', items.map(cardHtml).join(''));
                        button.dataset.offset = String(payload.next_offset || (offset + items.length));

                        if (window.lucide) {
                            window.lucide.createIcons();
                        }
                    }

                    if (!payload.has_more || !items.length) {
                        button.closest('.ciak-instagram-more')?.remove();
                    }
                } catch (error) {
                    console.warn(error);
                    button.disabled = false;
                } finally {
                    button.classList.remove('is-loading');

                    if (document.body.contains(button)) {
                        button.disabled = false;
                    }
                }
            });
        });
    };

    const initQtyStepper = function () {
        document.querySelectorAll('[data-ciak-qty-stepper]').forEach(function (stepper) {
            var input = stepper.querySelector('.ciak-qty-input');
            var decBtn = stepper.querySelector('[data-ciak-qty-dec]');
            var incBtn = stepper.querySelector('[data-ciak-qty-inc]');

            if (!input || !decBtn || !incBtn) return;

            function getMin() { return Math.max(1, parseInt(input.min, 10) || 1); }
            function getStep() { return Math.max(1, parseInt(input.step, 10) || 1); }
            function getMax() {
                if (!input.max || input.max === '') return null;
                var v = parseInt(input.max, 10);
                return isNaN(v) ? null : v;
            }
            function getVal() { return parseInt(input.value, 10) || getMin(); }

            function syncBtns() {
                var v = getVal(), mx = getMax();
                decBtn.disabled = input.disabled || v <= getMin();
                incBtn.disabled = input.disabled || (mx !== null && v >= mx);
            }

            function applyVal(next) {
                input.value = String(next);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                syncBtns();
            }

            decBtn.addEventListener('click', function () {
                applyVal(Math.max(getMin(), getVal() - getStep()));
            });

            incBtn.addEventListener('click', function () {
                var mx = getMax();
                var next = getVal() + getStep();
                applyVal(mx !== null ? Math.min(mx, next) : next);
            });

            input.addEventListener('change', syncBtns);
            syncBtns();
        });
    };

    const initMinicartOnCartAdd = function () {
        document.addEventListener('cart:updated', function () {
            var offcanvasEl = document.getElementById('storefrontMinicart');
            if (!offcanvasEl) return;

            var openIt = function () {
                if (window.bootstrap && window.bootstrap.Offcanvas) {
                    window.bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).show();
                }
            };

            if (typeof window.loadMiniCart === 'function') {
                window.loadMiniCart({ force: true, showSpinner: true }).then(openIt)['catch'](openIt);
            } else {
                openIt();
            }
        });
    };

    onReady(function () {
        initStickyHeader();
        initBrandHomeScroll();
        initHero();
        initAboutVision();
        initFormats();
        initFormatStickyNavigation();
        initInstagram();
        initQtyStepper();
        initMinicartOnCartAdd();
    });
}());

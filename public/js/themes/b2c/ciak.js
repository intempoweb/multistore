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
        const hero = document.querySelector('[data-ciak-hero]');
        const slides = hero ? Array.from(hero.querySelectorAll('[data-ciak-hero-slide]')) : [];

        if (!hero || !slides.length) return;

        let current = 0;

        const show = function (next) {
            current = (next + slides.length) % slides.length;

            slides.forEach(function (slide, index) {
                const active = index === current;
                const video = slide.querySelector('video');

                slide.classList.toggle('is-active', active);

                if (video) {
                    if (active) {
                        video.play().catch(function () {});
                    } else {
                        video.pause();
                    }
                }
            });

            const label = hero.querySelector('[data-ciak-hero-current]');
            if (label) label.textContent = current + 1;
        };

        hero.querySelector('[data-ciak-hero-prev]')?.addEventListener('click', function () {
            show(current - 1);
        });

        hero.querySelector('[data-ciak-hero-next]')?.addEventListener('click', function () {
            show(current + 1);
        });

        show(0);
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

            const activate = function (index) {
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
            };

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activate(Number(tab.dataset.ciakFormatIndex || 0));
                });
            });

            const initial = tabs.find(function (tab) {
                return tab.classList.contains('is-active');
            });

            activate(Number(initial?.dataset.ciakFormatIndex || 0));
        });
    };

    const initAboutVision = function () {
        document.querySelectorAll('[data-ciak-about-vision]').forEach(function (section) {
            const tabs = Array.from(section.querySelectorAll('[data-ciak-about-vision-tab]'));
            const panels = Array.from(section.querySelectorAll('[data-ciak-about-vision-panel]'));

            if (!tabs.length || !panels.length) return;

            const activate = function (target, focusTab) {
                tabs.forEach(function (tab) {
                    const active = tab.dataset.ciakAboutVisionTarget === target;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.setAttribute('tabindex', active ? '0' : '-1');

                    if (active && focusTab) {
                        tab.focus({ preventScroll: true });
                    }
                });

                panels.forEach(function (panel) {
                    const active = panel.dataset.ciakAboutVisionPanelKey === target;
                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;
                });
            };

            tabs.forEach(function (tab, index) {
                tab.addEventListener('click', function () {
                    activate(tab.dataset.ciakAboutVisionTarget || '', false);
                });

                tab.addEventListener('keydown', function (event) {
                    if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;

                    event.preventDefault();

                    const direction = event.key === 'ArrowRight' ? 1 : -1;
                    const next = (index + direction + tabs.length) % tabs.length;

                    activate(tabs[next].dataset.ciakAboutVisionTarget || '', true);
                });
            });

            const initial = tabs.find(function (tab) {
                return tab.classList.contains('is-active');
            }) || tabs[0];

            if (initial) {
                activate(initial.dataset.ciakAboutVisionTarget || '', false);
            }
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
            const input = stepper.querySelector('.ciak-qty-input');
            const decBtn = stepper.querySelector('[data-ciak-qty-dec]');
            const incBtn = stepper.querySelector('[data-ciak-qty-inc]');

            if (!input || !decBtn || !incBtn) return;

            var getConstraints = function () {
                var min = Math.max(1, parseInt(input.min || '1', 10) || 1);
                var step = Math.max(1, parseInt(input.step || '1', 10) || 1);
                var max = input.max !== '' && input.max !== undefined && input.max !== null
                    ? (parseInt(input.max, 10) || null)
                    : null;
                return { min: min, step: step, max: max };
            };

            var updateButtons = function () {
                var c = getConstraints();
                var val = parseInt(input.value, 10) || c.min;
                decBtn.disabled = input.disabled || val <= c.min;
                incBtn.disabled = input.disabled || (c.max !== null && val >= c.max);
            };

            decBtn.addEventListener('click', function () {
                var c = getConstraints();
                var current = parseInt(input.value, 10) || c.min;
                var next = Math.max(c.min, current - c.step);
                input.value = String(next);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                updateButtons();
            });

            incBtn.addEventListener('click', function () {
                var c = getConstraints();
                var current = parseInt(input.value, 10) || c.min;
                var next = c.max !== null ? Math.min(c.max, current + c.step) : current + c.step;
                input.value = String(next);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                updateButtons();
            });

            input.addEventListener('input', updateButtons);
            input.addEventListener('change', updateButtons);
            updateButtons();
        });
    };

    onReady(function () {
        initStickyHeader();
        initHero();
        initAboutVision();
        initFormats();
        initInstagram();
        initQtyStepper();
    });
}());

(function () {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const outlineHoldDelay = 2200;

    const loadOutline = function (panel) {
        const outlineLayer = panel.querySelector('[data-teknikoshop-outline-src]');

        if (!outlineLayer || outlineLayer.dataset.teknikoshopOutlineLoaded === '1') {
            return Promise.resolve();
        }

        const outlineUrl = outlineLayer.dataset.teknikoshopOutlineSrc;
        if (!outlineUrl) return Promise.resolve();

        return fetch(outlineUrl, { credentials: 'omit' })
            .then(function (response) {
                if (!response.ok) throw new Error('Tekniko outline not available');
                return response.text();
            })
            .then(function (svg) {
                if (!svg || !svg.includes('<svg')) return;

                outlineLayer.innerHTML = svg;
                outlineLayer.dataset.teknikoshopOutlineLoaded = '1';
                outlineLayer.classList.add('is-loaded');
            })
            .catch(function () {
                outlineLayer.dataset.teknikoshopOutlineLoaded = '1';
            });
    };

    const resetPanel = function (panel) {
        panel.classList.remove('is-outline-running', 'is-detail-ready');
    };

    const runPanel = function (panel, timers) {
        window.clearTimeout(timers.reveal);
        resetPanel(panel);

        if (reduceMotion) {
            panel.classList.add('is-detail-ready');
            return;
        }

        loadOutline(panel).finally(function () {
            panel.offsetHeight;
            window.requestAnimationFrame(function () {
                panel.classList.add('is-outline-running');
                timers.reveal = window.setTimeout(function () {
                    panel.classList.add('is-detail-ready');
                }, outlineHoldDelay);
            });
        });
    };

    const bootFormats = function () {
        document.querySelectorAll('[data-teknikoshop-formats]').forEach(function (section) {
            const tabs = Array.from(section.querySelectorAll('[data-teknikoshop-format-tab]'));
            const panels = Array.from(section.querySelectorAll('[data-teknikoshop-format-panel]'));
            const timers = { reveal: null };

            if (!tabs.length || !panels.length) return;

            const activate = function (index, shouldScrollTab) {
                tabs.forEach(function (tab) {
                    const active = Number(tab.dataset.teknikoshopFormatIndex || 0) === index;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                panels.forEach(function (panel) {
                    const active = Number(panel.dataset.teknikoshopFormatIndex || 0) === index;
                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;

                    if (!active) resetPanel(panel);
                });

                const activePanel = panels.find(function (panel) {
                    return Number(panel.dataset.teknikoshopFormatIndex || 0) === index;
                });

                if (activePanel) runPanel(activePanel, timers);

                if (shouldScrollTab) {
                    const activeTab = tabs.find(function (tab) {
                        return Number(tab.dataset.teknikoshopFormatIndex || 0) === index;
                    });
                    activeTab?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                }
            };

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activate(Number(tab.dataset.teknikoshopFormatIndex || 0), true);
                });

                tab.addEventListener('keydown', function (event) {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                    event.preventDefault();

                    const current = Number(tab.dataset.teknikoshopFormatIndex || 0);
                    const next = event.key === 'Home'
                        ? 0
                        : event.key === 'End'
                            ? tabs.length - 1
                            : event.key === 'ArrowRight'
                                ? (current + 1) % tabs.length
                                : (current - 1 + tabs.length) % tabs.length;

                    tabs[next]?.focus();
                    activate(next, true);
                });
            });

            activate(0, false);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootFormats, { once: true });
    } else {
        bootFormats();
    }
})();

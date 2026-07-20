(function () {
    const visualSelector = '[data-teknikoshop-collection-visual]';
    const loaded = new WeakSet();
    const timers = new WeakMap();

    const loadOutline = function (visual) {
        const outlineLayer = visual.querySelector('[data-teknikoshop-outline-src]');

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
                outlineLayer.innerHTML = svg;
                outlineLayer.dataset.teknikoshopOutlineLoaded = '1';
                outlineLayer.classList.add('is-loaded');
            })
            .catch(function () {
                outlineLayer.dataset.teknikoshopOutlineLoaded = '1';
            });
    };

    const clearVisualTimer = function (visual) {
        const timer = timers.get(visual);
        if (timer) window.clearTimeout(timer);
    };

    const runAnimation = function (visual) {
        clearVisualTimer(visual);
        visual.classList.remove('is-outline-running', 'is-detail-ready');

        window.requestAnimationFrame(function () {
            visual.classList.add('is-outline-running');

            timers.set(visual, window.setTimeout(function () {
                visual.classList.add('is-detail-ready');
            }, 2450));
        });
    };

    const prepareVisual = function (visual) {
        if (loaded.has(visual)) {
            runAnimation(visual);
            return;
        }

        loaded.add(visual);
        loadOutline(visual).finally(function () {
            runAnimation(visual);
        });
    };

    const boot = function () {
        const visuals = Array.from(document.querySelectorAll(visualSelector));

        if (!visuals.length) return;

        if (!('IntersectionObserver' in window)) {
            visuals.forEach(prepareVisual);
            return;
        }

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    prepareVisual(entry.target);
                }
            });
        }, { threshold: 0.25 });

        visuals.forEach(function (visual) {
            observer.observe(visual);
            visual.addEventListener('mouseenter', function () {
                prepareVisual(visual);
            });
            visual.addEventListener('focusin', function () {
                prepareVisual(visual);
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();

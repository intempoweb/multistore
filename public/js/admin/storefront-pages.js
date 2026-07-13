(function () {
    'use strict';

    const initMediaRepeater = function () {
        const template = document.getElementById('hero-media-row-template');

        if (!template) {
            return;
        }

        document.querySelectorAll('[data-add-hero-media]').forEach(function (button) {
            if (button.dataset.mediaRepeaterReady === '1') {
                return;
            }

            button.dataset.mediaRepeaterReady = '1';

            button.addEventListener('click', function () {
                const blockIndex = button.dataset.blockIndex;
                const list = document.querySelector(`[data-hero-media-list="${blockIndex}"]`);

                if (!list) {
                    return;
                }

                const mediaIndex = Number.parseInt(list.dataset.nextIndex || '0', 10);
                const safeMediaIndex = Number.isNaN(mediaIndex) ? 0 : mediaIndex;
                const html = template.innerHTML
                    .replaceAll('__BLOCK__', String(blockIndex))
                    .replaceAll('__MEDIA__', String(safeMediaIndex));

                list.insertAdjacentHTML('beforeend', html);
                list.dataset.nextIndex = String(safeMediaIndex + 1);

                const newRow = list.lastElementChild;
                const firstInput = newRow?.querySelector('select, input, textarea');

                if (firstInput instanceof HTMLElement) {
                    firstInput.focus({ preventScroll: true });
                }

                newRow?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });
    };

    const initDirtyGuard = function () {
        let pageDirty = false;

        document.querySelectorAll('form input, form textarea, form select').forEach((field) => {
            field.addEventListener('change', () => {
                pageDirty = true;
            });
            field.addEventListener('input', () => {
                pageDirty = true;
            });
        });

        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!form.action.includes('/admin/locale/')) {
                    pageDirty = false;
                    return;
                }

                if (pageDirty && !window.confirm('Ci sono modifiche non salvate. Vuoi cambiare lingua senza salvarle?')) {
                    event.preventDefault();
                }
            });
        });
    };

    const initCharacterCounters = function () {
        document.querySelectorAll('[data-character-counter]').forEach((field) => {
            const targetId = field.dataset.characterCounterTarget;
            const target = targetId ? document.getElementById(targetId) : null;

            if (!target) {
                return;
            }

            const render = function () {
                target.textContent = `${field.value.length} caratteri`;
            };

            field.addEventListener('input', render);
            render();
        });
    };

    const initSeoPreview = function () {
        const titleField = document.getElementById('meta_title');
        const pageTitleField = document.getElementById('title');
        const descriptionField = document.getElementById('meta_description');
        const slugField = document.getElementById('slug');
        const previewTitle = document.querySelector('[data-seo-preview-title]');
        const previewDescription = document.querySelector('[data-seo-preview-description]');
        const previewUrl = document.querySelector('[data-seo-preview-url]');
        const warning = document.querySelector('[data-seo-preview-warning]');

        if (!previewTitle || !previewDescription) {
            return;
        }

        const buildPreviewUrl = function () {
            if (!previewUrl) {
                return;
            }

            const baseUrl = (previewUrl.dataset.storefrontBaseUrl || '').replace(/\/+$/, '');
            const locale = (previewUrl.dataset.contentLocale || '').replace(/^\/+|\/+$/g, '');
            const rawSlug = (slugField?.value || '').trim().replace(/^\/+|\/+$/g, '');
            const slug = rawSlug === 'home' ? '' : rawSlug;
            const pathParts = [locale, slug].filter(Boolean);
            const path = pathParts.length ? `/${pathParts.join('/')}` : '/';

            previewUrl.textContent = `${baseUrl}${path}`;
        };

        const render = function () {
            const title = (titleField?.value || pageTitleField?.value || '').trim();
            const description = (descriptionField?.value || '').trim();
            const notes = [];

            previewTitle.textContent = title || 'Titolo pagina';
            previewDescription.textContent = description || 'Aggiungi una meta description chiara e utile per descrivere questa pagina.';
            buildPreviewUrl();

            if (!title) {
                notes.push('Meta title mancante.');
            } else if (title.length < 35 || title.length > 65) {
                notes.push('Meta title consigliato tra 50 e 60 caratteri.');
            }

            if (!description) {
                notes.push('Meta description mancante.');
            } else if (description.length < 120 || description.length > 170) {
                notes.push('Meta description consigliata tra 140 e 160 caratteri.');
            }

            if (warning) {
                warning.className = notes.length ? 'small mt-2 text-warning' : 'small mt-2 text-success';
                warning.textContent = notes.length ? notes.join(' ') : 'SEO di base compilata.';
            }
        };

        [titleField, pageTitleField, descriptionField, slugField].forEach((field) => {
            field?.addEventListener('input', render);
        });

        render();
    };

    const init = function () {
        initMediaRepeater();
        initDirtyGuard();
        initCharacterCounters();
        initSeoPreview();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());

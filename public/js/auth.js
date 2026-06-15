document.addEventListener('DOMContentLoaded', function () {
    const isAuthPage = document.body?.classList.contains('storefront-auth-page');

    if (isAuthPage) {
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    }

    const initAuthModeTabs = function () {
        const tabs = Array.from(document.querySelectorAll('[data-auth-mode-tab]'));
        const loginModeInput = document.querySelector('[data-auth-mode-input]');
        const magicModeInput = document.querySelector('[data-magic-auth-mode-input]');
        const loginLabel = document.querySelector('[data-login-label]');
        const loginInput = document.querySelector('[data-login-input]');
        const loginHelp = document.querySelector('[data-login-help]');
        const magicLabel = document.querySelector('[data-magic-email-label]');
        const magicInput = document.querySelector('[data-magic-email-input]');
        const magicHelp = document.querySelector('[data-magic-email-help]');

        if (!tabs.length || !loginModeInput || !loginLabel || !loginInput || !loginHelp) {
            return;
        }

        const expireMinutes = magicHelp?.dataset.expireMinutes || '30';

        const content = {
            customer: {
                loginLabel: 'Codice cliente o email cliente',
                loginPlaceholder: 'Codice cliente o email cliente',
                loginHelp: 'Usa le credenziali del tuo account cliente.',
                magicLabel: 'Accesso rapido via email cliente',
                magicPlaceholder: 'email cliente',
                magicHelp: `Il link cliente scade dopo ${expireMinutes} minuti.`,
            },
            agent: {
                loginLabel: 'Email agente',
                loginPlaceholder: 'email agente',
                loginHelp: 'Usa la tua email agente. Dopo il login entrerai nell’elenco clienti assegnati.',
                magicLabel: 'Accesso rapido via email agente',
                magicPlaceholder: 'email agente',
                magicHelp: `Il link agente scade dopo ${expireMinutes} minuti.`,
            },
        };

        const setMode = function (mode) {
            const selected = content[mode] ? mode : 'customer';
            const data = content[selected];

            loginModeInput.value = selected;
            if (magicModeInput) magicModeInput.value = selected;

            loginLabel.textContent = data.loginLabel;
            loginInput.placeholder = data.loginPlaceholder;
            loginHelp.textContent = data.loginHelp;

            if (magicLabel) magicLabel.textContent = data.magicLabel;
            if (magicInput) magicInput.placeholder = data.magicPlaceholder;
            if (magicHelp) magicHelp.textContent = data.magicHelp;

            tabs.forEach(function (tab) {
                const isActive = tab.dataset.authMode === selected;
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        };

        tabs.forEach(function (tab) {
            if (tab.dataset.authBound === '1') return;
            tab.dataset.authBound = '1';

            tab.addEventListener('click', function () {
                setMode(tab.dataset.authMode);
            });
        });

        setMode(loginModeInput.value || 'customer');
    };

    initAuthModeTabs();

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        if (button.dataset.authBound === '1') return;

        button.dataset.authBound = '1';

        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.passwordTarget);
            const icon = button.querySelector('[data-password-toggle-icon]');

            if (!input) return;

            const show = input.type === 'password';

            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Nascondi password' : 'Mostra password');

            if (icon) {
                icon.classList.toggle('fa-eye', !show);
                icon.classList.toggle('fa-eye-slash', show);
            }
        });
    });
});
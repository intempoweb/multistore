document.addEventListener('DOMContentLoaded', function () {
    const isAuthPage = document.body?.classList.contains('storefront-auth-page');

    if (isAuthPage) {
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    }

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
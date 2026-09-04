@php
    /** @var \App\Services\Security\RecaptchaVerifier $recaptcha */
    $recaptcha = app(\App\Services\Security\RecaptchaVerifier::class);
    $recaptchaAction = $action ?? 'submit';
    $recaptchaSiteKey = $recaptcha->siteKey();
@endphp

@if($recaptcha->enabled() && $recaptchaSiteKey)
    <input
        type="hidden"
        name="g-recaptcha-response"
        value=""
        data-recaptcha-token
        data-recaptcha-action="{{ $recaptchaAction }}"
        data-recaptcha-site-key="{{ $recaptchaSiteKey }}"
    >

    @once
        @push('scripts')
            <script>
                (() => {
                    let recaptchaLoadingPromise = null;

                    const loadRecaptcha = (siteKey) => {
                        if (window.grecaptcha) {
                            return Promise.resolve();
                        }

                        if (recaptchaLoadingPromise) {
                            return recaptchaLoadingPromise;
                        }

                        recaptchaLoadingPromise = new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            script.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(siteKey);
                            script.async = true;
                            script.defer = true;
                            script.onload = () => resolve();
                            script.onerror = () => reject(new Error('reCAPTCHA non disponibile.'));
                            document.head.appendChild(script);
                        });

                        return recaptchaLoadingPromise;
                    };

                    document.querySelectorAll('[data-recaptcha-token]').forEach((input) => {
                        const siteKey = input.dataset.recaptchaSiteKey;

                        if (siteKey) {
                            loadRecaptcha(siteKey).catch(() => {});
                        }
                    });

                    const executeRecaptcha = (input, form) => {
                        const siteKey = input.dataset.recaptchaSiteKey;
                        const action = input.dataset.recaptchaAction || 'submit';

                        if (!siteKey) {
                            HTMLFormElement.prototype.submit.call(form);
                            return;
                        }

                        loadRecaptcha(siteKey)
                            .then(() => {
                                window.grecaptcha.ready(() => {
                                    window.grecaptcha.execute(siteKey, { action }).then((token) => {
                                        input.value = token;
                                        HTMLFormElement.prototype.submit.call(form);
                                    });
                                });
                            })
                            .catch(() => {
                                HTMLFormElement.prototype.submit.call(form);
                            });
                    };

                    document.addEventListener('submit', (event) => {
                        const form = event.target;

                        if (!(form instanceof HTMLFormElement) || form.dataset.recaptchaSubmitting === '1') {
                            return;
                        }

                        const input = form.querySelector('[data-recaptcha-token]');

                        if (!input) {
                            return;
                        }

                        event.preventDefault();
                        form.dataset.recaptchaSubmitting = '1';
                        executeRecaptcha(input, form);
                    }, true);
                })();
            </script>
        @endpush
    @endonce
@endif

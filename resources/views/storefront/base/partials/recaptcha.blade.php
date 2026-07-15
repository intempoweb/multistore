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
        @push('head-scripts')
            <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode($recaptchaSiteKey) }}" async defer></script>
        @endpush

        @push('scripts')
            <script>
                (() => {
                    const executeRecaptcha = (input, form) => {
                        const siteKey = input.dataset.recaptchaSiteKey;
                        const action = input.dataset.recaptchaAction || 'submit';

                        if (!window.grecaptcha || !siteKey) {
                            HTMLFormElement.prototype.submit.call(form);
                            return;
                        }

                        window.grecaptcha.ready(() => {
                            window.grecaptcha.execute(siteKey, { action }).then((token) => {
                                input.value = token;
                                HTMLFormElement.prototype.submit.call(form);
                            });
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

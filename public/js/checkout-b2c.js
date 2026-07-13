document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('.checkout-page');
    const form = document.getElementById('checkout-place-form');

    if (!root || !form || root.dataset.checkoutMode !== 'b2c') {
        return;
    }

    const checkoutUrl = root.dataset.checkoutUrl || window.location.href;
    const paymentPreviewUrl = root.dataset.paymentPreviewUrl || '';
    const stripeReturnUrl = root.dataset.stripeReturnUrl || window.location.href;
    const freeLabel = root.dataset.labelFree || 'Free';
    const unavailableLabel = root.dataset.labelUnavailable || 'Unavailable';
    const shippingCostMessage = root.dataset.shippingCostMessage || '';

    const csrfToken = form.querySelector('input[name="_token"]')?.value
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || '';

    const accountSection = document.querySelector('[data-checkout-account]');
    const accountEmailInput = document.querySelector('[data-checkout-account-email]');
    const loginEmailInput = document.querySelector('[data-checkout-login-email]');
    const passwordWrapper = document.querySelector('[data-checkout-password-wrapper]');
    const passwordInput = document.querySelector('[data-checkout-password]');
    const accountMessage = document.querySelector('[data-checkout-account-message]');
    const guestAccountMessage = document.querySelector('[data-checkout-account-guest-message]');
    const loginSubmit = document.querySelector('[data-checkout-login-submit]');
    let accountLookupTimer = null;
    let accountLookupController = null;
    let lastCheckedEmail = '';

    const shippingCountryInput = document.getElementById('shipping_country');
    const billingCountryInput = document.getElementById('billing_country');

    const billingSameToggle = document.querySelector('[data-billing-same-as-shipping]');
    const billingWrapper = document.querySelector('[data-billing-wrapper]');
    const billingInputs = Array.from(document.querySelectorAll('[data-billing-input]'));

    const invoiceToggle = document.querySelector('[data-invoice-toggle]');
    const invoiceFields = document.querySelector('[data-invoice-fields]');
    const invoiceInputs = Array.from(document.querySelectorAll('[data-invoice-input]'));

    const paymentCards = Array.from(document.querySelectorAll('[data-payment-card]'));
    const paymentRadios = Array.from(document.querySelectorAll('[data-payment-radio]'));
    const paymentPanels = Array.from(document.querySelectorAll('[data-payment-panel]'));

    const spinner = document.getElementById('checkout-summary-spinner');
    const warningBox = document.getElementById('checkout-shipping-warning');
    const warningMessage = document.getElementById('checkout-shipping-warning-message');

    const shippingPrice = document.getElementById('checkout-shipping-price');
    const shippingMessage = document.getElementById('checkout-shipping-message');
    const summaryShipping = document.getElementById('checkout-summary-shipping');
    const summaryMessage = document.getElementById('checkout-summary-message');

    const subtotalNode = document.getElementById('checkout-subtotal');
    const discountNode = document.getElementById('checkout-discount');
    const totalNode = document.getElementById('checkout-grand-total');
    const submitButton = document.getElementById('checkout-submit-button');

    const stripeContainer = document.getElementById('stripe-payment-element');
    const stripeError = document.getElementById('stripe-payment-error');

    const paypalContainer = document.getElementById('paypal-buttons');
    const paypalError = document.getElementById('paypal-payment-error');

    let refreshTimer = null;
    let paymentPreviewTimer = null;
    let activeSummaryController = null;
    let activePaymentController = null;
    let isSubmitting = false;
    let checkoutSubmitAttempted = false;
    let latestShippingAvailable = false;
    let activeGateway = null;
    let paymentPreviewGateway = null;
    let latestPaymentAmountKey = null;
    let stripeAmountKey = null;
    let paypalAmountKey = null;

    let stripe = null;
    let stripeElements = null;
    let stripeClientSecret = null;
    let stripePaymentElement = null;

    let paypalOrderId = null;
    let paypalRenderedOrderId = null;

    function formatEuro(value) {
        return '€ ' + Number(value || 0).toLocaleString('it-IT', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function amountKey(value) {
        return Number(value || 0).toFixed(3);
    }

    function normalizedEmail(value) {
        return String(value || '').trim().toLowerCase();
    }

    function emailLooksValid(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function syncCheckoutLoginEmail() {
        if (loginEmailInput && accountEmailInput) {
            loginEmailInput.value = normalizedEmail(accountEmailInput.value);
        }
    }

    function renderAccountStatus(payload) {
        const registered = Boolean(payload && payload.registered);
        const passwordAvailable = Boolean(payload && payload.password_available);

        if (passwordWrapper) {
            passwordWrapper.classList.toggle('d-none', !registered);
        }

        if (guestAccountMessage) {
            guestAccountMessage.classList.toggle('d-none', registered);
        }

        if (passwordInput) {
            passwordInput.disabled = !registered || !passwordAvailable;
            passwordInput.required = registered && passwordAvailable;
        }

        if (loginSubmit) {
            loginSubmit.disabled = !registered || !passwordAvailable;
        }

        if (accountMessage && registered) {
            accountMessage.textContent = passwordAvailable
                ? (accountSection?.dataset.accountExistsMessage || accountMessage.textContent || '')
                : (accountSection?.dataset.accountWithoutPasswordMessage || '');
        }
    }

    async function checkCheckoutAccount() {
        if (!accountSection || !accountEmailInput || !loginEmailInput || !passwordWrapper) {
            return;
        }

        const email = normalizedEmail(accountEmailInput.value);
        syncCheckoutLoginEmail();

        if (!emailLooksValid(email)) {
            lastCheckedEmail = '';
            renderAccountStatus({ registered: false });
            return;
        }

        if (email === lastCheckedEmail) {
            return;
        }

        if (accountLookupController) {
            accountLookupController.abort();
        }

        accountLookupController = new AbortController();

        try {
            const response = await fetch(accountSection.dataset.accountStatusUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                },
                credentials: 'same-origin',
                signal: accountLookupController.signal,
                body: JSON.stringify({ email: email })
            });

            if (!response.ok) {
                return;
            }

            lastCheckedEmail = email;
            renderAccountStatus(await response.json());
        } catch (error) {
            if (error.name !== 'AbortError') {
                lastCheckedEmail = '';
            }
        }
    }

    function scheduleCheckoutAccountCheck() {
        syncCheckoutLoginEmail();

        if (accountLookupTimer) {
            window.clearTimeout(accountLookupTimer);
        }

        accountLookupTimer = window.setTimeout(checkCheckoutAccount, 450);
    }

    if (accountEmailInput && loginEmailInput) {
        accountEmailInput.addEventListener('input', scheduleCheckoutAccountCheck);
        accountEmailInput.addEventListener('blur', checkCheckoutAccount);
        document.getElementById('checkout-account-login-form')?.addEventListener('submit', syncCheckoutLoginEmail);

        if (emailLooksValid(normalizedEmail(accountEmailInput.value)) && passwordWrapper?.classList.contains('d-none')) {
            scheduleCheckoutAccountCheck();
        }
    }

    function isStripeAuthorizedStatus(status) {
        return ['requires_capture', 'succeeded'].includes(String(status || '').toLowerCase());
    }

    function setLoading(enabled) {
        if (spinner) {
            spinner.classList.toggle('d-none', !enabled);
        }
    }

    function selectedPaymentGateway() {
        const checked = paymentRadios.find(function (radio) {
            return radio.checked;
        });

        return checked ? checked.value : 'stripe';
    }

    function clearPaymentErrors() {
        [stripeError, paypalError].forEach(function (node) {
            if (!node) return;

            node.textContent = '';
            node.classList.add('d-none');
        });
    }

    function showPaymentError(node, message) {
        if (!node) {
            showFormError(message);
            return;
        }

        node.textContent = message || 'Pagamento non completato.';
        node.classList.remove('d-none');
    }

    function showFormError(message) {
        let alert = document.getElementById('checkout-submit-error');

        if (!alert) {
            alert = document.createElement('div');
            alert.id = 'checkout-submit-error';
            alert.className = 'alert alert-danger';
            root.insertBefore(alert, root.firstChild.nextSibling);
        }

        alert.textContent = message || 'Impossibile completare il checkout.';
        alert.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function clearValidationErrors() {
        document.querySelectorAll('[data-checkout-field-error]').forEach(function (node) {
            node.remove();
        });

        document.querySelectorAll('.is-invalid[data-checkout-invalid]').forEach(function (field) {
            field.classList.remove('is-invalid');
            delete field.dataset.checkoutInvalid;
        });
    }

    function fieldForValidationKey(key) {
        return document.querySelector(`[name="${CSS.escape(key)}"][form="checkout-place-form"]`)
            || form.querySelector(`[name="${CSS.escape(key)}"]`);
    }

    function showValidationErrors(errors, scrollToFirst = true) {
        clearValidationErrors();

        const entries = Object.entries(errors || {});
        let firstField = null;

        entries.forEach(function ([key, messages]) {
            const field = fieldForValidationKey(key);
            const message = Array.isArray(messages) ? messages[0] : String(messages || '');

            if (!field || !message) {
                return;
            }

            field.classList.add('is-invalid');
            field.dataset.checkoutInvalid = '1';

            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block';
            feedback.dataset.checkoutFieldError = key;
            feedback.textContent = message;

            const wrapper = field.closest('.input-group') || field;
            wrapper.insertAdjacentElement('afterend', feedback);
            firstField ||= field;
        });

        if (firstField && scrollToFirst) {
            firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstField.focus({ preventScroll: true });
        }

        return entries.length > 0;
    }

    function validationErrorFromPayload(payload, fallback) {
        const error = new Error(errorMessageFromPayload(payload, fallback));
        error.validationErrors = payload.errors || {};

        return error;
    }

    function errorMessageFromPayload(payload, fallback) {
        const validationMessage = Object.values(payload.errors || {}).flat().join(' ');

        return validationMessage
            || payload.message
            || fallback;
    }

    function setSubmitState(enabled) {
        isSubmitting = enabled;

        if (!submitButton) return;

        submitButton.disabled = enabled || !latestShippingAvailable;

        if (enabled) {
            submitButton.dataset.originalText = submitButton.dataset.originalText || submitButton.innerHTML;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Attendi...';
        } else if (submitButton.dataset.originalText) {
            submitButton.innerHTML = submitButton.dataset.originalText;
        }
    }

    function abortPaymentPreviewRequest() {
        if (activePaymentController) {
            activePaymentController.abort();
            activePaymentController = null;
        }

        if (paymentPreviewTimer) {
            window.clearTimeout(paymentPreviewTimer);
            paymentPreviewTimer = null;
        }
    }

    function resetStripePreview() {
        stripe = null;
        stripeElements = null;
        stripeClientSecret = null;
        stripePaymentElement = null;
        stripeAmountKey = null;

        if (stripeContainer) {
            stripeContainer.innerHTML = '';
        }
    }

    function resetPayPalPreview() {
        paypalOrderId = null;
        paypalRenderedOrderId = null;
        paypalAmountKey = null;

        if (paypalContainer) {
            paypalContainer.innerHTML = '';
            delete paypalContainer.dataset.paypalOrderId;
            delete paypalContainer.dataset.paypalAmountKey;
        }
    }

    function resetPaymentPreview() {
        abortPaymentPreviewRequest();
        resetStripePreview();
        resetPayPalPreview();
        paymentPreviewGateway = null;
        clearPaymentErrors();
    }

    function syncPaymentCards(force = false) {
        const gateway = selectedPaymentGateway();
        const gatewayChanged = activeGateway !== gateway;

        activeGateway = gateway;

        paymentCards.forEach(function (card) {
            const radio = card.querySelector('[data-payment-radio]');
            const selected = Boolean(radio && radio.checked);

            card.classList.toggle('border-primary', selected);
            card.classList.toggle('bg-light-subtle', selected);
        });

        paymentPanels.forEach(function (panel) {
            panel.classList.toggle('d-none', panel.dataset.paymentPanel !== gateway);
        });

        if (force || gatewayChanged) {
            resetPaymentPreview();
            schedulePaymentPreview(true);
        }
    }

    function syncBillingVisibility() {
        const sameAsShipping = Boolean(billingSameToggle && billingSameToggle.checked);

        if (billingWrapper) {
            billingWrapper.classList.toggle('d-none', sameAsShipping);
        }

        billingInputs.forEach(function (input) {
            input.disabled = sameAsShipping;
            input.required = false;
        });

        if (sameAsShipping && shippingCountryInput && billingCountryInput) {
            billingCountryInput.value = shippingCountryInput.value || '';
        }
    }

    function syncInvoiceVisibility() {
        const invoiceRequested = Boolean(invoiceToggle && invoiceToggle.checked);

        if (invoiceFields) {
            invoiceFields.classList.toggle('d-none', !invoiceRequested);
        }

        invoiceInputs.forEach(function (input) {
            input.disabled = !invoiceRequested;
            input.required = false;
        });
    }

    function collectFormData() {
        syncBillingVisibility();
        syncInvoiceVisibility();

        const formData = new FormData(form);

        document.querySelectorAll('[form="checkout-place-form"]').forEach(function (input) {
            if (!input.name || input.disabled) return;

            if ((input.type === 'checkbox' || input.type === 'radio') && !input.checked) {
                return;
            }

            formData.set(input.name, input.value == null ? '' : String(input.value));
        });

        formData.set('billing_same_as_shipping', billingSameToggle && billingSameToggle.checked ? '1' : '0');
        formData.set('billing_request_invoice', invoiceToggle && invoiceToggle.checked ? '1' : '0');
        formData.set('payment_gateway', selectedPaymentGateway());

        if (billingSameToggle && billingSameToggle.checked && shippingCountryInput && billingCountryInput) {
            billingCountryInput.value = shippingCountryInput.value || '';
            formData.set('billing_country', billingCountryInput.value || '');
        }

        return formData;
    }

    function collectPreviewParams() {
        const params = new URLSearchParams();
        const formData = collectFormData();

        for (const [key, value] of formData.entries()) {
            if (key !== '_token') {
                params.set(key, value == null ? '' : String(value));
            }
        }

        return params;
    }

    async function postPlaceOrder(extra = {}) {
        const formData = collectFormData();

        Object.entries(extra).forEach(function ([key, value]) {
            formData.set(key, value == null ? '' : String(value));
        });

        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
            },
            credentials: 'same-origin',
            body: formData
        });

        const payload = await response.json().catch(function () {
            return {};
        });

        if (!response.ok) {
            if (payload.errors) {
                throw validationErrorFromPayload(payload, 'Controlla i dati inseriti.');
            }

            throw new Error(errorMessageFromPayload(payload, 'Impossibile creare ordine.'));
        }

        window.location.href = payload?.data?.redirect_url || '/cart';
    }

    function updateShippingUi(summary) {
        const shippingAvailable = Boolean(summary.shipping_available);
        const shippingIsFree = Boolean(summary.shipping_is_free);
        const shippingTotal = Number(summary.shipping_total || 0);
        const message = String(summary.shipping_message || '');

        latestShippingAvailable = shippingAvailable;

        const shippingHtml = shippingAvailable
            ? (shippingIsFree ? freeLabel : formatEuro(shippingTotal))
            : '<span class="text-danger">' + unavailableLabel + '</span>';

        if (shippingPrice) shippingPrice.innerHTML = shippingHtml;
        if (summaryShipping) summaryShipping.innerHTML = shippingHtml;

        if (shippingMessage) {
            shippingMessage.textContent = message || shippingCostMessage;
            shippingMessage.classList.toggle('text-danger', !shippingAvailable);
            shippingMessage.classList.toggle('text-muted', shippingAvailable);
        }

        if (summaryMessage) {
            summaryMessage.textContent = message || '';
            summaryMessage.classList.toggle('text-danger', !shippingAvailable);
            summaryMessage.classList.toggle('text-muted', shippingAvailable);
            summaryMessage.classList.toggle('mb-3', message !== '');
        }

        if (warningBox && warningMessage) {
            warningBox.classList.toggle('d-none', shippingAvailable || message === '');
            warningMessage.textContent = !shippingAvailable ? message : '';
        }

        if (submitButton) {
            submitButton.disabled = !shippingAvailable || isSubmitting;
        }

        if (!shippingAvailable) {
            resetPaymentPreview();
        }
    }

    function updateTotalsUi(summary) {
        if (subtotalNode) subtotalNode.textContent = formatEuro(summary.subtotal || 0);
        if (discountNode) discountNode.textContent = '- ' + formatEuro(summary.discount_total || 0);
        if (totalNode) totalNode.textContent = formatEuro(summary.grand_total || 0);

        const newAmountKey = amountKey(summary.grand_total || 0);
        const oldAmountKey = latestPaymentAmountKey;
        const amountChanged = oldAmountKey !== null && oldAmountKey !== newAmountKey;

        latestPaymentAmountKey = newAmountKey;

        if (!amountChanged) {
            return;
        }

        if (selectedPaymentGateway() === 'paypal') {
            resetPayPalPreview();
            paymentPreviewGateway = null;
            schedulePaymentPreview(true);
            return;
        }

        stripeAmountKey = null;
    }

    async function refreshCheckoutSummary() {
        if (isSubmitting) return;

        if (activeSummaryController) {
            activeSummaryController.abort();
        }

        activeSummaryController = new AbortController();
        setLoading(true);

        try {
            const params = collectPreviewParams();
            const separator = checkoutUrl.includes('?') ? '&' : '?';

            const response = await fetch(checkoutUrl + separator + params.toString(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                signal: activeSummaryController.signal
            });

            const payload = await response.json().catch(function () {
                return {};
            });

            if (!response.ok) {
                throw new Error(errorMessageFromPayload(payload, 'Preview checkout non disponibile.'));
            }

            const summary = payload?.data?.checkoutSummary
                || payload?.data?.checkout_summary
                || {};

            updateShippingUi(summary);
            updateTotalsUi(summary);
            schedulePaymentPreview(false);
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Errore aggiornamento checkout:', error);
            }
        } finally {
            setLoading(false);
        }
    }

    function scheduleRefresh() {
        if (refreshTimer) {
            window.clearTimeout(refreshTimer);
        }

        refreshTimer = window.setTimeout(refreshCheckoutSummary, 350);
    }

    function schedulePaymentPreview(force = false) {
        if (!paymentPreviewUrl || isSubmitting || !latestShippingAvailable) {
            return;
        }

        const gateway = selectedPaymentGateway();

        if (
            !force
            && gateway === 'stripe'
            && stripeElements
            && stripePaymentElement
            && paymentPreviewGateway === 'stripe'
            && stripeAmountKey === latestPaymentAmountKey
        ) {
            return;
        }

        if (
            !force
            && gateway === 'paypal'
            && paypalRenderedOrderId
            && paymentPreviewGateway === 'paypal'
            && paypalAmountKey === latestPaymentAmountKey
        ) {
            return;
        }

        if (paymentPreviewTimer) {
            window.clearTimeout(paymentPreviewTimer);
        }

        paymentPreviewTimer = window.setTimeout(initPaymentPreview, force ? 0 : 500);
    }

    async function initPaymentPreview() {
        if (!paymentPreviewUrl || isSubmitting || !latestShippingAvailable) {
            return;
        }

        const requestedGateway = selectedPaymentGateway();

        if (
            requestedGateway === 'stripe'
            && stripeElements
            && stripePaymentElement
            && paymentPreviewGateway === 'stripe'
            && stripeAmountKey === latestPaymentAmountKey
        ) {
            return;
        }

        if (
            requestedGateway === 'paypal'
            && paypalRenderedOrderId
            && paymentPreviewGateway === 'paypal'
            && paypalAmountKey === latestPaymentAmountKey
        ) {
            return;
        }

        if (activePaymentController) {
            activePaymentController.abort();
        }

        activePaymentController = new AbortController();
        clearPaymentErrors();

        try {
            const response = await fetch(paymentPreviewUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                },
                body: collectFormData(),
                credentials: 'same-origin',
                signal: activePaymentController.signal
            });

            const payload = await response.json().catch(function () {
                return {};
            });

            if (!response.ok) {
                if (payload.errors) {
                    if (checkoutSubmitAttempted) {
                        showValidationErrors(payload.errors);
                    }

                    resetStripePreview();
                    resetPayPalPreview();
                    paymentPreviewGateway = null;

                    return false;
                }

                throw new Error(errorMessageFromPayload(payload, 'Impossibile inizializzare il pagamento.'));
            }

            const gateway = selectedPaymentGateway();

            if (gateway !== requestedGateway) {
                return;
            }

            paymentPreviewGateway = gateway;

            if (gateway === 'stripe') {
                await mountStripeElements(payload);
            }

            if (gateway === 'paypal') {
                await renderPayPalButtons(payload);
            }

            return true;
        } catch (error) {
            if (error.name === 'AbortError') return;

            console.error('Errore payment preview:', error);

            if (selectedPaymentGateway() === 'stripe') {
                showPaymentError(stripeError, error.message);
            } else {
                showPaymentError(paypalError, error.message);
            }
        }
    }

    function stripeClientSecretFromPayload(payload) {
        const data = payload?.data || {};

        return data.client_secret
            || data.payment_client_secret
            || data.payment?.client_secret
            || data.payment?.clientSecret
            || null;
    }

    function stripePublishableKeyFromPayload(payload) {
        const data = payload?.data || {};

        return data.stripe_key
            || data.payment?.stripe_key
            || root.dataset.stripeKey
            || document.querySelector('meta[name="stripe-key"]')?.getAttribute('content')
            || null;
    }

    async function mountStripeElements(payload) {
        const clientSecret = stripeClientSecretFromPayload(payload);
        const publishableKey = stripePublishableKeyFromPayload(payload);

        if (!clientSecret) {
            throw new Error('Client secret Stripe mancante.');
        }

        if (!window.Stripe || !publishableKey) {
            throw new Error('Stripe.js non caricato o chiave pubblica Stripe mancante.');
        }

        if (stripeClientSecret === clientSecret && stripeElements && stripePaymentElement) {
            stripeAmountKey = latestPaymentAmountKey;
            return;
        }

        stripe = window.Stripe(publishableKey);
        stripeClientSecret = clientSecret;
        stripeAmountKey = latestPaymentAmountKey;

        stripeElements = stripe.elements({
            clientSecret: stripeClientSecret
        });

        if (stripeContainer) {
            stripeContainer.innerHTML = '';
        }

        stripePaymentElement = stripeElements.create('payment');
        stripePaymentElement.mount('#stripe-payment-element');

        if (stripeError) {
            stripeError.classList.add('d-none');
            stripeError.textContent = '';
        }
    }

    async function confirmStripePayment() {
        if (!stripe || !stripeElements || !stripeClientSecret || stripeAmountKey !== latestPaymentAmountKey) {
            const previewReady = await initPaymentPreview();

            if (previewReady === false) {
                return false;
            }
        }

        if (!stripe || !stripeElements || !stripeClientSecret) {
            throw new Error('Pagamento Stripe non inizializzato.');
        }

        const existingPayment = await stripe.retrievePaymentIntent(stripeClientSecret);
        const existingPaymentIntent = existingPayment?.paymentIntent || null;

        if (existingPaymentIntent?.id && isStripeAuthorizedStatus(existingPaymentIntent.status)) {
            await postPlaceOrder({
                payment_gateway: 'stripe',
                payment_intent_id: existingPaymentIntent.id
            });

            return;
        }

        const submitResult = await stripeElements.submit();

        if (submitResult.error) {
            throw new Error(submitResult.error.message || 'Dati carta non validi.');
        }

        const result = await stripe.confirmPayment({
            elements: stripeElements,
            redirect: 'if_required',
            confirmParams: {
                return_url: stripeReturnUrl
            }
        });

        if (result.error) {
            throw new Error(result.error.message || 'Pagamento Stripe non completato.');
        }

        const paymentIntent = result.paymentIntent;
        const paymentIntentId = paymentIntent?.id || '';

        if (!paymentIntentId) {
            throw new Error('PaymentIntent Stripe mancante.');
        }

        if (!isStripeAuthorizedStatus(paymentIntent.status)) {
            throw new Error('Pagamento Stripe non completato. Stato: ' + paymentIntent.status);
        }

        await postPlaceOrder({
            payment_gateway: 'stripe',
            payment_intent_id: paymentIntentId
        });

        return true;
    }

    async function renderPayPalButtons(payload) {
        const data = payload?.data || {};
        const newOrderId = data.paypal_order_id || data.payment?.id || null;

        if (!paypalContainer) {
            return;
        }

        if (!window.paypal) {
            showPaymentError(paypalError, 'SDK PayPal non caricato.');
            return;
        }

        if (!newOrderId) {
            showPaymentError(paypalError, 'ID ordine PayPal mancante.');
            return;
        }

        paypalOrderId = newOrderId;
        paypalAmountKey = latestPaymentAmountKey;

        if (paypalRenderedOrderId === paypalOrderId && paypalContainer.dataset.paypalAmountKey === paypalAmountKey) {
            return;
        }

        paypalRenderedOrderId = paypalOrderId;
        paypalContainer.dataset.paypalOrderId = paypalOrderId;
        paypalContainer.dataset.paypalAmountKey = paypalAmountKey || '';
        paypalContainer.innerHTML = '';

        window.paypal.Buttons({
            createOrder: function () {
                return paypalOrderId;
            },

	           onApprove: async function (data, actions) {
                setSubmitState(true);
                setLoading(true);
                clearPaymentErrors();
                clearValidationErrors();

                try {
                    const orderId = data.orderID || paypalOrderId;

                    if (!orderId) {
                        throw new Error('ID ordine PayPal mancante.');
                    }

                    if (actions?.order?.authorize) {
                        await actions.order.authorize();
                    }

                    await postPlaceOrder({
                        payment_gateway: 'paypal',
                        paypal_order_id: orderId
                    });
                } catch (error) {
                    console.error('Errore PayPal:', error);

                    if (error.validationErrors) {
                        showValidationErrors(error.validationErrors);
                        setSubmitState(false);
                        setLoading(false);
                        return;
                    }

                    showPaymentError(paypalError, error.message || 'Pagamento PayPal non completato.');
                    setSubmitState(false);
                    setLoading(false);
                }
            },

            onCancel: function () {
                showPaymentError(paypalError, 'Pagamento PayPal annullato.');
                setSubmitState(false);
                setLoading(false);
            },

            onError: function (error) {
                console.error('Errore PayPal:', error);
                showPaymentError(paypalError, 'Pagamento PayPal non completato.');
                setSubmitState(false);
                setLoading(false);
            }
        }).render('#paypal-buttons');
    }

    function bindRefreshField(id) {
        const element = document.getElementById(id);

        if (!element) return;

        element.addEventListener('input', scheduleRefresh);
        element.addEventListener('change', scheduleRefresh);
        element.addEventListener('blur', scheduleRefresh);
    }

    async function submitCheckout(event) {
        event.preventDefault();

        if (isSubmitting) return;

        checkoutSubmitAttempted = true;
        setSubmitState(true);
        setLoading(true);
        clearPaymentErrors();
        clearValidationErrors();

        try {
            const gateway = selectedPaymentGateway();

            if (gateway === 'stripe') {
                const completed = await confirmStripePayment();

                if (completed === false) {
                    setSubmitState(false);
                    setLoading(false);
                }

                return;
            }

            if (gateway === 'paypal') {
                if (!paypalRenderedOrderId || paypalAmountKey !== latestPaymentAmountKey) {
                    const previewReady = await initPaymentPreview();

                    if (previewReady === false) {
                        setSubmitState(false);
                        setLoading(false);
                        return;
                    }
                }

                showPaymentError(paypalError, 'Clicca il pulsante PayPal per completare il pagamento.');
                setSubmitState(false);
                setLoading(false);
                return;
            }

            throw new Error('Gateway pagamento non valido.');
        } catch (error) {
            console.error('Errore checkout:', error);

            if (error.validationErrors) {
                showValidationErrors(error.validationErrors);
                setSubmitState(false);
                setLoading(false);
                return;
            }

            if (selectedPaymentGateway() === 'stripe') {
                showPaymentError(stripeError, error.message);
            } else if (selectedPaymentGateway() === 'paypal') {
                showPaymentError(paypalError, error.message);
            } else {
                showFormError(error.message);
            }

            setSubmitState(false);
            setLoading(false);
        }
    }

    [
        'shipping_first_name',
        'shipping_last_name',
        'shipping_email',
        'shipping_phone',
        'shipping_address_line_1',
        'shipping_postcode',
        'shipping_city',
        'shipping_province',
        'shipping_country',
        'billing_first_name',
        'billing_last_name',
        'billing_email',
        'billing_address_line_1',
        'billing_postcode',
        'billing_city',
        'billing_province',
        'billing_country',
        'billing_tax_code',
        'billing_vat_number',
        'billing_sdi',
        'billing_pec',
        'notes'
    ].forEach(bindRefreshField);

    document.querySelectorAll('[form="checkout-place-form"]').forEach(function (field) {
        field.addEventListener('input', function () {
            if (field.dataset.checkoutInvalid === '1') {
                field.classList.remove('is-invalid');
                delete field.dataset.checkoutInvalid;
                document.querySelectorAll(`[data-checkout-field-error="${CSS.escape(field.name)}"]`).forEach(function (node) {
                    node.remove();
                });
            }
        });
    });

    if (billingSameToggle) {
        billingSameToggle.addEventListener('change', function () {
            syncBillingVisibility();
            scheduleRefresh();
        });
    }

    if (invoiceToggle) {
        invoiceToggle.addEventListener('change', function () {
            syncInvoiceVisibility();
            scheduleRefresh();
        });
    }

    paymentCards.forEach(function (card) {
        card.addEventListener('click', function (event) {
            if (
                event.target.closest('[data-payment-panel]')
                || event.target.closest('#stripe-payment-element')
                || event.target.closest('#paypal-buttons')
                || event.target.closest('input, select, textarea, button, iframe')
            ) {
                return;
            }

            const radio = card.querySelector('[data-payment-radio]');

            if (!radio || radio.checked) {
                return;
            }

            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    paymentRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            syncPaymentCards(false);
        });
    });

    form.addEventListener('submit', submitCheckout);

    syncBillingVisibility();
    syncInvoiceVisibility();
    syncPaymentCards(true);
    refreshCheckoutSummary();
});

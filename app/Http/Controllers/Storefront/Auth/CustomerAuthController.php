<?php

namespace App\Http\Controllers\Storefront\Auth;

use App\Http\Controllers\Controller;
use App\Mail\Storefront\Auth\CustomerMagicLoginMail;
use App\Mail\Storefront\Auth\CustomerPasswordResetMail;
use App\Models\AgentAuth;
use App\Models\Customer;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    private const LOGIN_RATE_LIMIT_ATTEMPTS = 5;
    private const LOGIN_RATE_LIMIT_DECAY_SECONDS = 60;

    private const MAGIC_LINK_RATE_LIMIT_ATTEMPTS = 3;
    private const MAGIC_LINK_RATE_LIMIT_DECAY_SECONDS = 300;
    private const MAGIC_LINK_EXPIRE_MINUTES = 30;

    public function __construct(
        private ThemeResolver $themeResolver,
    ) {
    }

    public function showLoginForm(Request $request): RedirectResponse|Response
    {
        $store = $this->currentStore();

        if (Auth::guard('customer')->check()) {
            if ($this->isAgentMode($request)) {
                return redirect()->route('storefront.agent.customers');
            }

            return redirect()->route('storefront.home');
        }

        $loginPage = $this->storefrontPage($store, 'login');
        $login = old('login', old('email', (string) $request->query('login', $request->query('email', ''))));

        return response()
            ->view($this->themeResolver->view('auth.customer-login', $store), [
                'store' => $store,
                'storefrontLayout' => $this->themeResolver->authLayout($store),
                'storefrontPage' => $loginPage,
                'storefrontPageBlocks' => $loginPage?->activeBlocks ?? collect(),
                'login' => $login,
                'email' => $login,
                'magicLinkExpireMinutes' => self::MAGIC_LINK_EXPIRE_MINUTES,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function showRegistrationForm(Request $request): RedirectResponse|Response
    {
        $store = $this->currentStore();
        abort_if($store->is_b2b, 404);

        if (Auth::guard('customer')->check()) {
            return redirect()->route('storefront.home');
        }

        return response()
            ->view($this->themeResolver->view('auth.customer-register', $store), [
                'store' => $store,
                'storefrontLayout' => $this->themeResolver->authLayout($store),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function register(Request $request): RedirectResponse
    {
        $store = $this->currentStore();
        abort_if($store->is_b2b, 404);

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:128'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'privacy' => ['accepted'],
        ]);

        $validator->after(function ($validator) use ($request, $store) {
            $email = Str::lower(trim((string) $request->input('email')));
            $exists = Customer::query()
                ->where('ditta_cg18', (int) $store->ditta_cg18)
                ->whereRaw('LOWER(indemail_cg16) = ?', [$email])
                ->where(function ($query) use ($store) {
                    $query->whereNull('store_id')->orWhere('store_id', $store->id);
                })
                ->exists();

            if ($exists) {
                $validator->errors()->add('email', 'Esiste già un account con questo indirizzo email. Prova ad accedere o recupera la password.');
            }
        });

        $validated = $validator->validate();
        $name = trim($validated['first_name'] . ' ' . $validated['last_name']);

        $customer = Customer::query()->create([
            'store_id' => $store->id,
            'account_origin' => 'storefront',
            'ditta_cg18' => (int) $store->ditta_cg18,
            'tipocf_cg44' => null,
            'clifor_cg44' => null,
            'ragsoanag_cg16' => Str::limit($name, 60, ''),
            'indemail_cg16' => Str::lower(trim($validated['email'])),
            'password' => $validated['password'],
            'codrifalf_mg19' => 'PT',
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();

        return redirect()->intended(route('storefront.home'))
            ->with('status', 'Account creato correttamente.');
    }

    public function showForgotPasswordForm(Request $request): RedirectResponse|View
    {
        $store = $this->currentStore();

        if (Auth::guard('customer')->check()) {
            if ($this->isAgentMode($request)) {
                return redirect()->route('storefront.agent.customers');
            }

            return redirect()->route('storefront.home');
        }

        $loginPage = $this->storefrontPage($store, 'login');

        return view($this->themeResolver->view('auth.forgot-password', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->authLayout($store),
            'storefrontPage' => $loginPage,
            'storefrontPageBlocks' => $loginPage?->activeBlocks ?? collect(),
            'email' => old('email', (string) $request->query('email', '')),
        ]);
    }

    public function sendResetPasswordLink(Request $request): RedirectResponse
    {
        $store = $this->currentStore();

        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
            'auth_mode' => ['nullable', 'in:customer,agent'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $authMode = $this->normalizeAuthMode($validated['auth_mode'] ?? null);
        $identity = $this->resolvePasswordIdentity($store, $email, $authMode);
        $customer = $identity['customer'] ?? null;

        if ($customer instanceof Customer) {
            $token = Password::broker('customers')->createToken($customer);

            $resetUrl = route('storefront.password.reset', [
                'token' => $token,
                'email' => $email,
                'auth_mode' => (bool) ($identity['is_agent_login'] ?? false) ? 'agent' : 'customer',
            ]);

            Mail::to($email)->send(new CustomerPasswordResetMail(
                store: $store,
                customer: $customer,
                resetUrl: $resetUrl,
            ));
        }

        return back()->with(
            'status',
            'Se l’account esiste ed è abilitato, riceverai una email per reimpostare la password.'
        );
    }

    public function showResetPasswordForm(Request $request, string $token): RedirectResponse|View
    {
        $store = $this->currentStore();

        if (Auth::guard('customer')->check()) {
            if ($this->isAgentMode($request)) {
                return redirect()->route('storefront.agent.customers');
            }

            return redirect()->route('storefront.home');
        }

        $loginPage = $this->storefrontPage($store, 'login');

        return view($this->themeResolver->view('auth.reset-password', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->authLayout($store),
            'storefrontPage' => $loginPage,
            'storefrontPageBlocks' => $loginPage?->activeBlocks ?? collect(),
            'token' => $token,
            'email' => old('email', (string) $request->query('email', '')),
            'authMode' => old('auth_mode', (string) $request->query('auth_mode', 'customer')),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $store = $this->currentStore();

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'confirmed', 'min:8'],
            'auth_mode' => ['nullable', 'in:customer,agent'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $authMode = $this->normalizeAuthMode($validated['auth_mode'] ?? null);
        $identity = $this->resolvePasswordIdentity($store, $email, $authMode);
        $customer = $identity['customer'] ?? null;
        $agentAuth = $identity['agent_auth'] ?? null;
        $isAgentLogin = (bool) ($identity['is_agent_login'] ?? false);

        if (!$customer instanceof Customer) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Utente non valido o non abilitato.']);
        }

        $broker = Password::broker('customers');

        if (!$broker->tokenExists($customer, (string) $validated['token'])) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Link non valido o scaduto.']);
        }

        if ($isAgentLogin) {
            if (!$agentAuth instanceof AgentAuth) {
                return back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['email' => 'Agente non valido o non abilitato.']);
            }

            $agentAuth->forceFill([
                'password' => (string) $validated['password'],
                'email_verified_at' => $agentAuth->email_verified_at ?: now(),
                'magic_login_token_hash' => null,
                'magic_login_expires_at' => null,
                'magic_login_used_at' => null,
                'remember_token' => Str::random(60),
                'last_login_at' => now(),
            ])->save();

            $broker->deleteToken($customer);

            $freshCustomer = Customer::query()->findOrFail($customer->id);

            Auth::guard('customer')->login($freshCustomer, true);
            $request->session()->regenerate();
            $this->clearAgentSession($request, true);
            $this->storeAgentSession($request, $freshCustomer, $email);

            return redirect()
                ->route('storefront.agent.customers')
                ->with('status', 'Password agente aggiornata correttamente.');
        }

        $customer->forceFill([
            'password' => (string) $validated['password'],
            'email_verified_at' => $customer->email_verified_at ?: now(),
            'magic_login_token_hash' => null,
            'magic_login_expires_at' => null,
            'magic_login_used_at' => null,
            'remember_token' => Str::random(60),
            'last_login_at' => now(),
        ])->save();

        $broker->deleteToken($customer);

        event(new PasswordReset($customer));

        $freshCustomer = Customer::query()->findOrFail($customer->id);

        Auth::guard('customer')->login($freshCustomer, true);
        $request->session()->regenerate();
        $this->clearAgentSession($request, true);

        return redirect()
            ->route('storefront.home')
            ->with('status', 'Password aggiornata correttamente.');
    }

    public function login(Request $request): RedirectResponse
    {
        $store = $this->currentStore();

        $validated = $request->validate([
            'login' => ['nullable', 'string', 'max:190', 'required_without:email'],
            'email' => ['nullable', 'string', 'max:190', 'required_without:login'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'auth_mode' => ['nullable', 'in:customer,agent'],
        ]);

        $login = Str::lower(trim((string) ($validated['login'] ?? $validated['email'] ?? '')));
        $authMode = $this->normalizeAuthMode($validated['auth_mode'] ?? null);
        $rateLimitKey = $this->loginRateLimitKey($request, (int) $store->ditta_cg18, $login . '|' . ($authMode ?? 'auto'));

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::LOGIN_RATE_LIMIT_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return back()
                ->withInput($request->except('password'))
                ->withErrors([
                    'login' => 'Troppi tentativi. Riprova tra ' . $seconds . ' secondi.',
                    'email' => 'Troppi tentativi. Riprova tra ' . $seconds . ' secondi.',
                ]);
        }

        $identity = $this->resolveLoginIdentity($store, $login, $authMode);
        $customer = $identity['customer'] ?? null;
        $agentAuth = $identity['agent_auth'] ?? null;
        $isAgentLogin = (bool) ($identity['is_agent_login'] ?? false);

        $passwordIsValid = $customer instanceof Customer
            && ($isAgentLogin
                ? $agentAuth instanceof AgentAuth && $agentAuth->passwordMatches((string) $validated['password'])
                : $customer->hasUsablePassword() && Hash::check((string) $validated['password'], (string) $customer->password));

        if (!$passwordIsValid) {
            RateLimiter::hit($rateLimitKey, self::LOGIN_RATE_LIMIT_DECAY_SECONDS);

            return back()
                ->withInput($request->except('password'))
                ->withErrors([
                    'login' => 'Credenziali non valide.',
                    'email' => 'Credenziali non valide.',
                ]);
        }

        RateLimiter::clear($rateLimitKey);

        $customer->forceFill([
            'last_login_at' => now(),
            'email_verified_at' => $customer->email_verified_at ?: now(),
        ])->save();

        if ($isAgentLogin && $agentAuth instanceof AgentAuth) {
            $agentAuth->forceFill([
                'last_login_at' => now(),
            ])->save();
        }

        Auth::guard('customer')->login($customer, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();
        $this->clearAgentSession($request, true);

        if ($isAgentLogin) {
            $this->storeAgentSession($request, $customer, $login);

            return redirect()->route('storefront.agent.customers');
        }

        return redirect()->intended(route('storefront.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            Auth::guard('customer')->logout();
        }

        if ($request->hasSession()) {
            $request->session()->forget([
                'password_hash_customer',
                'url.intended',
                'agent_mode',
                'agent_login_email',
                'agent_code',
                'agent_name',
                'agent_contexts',
            ]);

            $request->session()->regenerateToken();
        }

        return redirect()
            ->route('storefront.login')
            ->with('status', 'Logout effettuato correttamente.');
    }

    public function sendMagicLink(Request $request): RedirectResponse
    {
        $store = $this->currentStore();

        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
            'auth_mode' => ['nullable', 'in:customer,agent'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $authMode = $this->normalizeAuthMode($validated['auth_mode'] ?? null);
        $rateLimitKey = $this->magicLinkRateLimitKey($request, (int) $store->ditta_cg18, $email . '|' . ($authMode ?? 'auto'));

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAGIC_LINK_RATE_LIMIT_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return back()
                ->withInput()
                ->withErrors(['email' => 'Hai richiesto troppi link. Riprova tra ' . $seconds . ' secondi.']);
        }

        $identity = $this->resolvePasswordIdentity($store, $email, $authMode);
        $customer = $identity['customer'] ?? null;
        $agentAuth = $identity['agent_auth'] ?? null;
        $isAgentLogin = (bool) ($identity['is_agent_login'] ?? false);

        RateLimiter::hit($rateLimitKey, self::MAGIC_LINK_RATE_LIMIT_DECAY_SECONDS);

        $canReceiveMagicLink = $customer instanceof Customer
            && ($isAgentLogin
                ? $agentAuth instanceof AgentAuth && $agentAuth->canReceiveMagicLink()
                : $customer->canReceiveMagicLink());

        if (!$canReceiveMagicLink) {
            return back()->with(
                'status',
                'Se l’account esiste ed è abilitato, riceverai un link di accesso via email.'
            );
        }

        $plainToken = Str::random(64);

        if ($isAgentLogin && $agentAuth instanceof AgentAuth) {
            $agentAuth->forceFill([
                'magic_login_token_hash' => hash('sha256', $plainToken),
                'magic_login_expires_at' => now()->addMinutes(self::MAGIC_LINK_EXPIRE_MINUTES),
                'magic_login_used_at' => null,
            ])->save();
        } else {
            $customer->forceFill([
                'magic_login_token_hash' => hash('sha256', $plainToken),
                'magic_login_expires_at' => now()->addMinutes(self::MAGIC_LINK_EXPIRE_MINUTES),
                'magic_login_used_at' => null,
            ])->save();
        }

        $signedUrl = URL::temporarySignedRoute(
            'storefront.magic-login.consume',
            now()->addMinutes(self::MAGIC_LINK_EXPIRE_MINUTES),
            [
                'customer' => $customer->id,
                'token' => $plainToken,
                'login_email' => $email,
                'auth_mode' => $isAgentLogin ? 'agent' : 'customer',
            ]
        );

        Mail::to($email)->send(new CustomerMagicLoginMail(
            store: $store,
            customer: $customer,
            signedUrl: $signedUrl,
            expireMinutes: self::MAGIC_LINK_EXPIRE_MINUTES,
        ));

        return back()->with(
            'status',
            'Se l’account esiste ed è abilitato, riceverai un link di accesso via email.'
        );
    }

    public function consumeMagicLink(Request $request): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $store = $this->currentStore();
        $customerId = (int) $request->route('customer');
        $plainToken = (string) $request->query('token', '');
        $loginEmail = Str::lower(trim((string) $request->query('login_email', '')));
        $authMode = $this->normalizeAuthMode((string) $request->query('auth_mode', null));
        $isAgentLink = $authMode === 'agent';

        $customer = Customer::query()
            ->active()
            ->webEnabled()
            ->where('id', $customerId)
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->first();

        if (!$customer instanceof Customer) {
            return redirect()
                ->route('storefront.login')
                ->withErrors(['email' => 'Link non valido o non più utilizzabile.']);
        }

        $agentAuth = $isAgentLink ? $this->resolveAgentAuth($store, $loginEmail) : null;
        $tokenOwner = $isAgentLink ? $agentAuth : $customer;

        if (!$tokenOwner) {
            return redirect()
                ->route('storefront.login')
                ->withErrors(['email' => 'Link non valido o non più utilizzabile.']);
        }

        $expectedHash = (string) ($tokenOwner->magic_login_token_hash ?? '');

        if (
            $plainToken === ''
            || $expectedHash === ''
            || !hash_equals($expectedHash, hash('sha256', $plainToken))
            || !$tokenOwner->magic_login_expires_at
            || $tokenOwner->magic_login_expires_at->isPast()
            || $tokenOwner->magic_login_used_at !== null
        ) {
            return redirect()
                ->route('storefront.login')
                ->withErrors(['email' => 'Link non valido o scaduto.']);
        }

        $tokenOwner->forceFill([
            'magic_login_used_at' => now(),
            'magic_login_token_hash' => null,
            'magic_login_expires_at' => null,
            'last_login_at' => now(),
            'email_verified_at' => $tokenOwner->email_verified_at ?: now(),
        ])->save();

        $customer->forceFill([
            'last_login_at' => now(),
            'email_verified_at' => $customer->email_verified_at ?: now(),
        ])->save();

        Auth::guard('customer')->login($customer, true);

        if ($request->hasSession()) {
            $request->session()->regenerate();
            $this->clearAgentSession($request, true);
        }

        if ($isAgentLink && $agentAuth instanceof AgentAuth) {
            $this->storeAgentSession($request, $customer, $loginEmail);

            return redirect()->route('storefront.agent.customers');
        }

        return redirect()->route('storefront.home');
    }

    private function isAgent(Customer $customer): bool
    {
        return trim((string) $customer->agente_mg17) !== ''
            && trim((string) $customer->indeemail_vwebdcg44) !== '';
    }

    private function isAgentLogin(Customer $customer, string $login): bool
    {
        $agentEmail = Str::lower(trim((string) $customer->indeemail_vwebdcg44));

        return $this->isAgent($customer)
            && $agentEmail !== ''
            && $agentEmail === Str::lower(trim($login));
    }

    private function storeAgentSession(Request $request, Customer $customer, string $agentLoginEmail): void
    {
        $request->session()->put([
            'agent_mode' => true,
            'agent_login_email' => Str::lower(trim($agentLoginEmail)),
            'agent_code' => trim((string) $customer->agente_mg17),
            'agent_name' => trim((string) $customer->ragsoanag_vwebdcg44),
        ]);
    }

    private function isAgentMode(Request $request): bool
    {
        return (bool) $request->session()->get('agent_mode', false);
    }


    private function clearAgentSession(Request $request, bool $includeIntended = false): void
    {
        $keys = [
            'agent_mode',
            'agent_login_email',
            'agent_code',
            'agent_name',
            'agent_contexts',
        ];

        if ($includeIntended) {
            $keys[] = 'url.intended';
        }

        $request->session()->forget($keys);
    }

    private function currentStore(): Store
    {
        /** @var Store $store */
        $store = app('currentStore');

        return $store;
    }

    private function storefrontPage(Store $store, string $slug): ?StorefrontPage
    {
        return StorefrontPage::query()
            ->with('activeBlocks')
            ->active()
            ->where('store_id', $store->id)
            ->where('slug', $slug)
            ->first();
    }

    private function normalizeAuthMode(?string $authMode): ?string
    {
        $authMode = Str::lower(trim((string) $authMode));

        return in_array($authMode, ['customer', 'agent'], true) ? $authMode : null;
    }

    private function resolveLoginIdentity(Store $store, string $login, ?string $authMode = null): array
    {
        if ($login === '') {
            return ['customer' => null, 'agent_auth' => null, 'is_agent_login' => false];
        }

        if ($authMode === 'customer') {
            return [
                'customer' => $this->resolveDirectCustomer($store, $login),
                'agent_auth' => null,
                'is_agent_login' => false,
            ];
        }

        if ($authMode === 'agent') {
            return $this->resolveAgentIdentity($store, $login, false);
        }

        $customer = $this->resolveDirectCustomer($store, $login);

        if ($customer instanceof Customer) {
            return ['customer' => $customer, 'agent_auth' => null, 'is_agent_login' => false];
        }

        return $this->resolveAgentIdentity($store, $login, false);
    }

    private function resolvePasswordIdentity(Store $store, string $email, ?string $authMode = null): array
    {
        if ($email === '') {
            return ['customer' => null, 'agent_auth' => null, 'is_agent_login' => false];
        }

        if ($authMode === 'customer') {
            return [
                'customer' => $this->resolveDirectCustomer($store, $email),
                'agent_auth' => null,
                'is_agent_login' => false,
            ];
        }

        if ($authMode === 'agent') {
            return $this->resolveAgentIdentity($store, $email, true);
        }

        $customer = $this->resolveDirectCustomer($store, $email);

        if ($customer instanceof Customer) {
            return ['customer' => $customer, 'agent_auth' => null, 'is_agent_login' => false];
        }

        return $this->resolveAgentIdentity($store, $email, true);
    }

    private function resolveDirectCustomer(Store $store, string $login): ?Customer
    {
        return Customer::query()
            ->authEnabled()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where(function ($query) use ($store) {
                $query->whereNull('store_id')->orWhere('store_id', $store->id);
            })
            ->where(function ($query) use ($login) {
                $query->whereRaw('LOWER(indemail_cg16) = ?', [$login])
                    ->orWhereRaw('LOWER(CAST(clifor_cg44 AS CHAR)) = ?', [$login])
                    ->orWhereRaw('LOWER(CAST(codice_cg16 AS CHAR)) = ?', [$login]);
            })
            ->orderByRaw('CASE WHEN store_id = ? THEN 0 ELSE 1 END', [$store->id])
            ->first();
    }

    private function resolveAgentIdentity(Store $store, string $email, bool $createAgentAuth): array
    {
        $customer = $this->resolveAgentLoginCustomer($store, $email);

        if (!$customer instanceof Customer) {
            return ['customer' => null, 'agent_auth' => null, 'is_agent_login' => false];
        }

        $agentAuth = $createAgentAuth
            ? $this->firstOrCreateAgentAuth($store, $email)
            : $this->resolveAgentAuth($store, $email);

        return [
            'customer' => $customer,
            'agent_auth' => $agentAuth,
            'is_agent_login' => $agentAuth instanceof AgentAuth,
        ];
    }

    private function resolveAgentLoginCustomer(Store $store, string $email): ?Customer
    {
        $normalizedEmail = Str::lower(trim($email));

        if ($normalizedEmail === '') {
            return null;
        }

        return Customer::query()
            ->active()
            ->webEnabled()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->whereRaw('LOWER(indeemail_vwebdcg44) = ?', [$normalizedEmail])
            ->orderBy('id')
            ->first();
    }

    private function resolveAgentAuth(Store $store, string $email): ?AgentAuth
    {
        $normalizedEmail = Str::lower(trim($email));

        if ($normalizedEmail === '') {
            return null;
        }

        return AgentAuth::query()
            ->active()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->whereRaw('LOWER(indeemail_vwebdcg44) = ?', [$normalizedEmail])
            ->first();
    }

    private function firstOrCreateAgentAuth(Store $store, string $email): AgentAuth
    {
        $normalizedEmail = Str::lower(trim($email));

        return AgentAuth::query()->firstOrCreate(
            [
                'ditta_cg18' => (int) $store->ditta_cg18,
                'indeemail_vwebdcg44' => $normalizedEmail,
            ],
            [
                'is_active' => true,
            ]
        );
    }

    private function loginRateLimitKey(Request $request, int $ditta, string $login): string
    {
        return 'customer-login|' . $ditta . '|' . $login . '|' . $request->ip();
    }

    private function magicLinkRateLimitKey(Request $request, int $ditta, string $email): string
    {
        return 'customer-magic-link|' . $ditta . '|' . $email . '|' . $request->ip();
    }
}

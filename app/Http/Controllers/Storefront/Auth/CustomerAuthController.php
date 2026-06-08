<?php

namespace App\Http\Controllers\Storefront\Auth;

use App\Http\Controllers\Controller;
use App\Mail\Storefront\Auth\CustomerMagicLoginMail;
use App\Mail\Storefront\Auth\CustomerPasswordResetMail;
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
use Illuminate\Support\Str;
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

    public function showForgotPasswordForm(Request $request): RedirectResponse|View
    {
        $store = $this->currentStore();

        if (Auth::guard('customer')->check()) {
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
        ]);

        $email = Str::lower(trim((string) $validated['email']));

        /** @var Customer|null $customer */
        $customer = Customer::query()
            ->authEnabled()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->whereRaw('LOWER(indemail_cg16) = ?', [$email])
            ->first();

        if ($customer instanceof Customer) {
            $token = Password::broker('customers')->createToken($customer);

            $resetUrl = route('storefront.password.reset', [
                'token' => $token,
                'email' => $customer->indemail_cg16,
            ]);

            Mail::to($customer->getEmailForPasswordReset())
                ->send(new CustomerPasswordResetMail(
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
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $store = $this->currentStore();

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));

        /** @var Customer|null $customer */
        $customer = Customer::query()
            ->authEnabled()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->whereRaw('LOWER(indemail_cg16) = ?', [$email])
            ->first();

        if (!$customer) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'email' => 'Utente non valido o non abilitato.',
                ]);
        }

        $broker = Password::broker('customers');

        if (!$broker->tokenExists($customer, (string) $validated['token'])) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'email' => 'Link non valido o scaduto.',
                ]);
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

        /** @var Customer $freshCustomer */
        $freshCustomer = Customer::query()->findOrFail($customer->id);

        Auth::guard('customer')->login($freshCustomer, true);
        $request->session()->regenerate();

        return redirect()->route('storefront.home')
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
        ]);

        $login = Str::lower(trim((string) ($validated['login'] ?? $validated['email'] ?? '')));
        $rateLimitKey = $this->loginRateLimitKey($request, (int) $store->ditta_cg18, $login);

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::LOGIN_RATE_LIMIT_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return back()
                ->withInput($request->except('password'))
                ->withErrors([
                    'login' => 'Troppi tentativi. Riprova tra ' . $seconds . ' secondi.',
                    'email' => 'Troppi tentativi. Riprova tra ' . $seconds . ' secondi.',
                ]);
        }

        /** @var Customer|null $customer */
        $customer = $this->resolveCustomerForLogin($store, $login);

        if (
            !$customer
            || !$customer->hasUsablePassword()
            || !Hash::check((string) $validated['password'], (string) $customer->password)
        ) {
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

        Auth::guard('customer')->login($customer, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->intended(route('storefront.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.login');
    }

    public function sendMagicLink(Request $request): RedirectResponse
    {
        $store = $this->currentStore();

        $validated = $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $rateLimitKey = $this->magicLinkRateLimitKey($request, (int) $store->ditta_cg18, $email);

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAGIC_LINK_RATE_LIMIT_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return back()
                ->withInput()
                ->withErrors([
                    'email' => 'Hai richiesto troppi link. Riprova tra ' . $seconds . ' secondi.',
                ]);
        }

        /** @var Customer|null $customer */
        $customer = Customer::query()
            ->authEnabled()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->whereRaw('LOWER(indemail_cg16) = ?', [$email])
            ->first();

        RateLimiter::hit($rateLimitKey, self::MAGIC_LINK_RATE_LIMIT_DECAY_SECONDS);

        if (!$customer || !$customer->canReceiveMagicLink()) {
            return back()->with(
                'status',
                'Se l’account esiste ed è abilitato, riceverai un link di accesso via email.'
            );
        }

        $plainToken = Str::random(64);

        $customer->forceFill([
            'magic_login_token_hash' => hash('sha256', $plainToken),
            'magic_login_expires_at' => now()->addMinutes(self::MAGIC_LINK_EXPIRE_MINUTES),
            'magic_login_used_at' => null,
        ])->save();

        $signedUrl = URL::temporarySignedRoute(
            'storefront.magic-login.consume',
            now()->addMinutes(self::MAGIC_LINK_EXPIRE_MINUTES),
            [
                'customer' => $customer->id,
                'token' => $plainToken,
            ]
        );

        Mail::to($customer->getEmailForPasswordReset())
            ->send(new CustomerMagicLoginMail(
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

        /** @var Customer|null $customer */
        $customer = Customer::query()
            ->authEnabled()
            ->where('id', $customerId)
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->first();

        if (!$customer) {
            return redirect()
                ->route('storefront.login')
                ->withErrors([
                    'email' => 'Link non valido o non più utilizzabile.',
                ]);
        }

        $expectedHash = (string) ($customer->magic_login_token_hash ?? '');

        if (
            $plainToken === ''
            || $expectedHash === ''
            || !hash_equals($expectedHash, hash('sha256', $plainToken))
            || !$customer->magic_login_expires_at
            || $customer->magic_login_expires_at->isPast()
            || $customer->magic_login_used_at !== null
        ) {
            return redirect()
                ->route('storefront.login')
                ->withErrors([
                    'email' => 'Link non valido o scaduto.',
                ]);
        }

        $customer->forceFill([
            'magic_login_used_at' => now(),
            'magic_login_token_hash' => null,
            'magic_login_expires_at' => null,
            'last_login_at' => now(),
            'email_verified_at' => $customer->email_verified_at ?: now(),
        ])->save();

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();

        return redirect()->intended(route('storefront.home'));
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

    private function resolveCustomerForLogin(Store $store, string $login): ?Customer
    {
        if ($login === '') {
            return null;
        }

        return Customer::query()
            ->authEnabled()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where(function ($query) use ($login) {
                $query->whereRaw('LOWER(indemail_cg16) = ?', [$login])
                    ->orWhereRaw('LOWER(CAST(clifor_cg44 AS CHAR)) = ?', [$login])
                    ->orWhereRaw('LOWER(CAST(codice_cg16 AS CHAR)) = ?', [$login]);
            })
            ->first();
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
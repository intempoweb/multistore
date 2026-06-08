<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class AdminSetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $supported = LaravelLocalization::getSupportedLanguagesKeys(); // es: ['it','en','es']

        // 1) preferenza utente
        $userLocale = $request->user()?->locale;
        if ($userLocale && in_array($userLocale, $supported, true)) {
            app()->setLocale($userLocale);
            return $next($request);
        }

        // 2) sessione admin
        $sessionLocale = $request->session()->get('admin_locale');
        if ($sessionLocale && in_array($sessionLocale, $supported, true)) {
            app()->setLocale($sessionLocale);
            return $next($request);
        }

        // 3) browser
        $browser = $request->getPreferredLanguage($supported);
        app()->setLocale($browser ?: config('app.fallback_locale', 'en'));

        return $next($request);
    }
}
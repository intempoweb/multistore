<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class AdminLocaleController extends Controller
{
    public function set(Request $request, string $locale)
    {
        $supported = LaravelLocalization::getSupportedLanguagesKeys();

        abort_unless(in_array($locale, $supported, true), 404);

        // salva su sessione
        $request->session()->put('admin_locale', $locale);

        // salva su utente (preferenza persistente)
        if ($request->user()) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Deve essere loggato e admin
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        //  Admin accessibile SOLO da new.intempodistribution.it
        $allowedHost = parse_url(config('app.admin_url'), PHP_URL_HOST) ?: 'new.intempodistribution.it';

        if ($request->getHost() !== $allowedHost) {
            abort(404); // meglio 404 che 403 (non esponiamo /admin)
        }

        return $next($request);
    }
}
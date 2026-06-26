<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminSectionAccess
{
    public function handle(Request $request, Closure $next, string $section)
    {
        $user = $request->user();

        if (!$user || !method_exists($user, 'canAccessAdminSection') || !$user->canAccessAdminSection($section)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Non hai i permessi per accedere a questa sezione.',
                ], 403);
            }

            return redirect()
                ->route('admin.dashboard')
                ->with('warning', 'Non hai i permessi per accedere a questa sezione.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnexion automatique après N minutes sans activité (config jibayat.auto_logout_minutes).
 * Équivalent de app.py::_check_inactivity_timeout. Le compte à rebours côté navigateur
 * (resources/js/session-timer.js) redirige aussi vers la connexion à l'échéance.
 */
class AutoLogout
{
    public const SESSION_KEY = 'jibayat.last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $limit = max(1, (int) config('jibayat.auto_logout_minutes', 10)) * 60;
            $last = (int) $request->session()->get(self::SESSION_KEY, time());

            if (time() - $last > $limit) {
                activity('auth')->causedBy(Auth::user())->log('Déconnexion automatique (inactivité)');
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => 'Session expirée'], 401)
                    : redirect()->route('login')->with('status', 'Session fermée après inactivité.');
            }

            // Le ping de session (vérification) ne compte pas comme une activité.
            if (! $request->routeIs('session.status')) {
                $request->session()->put(self::SESSION_KEY, time());
            }
        }

        return $next($request);
    }
}

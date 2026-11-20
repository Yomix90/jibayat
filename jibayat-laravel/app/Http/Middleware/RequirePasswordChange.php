<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compte importé de Flask ou mot de passe réinitialisé par l'administrateur (Flask : doit_changer_mdp) :
 * l'utilisateur doit choisir un nouveau mot de passe avant d'aller ailleurs que sur son profil.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->must_change_password
            && ! $request->routeIs('profile.edit', 'profile.update', 'password.update', 'logout', 'session.*')) {
            return redirect()->route('profile.edit')
                ->with('status', 'password-change-required');
        }

        return $next($request);
    }
}

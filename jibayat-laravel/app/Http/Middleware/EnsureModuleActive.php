<?php

namespace App\Http\Middleware;

use App\Support\Navigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque les pages d'un module désactivé dans Paramètres ➜ Modules actifs
 * (Flask : is_system_module_active).
 */
class EnsureModuleActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route()?->getName();
        if ($route && ! Navigation::isModuleActive($route)) {
            abort(403, 'Ce module est désactivé dans les paramètres.');
        }

        return $next($request);
    }
}

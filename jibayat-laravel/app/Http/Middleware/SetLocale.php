<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/** Langue d'interface : celle du compte connecté, sinon celle choisie sur la page de connexion (session). */
class SetLocale
{
    public const LOCALES = ['fr', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale', config('app.locale'));
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'fr';
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}

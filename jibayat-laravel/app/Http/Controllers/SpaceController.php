<?php

namespace App\Http\Controllers;

use App\Support\Navigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Entrée d'un espace (/tnb, /locations…) : redirige vers son tableau de bord, ou vers sa première page visible.
 * Les anciennes adresses /espaces/{espace} redirigent ici.
 */
class SpaceController extends Controller
{
    public function __invoke(Request $request, Navigation $navigation, string $space): RedirectResponse
    {
        abort_unless($navigation->space($space), 404);
        $route = $navigation->entryRoute($request->user(), $space);
        abort_unless($route, 403);

        return redirect()->route($route);
    }
}

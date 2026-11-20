<?php

namespace App\Http\Controllers;

use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page vide (Phase 2) d'une entrée du registre de navigation.
 * Affiche l'en-tête de l'espace et l'écran Flask de référence qui sera porté en Phase 5.
 */
class PageController extends Controller
{
    public function __invoke(Request $request, Navigation $navigation): View
    {
        $page = $navigation->page($request->route()->getName());
        abort_if($page === null, 404);

        return view('pages.placeholder', [
            'page' => $page,
            'space' => $navigation->forUser($request->user())->get($page['space']),
        ]);
    }
}

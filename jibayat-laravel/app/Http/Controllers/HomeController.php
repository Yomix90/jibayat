<?php

namespace App\Http\Controllers;

use App\Domain\Dashboard\DashboardStats;
use App\Models\Municipality;
use App\Support\Navigation;
use Illuminate\Http\Request;

/**
 * Accueil = tableau de bord des statistiques (Flask : app.py::index).
 * Sans le droit « voir le tableau de bord », l'utilisateur est envoyé sur sa première page autorisée.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, Navigation $navigation)
    {
        $user = $request->user();
        if (! $user->can('dashboard.voir')) {
            $first = $navigation->pagesForUser($user)->first();

            return $first ? redirect()->route($first['route']) : redirect()->route('profile.edit');
        }

        $stats = new DashboardStats;

        return view('home', [
            'year' => $stats->year(),
            'totals' => $stats->totals(),
            'modules' => $stats->modules($user),
            'monthly' => $stats->monthly(),
            'receipts' => $stats->latestReceipts(),
            'pending' => $stats->pendingSlips(),
            'declarations' => $stats->recentDeclarations(),
            'commune' => Municipality::first(),
        ]);
    }
}

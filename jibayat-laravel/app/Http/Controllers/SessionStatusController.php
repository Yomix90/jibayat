<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AutoLogout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ping du compte à rebours d'inactivité (ne prolonge pas la session).
 */
class SessionStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $limit = (int) config('jibayat.auto_logout_minutes', 10) * 60;
        $last = (int) $request->session()->get(AutoLogout::SESSION_KEY, time());

        return response()->json(['remaining' => max(0, $limit - (time() - $last))]);
    }
}

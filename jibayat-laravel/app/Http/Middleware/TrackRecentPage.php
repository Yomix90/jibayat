<?php

namespace App\Http\Middleware;

use App\Models\RecentPage;
use App\Support\Navigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mémorise les pages du registre de navigation consultées (puces « RÉCENTS »).
 */
class TrackRecentPage
{
    public function __construct(protected Navigation $navigation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $route = $request->route()?->getName();
        $user = $request->user();
        if ($user && $route && $request->isMethod('GET') && $response->isSuccessful()
            && $this->navigation->page($route) !== null) {
            $recent = RecentPage::firstOrNew(['user_id' => $user->id, 'route_name' => $route]);
            $recent->visits = ($recent->visits ?? 0) + 1;
            $recent->visited_at = now();
            $recent->save();
        }

        return $response;
    }
}

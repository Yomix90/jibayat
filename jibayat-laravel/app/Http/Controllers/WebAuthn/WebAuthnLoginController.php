<?php

namespace App\Http\Controllers\WebAuthn;

use App\Http\Middleware\AutoLogout;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;

/** Connexion par empreinte / clé d'accès (WebAuthn) : mêmes effets qu'une connexion par mot de passe. */
class WebAuthnLoginController
{
    public function options(AssertionRequest $request): Responsable
    {
        $username = trim((string) $request->input('username', ''));

        return $request->toVerify($username !== '' ? ['username' => $username, 'is_active' => true] : null);
    }

    public function login(AssertedRequest $request): JsonResponse
    {
        $user = $request->login();
        if (! $user || ! $user->is_active) {
            if ($user) {
                Auth::guard('web')->logout();
            }

            return response()->json(['message' => 'Empreinte non reconnue ou compte désactivé.'], 422);
        }
        $request->session()->put(AutoLogout::SESSION_KEY, time());
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        activity('auth')->causedBy($user)->withProperties(['ip' => $request->ip(), 'methode' => 'empreinte'])->log('Connexion');

        return response()->json(['redirect' => redirect()->intended(route('home'))->getTargetUrl()]);
    }
}

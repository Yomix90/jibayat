<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

/** Enregistrement d'une empreinte / clé d'accès (clé « résidente » : connexion sans saisir l'identifiant). */
class WebAuthnRegisterController
{
    public function options(AttestationRequest $request): Responsable
    {
        return $request->fastRegistration()->userless()->toCreate();
    }

    public function register(AttestedRequest $request): Response
    {
        $alias = trim((string) $request->input('alias', '')) ?: 'Appareil du '.now()->format('d/m/Y');
        $request->save(['alias' => mb_substr($alias, 0, 60)]);
        activity('auth')->causedBy($request->user())->withProperties(['appareil' => $alias])->log('Empreinte enregistrée');

        return response()->noContent();
    }
}

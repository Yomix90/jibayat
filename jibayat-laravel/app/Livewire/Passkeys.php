<?php

namespace App\Livewire;

use Livewire\Component;

/** Mon profil ➜ Empreinte : appareils enregistrés (WebAuthn), ajout et retrait. */
class Passkeys extends Component
{
    public function remove(string $id): void
    {
        $c = auth()->user()->webAuthnCredentials()->whereKey($id)->firstOrFail();
        activity('auth')->causedBy(auth()->user())->withProperties(['appareil' => $c->alias])->log('Empreinte retirée');
        $c->delete();
        $this->dispatch('notify', message: 'Appareil retiré.', type: 'warning');
    }

    public function registered(): void
    {
        $this->dispatch('notify', message: 'Empreinte enregistrée : vous pourrez vous connecter avec elle sur cet appareil.');
    }

    public function render()
    {
        return view('livewire.passkeys', ['credentials' => auth()->user()->webAuthnCredentials()->whereEnabled()->latest()->get()]);
    }
}

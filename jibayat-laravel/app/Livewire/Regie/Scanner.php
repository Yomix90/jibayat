<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\DocumentRegistry;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

/**
 * Scanner : une feuille fait-elle foi ? Le QR imprimé en pied de page (lu par l'appareil photo d'un téléphone,
 * une douchette, ou le code tapé) ouvre cette page : la pièce est-elle au registre, son code de contrôle
 * correspond-il, a-t-elle été scannée et rangée ? Le scan papier peut être joint ici.
 */
#[Title('Scanner')]
class Scanner extends AppPage
{
    use WithFileUploads;

    public const ROUTE = 'regie.scanner';

    #[Url(as: 'code')]
    public string $code = '';

    public $scan;

    public function attach(DocumentRegistry $registry): void
    {
        $this->authorizeWrite('archives.ajouter');
        $result = $registry->verify($this->code);
        abort_unless(in_array($result['status'], ['valid', 'number_only'], true), 404);
        $this->validate(['scan' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']], [], ['scan' => 'scan']);
        $registry->attachScan($result['document'], $this->scan, auth()->user());
        $this->scan = null;
        $this->notify('Scan joint au registre.');
    }

    public function render(DocumentRegistry $registry)
    {
        $result = trim($this->code) !== '' ? $registry->verify($this->code) : null;

        return view('livewire.regie.scanner', ['result' => $result]);
    }
}

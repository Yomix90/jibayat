<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Models\PrintedDocument;
use App\Domain\Regie\Services\DocumentRegistry;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;

/** Contrôle du registre : pièces imprimées, scannées et rangées par famille ; pièces non scannées depuis 30 jours. */
#[Title('Contrôle du registre')]
class ControleRegistre extends AppPage
{
    public const ROUTE = 'regie.controle-registre';

    public function render(DocumentRegistry $registry)
    {
        return view('livewire.regie.controle-registre', ['c' => $registry->control(),
            'late' => PrintedDocument::with('printer')->whereNull('scan_path')->where('first_printed_at', '<', now()->subDays(30))->orderBy('first_printed_at')->limit(50)->get()]);
    }
}

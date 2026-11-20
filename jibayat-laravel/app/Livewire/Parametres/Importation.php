<?php

namespace App\Livewire\Parametres;

use App\Domain\Import\ExcelImportService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

/** Importation Excel — Flask /importation : modèle à télécharger, aperçu contrôlé, import transactionnel, journal. */
#[Title('Importation Excel')]
class Importation extends AppPage
{
    use WithFileUploads;

    public const ROUTE = 'parametres.importation';

    #[Url(as: 'module')]
    public string $module = 'DEBITS_BOISSONS';

    public $file;

    public ?array $preview = null;

    public ?array $result = null;

    public function mount(): void
    {
        if (! isset(ExcelImportService::MODULES[$this->module])) {
            $this->module = 'DEBITS_BOISSONS';
        }
    }

    public function updatedModule(): void
    {
        $this->reset('file', 'preview', 'result');
    }

    public function updatedFile(): void
    {
        $this->reset('preview', 'result');
    }

    public function analyse(ExcelImportService $service): void
    {
        $this->authorizeWrite('importation.ajouter');
        $this->validate(['file' => ['required', 'file', 'max:20480', 'extensions:xlsx,xlsm']], [], ['file' => 'fichier']);
        try {
            $p = $service->preview($this->file->getRealPath(), $this->module);
        } catch (\Throwable $e) {
            $this->addError('file', 'Lecture impossible : '.$e->getMessage());

            return;
        }
        if (! $p['total']) {
            $this->addError('file', 'Aucune ligne de données trouvée dans le fichier.');

            return;
        }
        $p['rows'] = array_slice($p['rows'], 0, 50);
        $this->preview = $p;
    }

    public function import(ExcelImportService $service): void
    {
        $this->authorizeWrite('importation.ajouter');
        $this->validate(['file' => ['required', 'file', 'max:20480', 'extensions:xlsx,xlsm']], [], ['file' => 'fichier']);
        $this->result = $service->execute($this->file->getRealPath(), $this->module, $this->file->getClientOriginalName(), auth()->user());
        $this->reset('file', 'preview');
        $r = $this->result;
        $this->notify("Import terminé : {$r['success']} ligne(s) importée(s), {$r['created']} contribuable(s) créé(s)".($r['errors'] ? ', '.count($r['errors']).' erreur(s).' : '.'),
            $r['errors'] ? 'warning' : 'success');
    }

    public function render(ExcelImportService $service)
    {
        [$order, $tariffs] = $service->activeTariffs($this->module);

        return view('livewire.parametres.importation', ['cfg' => ExcelImportService::MODULES[$this->module], 'stats' => $service->stats(),
            'order' => $order, 'tariffs' => $tariffs]);
    }
}

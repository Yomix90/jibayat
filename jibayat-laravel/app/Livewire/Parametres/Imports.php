<?php

namespace App\Livewire\Parametres;

use App\Domain\Import\ExcelImportService;
use App\Livewire\AppPage;
use App\Models\ImportLog;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

/** Journal des imports Excel (Flask imports_historique). */
#[Title('Journal des imports')]
class Imports extends AppPage
{
    use WithPagination;

    public const ROUTE = 'parametres.imports';

    public ?int $openId = null;

    public function render()
    {
        return view('livewire.parametres.imports', ['logs' => ImportLog::with('agent')->latest('id')->paginate(25), 'labels' => array_map(fn ($m) => $m['nom'], ExcelImportService::MODULES)]);
    }
}

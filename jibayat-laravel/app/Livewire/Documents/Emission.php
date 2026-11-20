<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Models\IssueSlip;
use App\Domain\Documents\Models\TreasuryStatement;
use App\Domain\Documents\Services\GirStatementParser;
use App\Domain\Documents\Services\IssueSlipService;
use App\Livewire\AppPage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

/**
 * Bordereaux d'émission — Flask /emission : tableau de l'année (12 mois), import du bordereau GIR mensuel,
 * détail du mois (modifier / retirer / ajouter une rubrique), configuration 1ère / 2ème partie, impressions.
 */
#[Title("Bordereaux d'émission")]
class Emission extends AppPage
{
    use WithFileUploads;

    public const ROUTE = 'documents.emission';

    #[Url(as: 'annee')]
    public int $year = 0;

    #[Url(as: 'mois')]
    public int $month = 0;

    public bool $showImport = false;

    public $file;

    public string $importMonth = '';

    public string $importYear = '';

    public bool $replace = false;

    public string $collective = '';

    public bool $showConfig = false;

    public array $config = [];

    public ?string $editCode = null;

    public string $editAmount = '';

    public bool $showAdd = false;

    public string $addCode = '';

    public string $addNature = '';

    public string $addAmount = '';

    public function mount(): void
    {
        $this->year = $this->year ?: (int) now()->year;
        if ($this->month < 0 || $this->month > 12) {
            $this->month = 0;
        }
    }

    public function openImport(?int $month = null): void
    {
        $this->authorizeWrite('emission.ajouter');
        $this->reset('file', 'replace', 'collective');
        $this->importMonth = $month ? (string) $month : '';
        $this->importYear = (string) $this->year;
        $this->resetValidation();
        $this->showImport = true;
    }

    public function import(IssueSlipService $service): void
    {
        $this->authorizeWrite('emission.ajouter');
        $this->validate([
            'file' => ['required', 'file', 'max:20480', 'extensions:xls,xlsx'], 'importMonth' => ['nullable', 'integer', 'between:1,12'],
            'importYear' => ['nullable', 'integer', 'between:2000,2100'], 'collective' => ['nullable', 'numeric', 'min:0'],
        ], [], ['file' => 'bordereau', 'importMonth' => 'mois', 'importYear' => 'année', 'collective' => 'montant collectif']);
        $parsed = (new GirStatementParser)->parse($this->file->getRealPath());
        $month = (int) $this->importMonth ?: $parsed['mois'];
        $year = (int) $this->importYear ?: $parsed['annee'];
        if (! $month || ! $year) {
            $this->addError('importMonth', "Impossible de détecter automatiquement le mois ou l'année dans le document : précisez-les.");

            return;
        }
        if (! $parsed['lignes']) {
            $this->addError('file', 'Aucune ligne de recette trouvée : vérifiez qu’il s’agit bien d’un bordereau de versement GIR.');

            return;
        }
        try {
            $service->import($parsed, $month, $year, $this->file->getClientOriginalName(), $this->replace, $this->collective !== '' ? (float) $this->collective : null, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->showImport = false;
        $this->year = $year;
        $this->month = $month;
        $this->notify('Import réussi pour '.IssueSlipService::MONTHS[$month]." $year : ".count($parsed['lignes']).' rubrique(s), bordereaux générés.');
    }

    public function openConfig(IssueSlipService $service): void
    {
        $this->authorizeWrite('config.gerer');
        $this->config = $service->config($this->year);
        $this->resetValidation();
        $this->showConfig = true;
    }

    public function saveConfig(IssueSlipService $service): void
    {
        $this->authorizeWrite('config.gerer');
        $this->validate([
            'config.first_part_mode' => ['required', 'in:auto,manuel'], 'config.first_part_value' => ['nullable', 'numeric', 'min:0'],
            'config.second_part_mode' => ['required', 'in:auto,manuel,vide'], 'config.second_part_value' => ['nullable', 'numeric', 'min:0'],
            'config.notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->saveConfig($this->year, $this->config, auth()->user());
        $this->showConfig = false;
        $this->notify("Configuration $this->year enregistrée : tous les bordereaux de l'année ont été recalculés.");
    }

    public function recalculate(IssueSlipService $service): void
    {
        $this->authorizeWrite('emission.modifier');
        $service->regenerateYear($this->year);
        $this->notify("Recalcul de l'année $this->year terminé.");
    }

    protected function statement(): TreasuryStatement
    {
        return TreasuryStatement::where('year', $this->year)->where('month', $this->month)->firstOrFail();
    }

    public function startEdit(string $code, string $amount): void
    {
        $this->authorizeWrite('emission.modifier');
        $this->editCode = $code;
        $this->editAmount = $amount;
    }

    public function saveAmount(IssueSlipService $service): void
    {
        $this->authorizeWrite('emission.modifier');
        $this->validate(['editAmount' => ['required', 'numeric', 'min:0']], [], ['editAmount' => 'montant']);
        $service->updateAmount($this->statement(), $this->editCode, (float) $this->editAmount, auth()->user());
        $this->notify("Montant de la rubrique $this->editCode modifié ; bordereaux recalculés.");
        $this->editCode = null;
    }

    public function deleteLine(string $code, IssueSlipService $service): void
    {
        $this->authorizeWrite('emission.supprimer');
        $service->deleteLine($this->statement(), $code, auth()->user());
        $this->notify("Rubrique $code retirée ; bordereaux recalculés.");
    }

    public function addLine(IssueSlipService $service): void
    {
        $this->authorizeWrite('emission.ajouter');
        $this->validate(['addCode' => ['required', 'regex:/^\d{6,20}$/'], 'addNature' => ['nullable', 'string', 'max:255'], 'addAmount' => ['required', 'numeric', 'gt:0']],
            ['addCode.regex' => 'Le code budgétaire comporte au moins 6 chiffres.'], ['addCode' => 'code budgétaire', 'addAmount' => 'montant']);
        try {
            $service->addLine($this->year, $this->month, $this->addCode, trim($this->addNature), (float) $this->addAmount, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors()->has('code') ? ['addCode' => $e->validator->errors()->first('code')] : $e->validator->errors());

            return;
        }
        $this->notify("Rubrique $this->addCode ajoutée.");
        $this->reset('addCode', 'addNature', 'addAmount', 'showAdd');
    }

    public function deleteMonth(IssueSlipService $service): void
    {
        $this->authorizeWrite('emission.supprimer');
        $st = $this->statement();
        $label = IssueSlipService::MONTHS[$st->month]." $st->year";
        $service->deleteMonth($st, auth()->user());
        $this->month = 0;
        $this->notify("Bordereaux de $label supprimés ; l'année a été resynchronisée.", 'warning');
    }

    public function render(IssueSlipService $service)
    {
        $years = TreasuryStatement::distinct()->orderByDesc('year')->pluck('year')->push($this->year)->unique()->sortDesc()->values();
        $data = ['years' => $years, 'summary' => $service->yearSummary($this->year), 'statement' => null, 'cfg' => $service->config($this->year)];
        if ($this->month) {
            $st = TreasuryStatement::where('year', $this->year)->where('month', $this->month)->first();
            if ($st) {
                $data['statement'] = $st;
                $data['slips'] = IssueSlip::where('treasury_statement_id', $st->id)->orderBy('number')->get();
                $data['globals'] = $service->globals($st);
                $data['cumulative'] = $service->cumulative($this->year, $this->month);
            }
        }

        return view('livewire.documents.emission', $data);
    }
}

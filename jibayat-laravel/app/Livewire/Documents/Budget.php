<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Models\BudgetLine;
use App\Domain\Documents\Services\BudgetService;
use App\Domain\Documents\Support\BudgetNomenclature;
use App\Livewire\AppPage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

/**
 * Gestion du budget — Flask /regie/budget : projet de budget des recettes et réalisations par chapitre et domaine,
 * saisie, synchronisation avec les encaissements Jibayat, import Excel, ajout / retrait de chapitre, impressions.
 */
#[Title('Gestion du budget')]
class Budget extends AppPage
{
    use WithFileUploads;

    public const ROUTE = 'documents.budget';

    #[Url(as: 'annee')]
    public int $year = 0;

    #[Url(as: 'lang')]
    public string $lang = 'fr';

    /** @var array<int, array{proposed: string, actual: string, previous_actual: string, notes: string}> */
    public array $values = [];

    public bool $showImport = false;

    public $file;

    public bool $showAdd = false;

    public array $line = [];

    public function mount(BudgetService $service): void
    {
        $this->year = $this->year ?: (int) now()->year;
        $this->lang = in_array($this->lang, ['fr', 'ar'], true) ? $this->lang : 'fr';
        $this->loadValues($service);
    }

    protected function loadValues(BudgetService $service): void
    {
        $service->ensureYear($this->year);
        $this->values = BudgetLine::where('year', $this->year)->get()->mapWithKeys(fn ($l) => [$l->id => [
            'proposed' => self::plain($l->proposed), 'actual' => self::plain($l->actual), 'previous_actual' => self::plain($l->previous_actual), 'notes' => (string) $l->notes,
        ]])->all();
    }

    protected static function plain($v): string
    {
        return (float) $v == 0 ? '0' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    public function updatedYear(BudgetService $service): void
    {
        $this->loadValues($service);
    }

    public function save(BudgetService $service): void
    {
        $this->authorizeWrite('budget.modifier');
        $this->validate(['values.*.proposed' => ['nullable', 'numeric', 'min:0'], 'values.*.actual' => ['nullable', 'numeric', 'min:0'],
            'values.*.previous_actual' => ['nullable', 'numeric', 'min:0'], 'values.*.notes' => ['nullable', 'string', 'max:500']],
            ['values.*.*.numeric' => 'Montant invalide.', 'values.*.*.min' => 'Montant négatif.']);
        $n = $service->save($this->year, $this->values, auth()->user());
        $this->loadValues($service);
        $this->notify("Budget $this->year enregistré ($n lignes).");
    }

    public function sync(BudgetService $service): void
    {
        $this->authorizeWrite('budget.modifier');
        $this->resetErrorBag();
        $n = $service->sync($this->year, auth()->user());
        $this->loadValues($service);
        $this->notify("Synchronisation réussie avec les encaissements $this->year ($n chapitre(s) mis à jour).");
    }

    public function import(BudgetService $service): void
    {
        $this->authorizeWrite('budget.modifier');
        $this->validate(['file' => ['required', 'file', 'max:20480', 'extensions:xls,xlsx']], [], ['file' => 'fichier']);
        try {
            $n = $service->import($this->file->getRealPath(), $this->year, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->reset('file', 'showImport');
        $this->loadValues($service);
        $this->notify("Budget $this->year importé ($n chapitre(s) enregistré(s)).");
    }

    public function openAdd(): void
    {
        $this->authorizeWrite('budget.modifier');
        $this->line = ['chapter' => '', 'gir_code' => '', 'label_fr' => '', 'label_ar' => '', 'domain' => 'ADMINISTRATION', 'proposed' => '', 'actual' => '', 'previous_actual' => ''];
        $this->resetValidation();
        $this->showAdd = true;
    }

    public function addLine(BudgetService $service): void
    {
        $this->authorizeWrite('budget.modifier');
        $this->validate([
            'line.chapter' => ['required', 'string', 'max:30', 'regex:/^[0-9.]+$/'], 'line.gir_code' => ['nullable', 'string', 'max:20'],
            'line.label_fr' => ['required_without:line.label_ar', 'nullable', 'string', 'max:255'], 'line.label_ar' => ['nullable', 'string', 'max:255'],
            'line.domain' => ['required', 'in:'.implode(',', array_keys(BudgetNomenclature::DOMAINS))],
            'line.proposed' => ['nullable', 'numeric', 'min:0'], 'line.actual' => ['nullable', 'numeric', 'min:0'], 'line.previous_actual' => ['nullable', 'numeric', 'min:0'],
        ], [], ['line.chapter' => 'chapitre', 'line.label_fr' => 'libellé', 'line.domain' => 'domaine']);
        try {
            $l = $service->addLine($this->year, $this->line, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->showAdd = false;
        $this->loadValues($service);
        $this->notify("Chapitre {$l->chapter} ajouté.");
    }

    public function deleteLine(int $id, BudgetService $service): void
    {
        $this->authorizeWrite('budget.supprimer');
        $this->resetErrorBag();
        $service->deleteLine(BudgetLine::where('year', $this->year)->findOrFail($id), auth()->user());
        $this->loadValues($service);
        $this->notify('Chapitre retiré du budget.', 'warning');
    }

    public function render(BudgetService $service)
    {
        return view('livewire.documents.budget', $service->overview($this->year) + ['years' => $service->years((int) now()->year)]);
    }
}

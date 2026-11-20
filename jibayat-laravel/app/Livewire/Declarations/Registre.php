<?php

namespace App\Livewire\Declarations;

use App\Domain\Declarations\Models\AssessmentDeclaration;
use App\Domain\Declarations\Services\DeclarationService;
use App\Livewire\AppPage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/** Registre des déclarations d'assiette : filtres, hors délai, signature, impression, annulation motivée. */
#[Title('Registre des déclarations d’assiette')]
class Registre extends AppPage
{
    use WithPagination;

    public const ROUTE = 'declarations.registre';

    #[Url(as: 'taxe')]
    public string $module = '';

    #[Url(as: 'type')]
    public string $type = '';

    #[Url(as: 'etat')]
    public string $status = '';

    #[Url(as: 'n')]
    public string $search = '';

    public ?int $cancelId = null;

    public string $cancelReason = '';

    public function updated(string $name): void
    {
        if (in_array($name, ['module', 'type', 'status', 'search'], true)) {
            $this->resetPage();
        }
        if ($name === 'module') {
            $this->type = '';
        }
    }

    protected function authorizeFor(AssessmentDeclaration $d): void
    {
        $this->authorizeWrite(DeclarationService::MODULES[$d->module]['perm'].'.modifier');
    }

    public function markSigned(int $id): void
    {
        $d = AssessmentDeclaration::findOrFail($id);
        $this->authorizeFor($d);
        $d->update(['signed' => true]);
        activity('declarations')->causedBy(auth()->user())->performedOn($d)->log("Déclaration {$d->number} signée par le redevable");
        $this->notify(__('Déclaration marquée comme signée.'));
    }

    public function askCancel(int $id): void
    {
        $this->authorizeFor(AssessmentDeclaration::findOrFail($id));
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function confirmCancel(DeclarationService $service): void
    {
        $d = AssessmentDeclaration::findOrFail($this->cancelId);
        $this->authorizeFor($d);
        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:255']], [], ['cancelReason' => 'motif']);
        if (! $d->isCancelled()) {
            $service->cancel($d, auth()->user(), $this->cancelReason);
        }
        $this->cancelId = null;
        $this->notify(__('Déclaration annulée ; elle reste visible avec son motif.'), 'warning');
    }

    public function render(DeclarationService $service)
    {
        $q = $service->query($this->module ?: null, $this->type ?: null, $this->status, $this->search);
        $base = AssessmentDeclaration::active();

        return view('livewire.declarations.registre', [
            'rows' => $q->with('declarable')->paginate(25),
            'kpi' => ['total' => (clone $base)->count(), 'late' => (clone $base)->where('is_late', true)->count(),
                'unsigned' => (clone $base)->where('signed', false)->count(), 'cessations' => (clone $base)->where('type', 'cessation')->count()],
        ]);
    }
}

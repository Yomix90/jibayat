<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Models\Employee;
use App\Domain\Documents\Models\Service;
use App\Domain\Documents\Models\StampBook;
use App\Domain\Documents\Models\StampValue;
use App\Domain\Documents\Services\StampService;
use App\Livewire\AppPage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/** Carnets — Flask /regie/carnets : liste filtrée et affectation d'un carnet en stock à un service et un employé. */
#[Title('Carnets — Régie des timbres')]
class Carnets extends AppPage
{
    use WithPagination;

    public const ROUTE = 'documents.carnets';

    #[Url(as: 'statut')]
    public string $status = '';

    #[Url(as: 'valeur')]
    public string $value = '';

    #[Url(as: 'service')]
    public string $service = '';

    public ?int $assignId = null;

    public string $assignService = '';

    public string $assignEmployee = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function openAssign(int $id): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->assignId = $id;
        $this->assignService = '';
        $this->assignEmployee = '';
        $this->resetValidation();
    }

    public function assign(StampService $stamps): void
    {
        $this->authorizeWrite('regie.modifier');
        $this->validate(['assignService' => ['required', 'exists:services,id'], 'assignEmployee' => ['required', 'exists:employees,id']], [],
            ['assignService' => 'service', 'assignEmployee' => 'employé']);
        try {
            $stamps->assign(StampBook::findOrFail($this->assignId), (int) $this->assignService, (int) $this->assignEmployee);
        } catch (ValidationException $e) {
            $this->notify($e->validator->errors()->first(), 'error');

            return;
        }
        $this->assignId = null;
        $this->notify('Carnet affecté.');
    }

    public function render()
    {
        return view('livewire.documents.carnets', [
            'books' => StampBook::with(['stampValue', 'service', 'employee', 'stampPack'])
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->when($this->value, fn ($q) => $q->where('stamp_value_id', $this->value))
                ->when($this->service, fn ($q) => $q->where('service_id', $this->service))
                ->orderByDesc('id')->paginate(50),
            'values' => StampValue::where('is_active', true)->orderBy('label')->get(),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::where('is_active', true)->when($this->assignService, fn ($q) => $q->where('service_id', $this->assignService))->orderBy('last_name')->get(),
        ]);
    }
}

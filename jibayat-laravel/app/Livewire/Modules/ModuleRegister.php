<?php

namespace App\Livewire\Modules;

use App\Livewire\AppPage;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Registre des paiements d'un module (Flask : /registre/paiements/<module>) : déclarations et
 * bulletins / quittances, filtres année et statut, totaux. Chaque sous-classe fixe MODULE et ROUTE.
 */
abstract class ModuleRegister extends AppPage
{
    use WithPagination;

    #[Url(as: 'annee')]
    public string $year = '';

    #[Url(as: 'statut')]
    public string $status = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    public function updating($name): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = DB::table('tax_declarations as d')
            ->leftJoin('payment_slips as s', fn ($j) => $j->on('s.tax_declaration_id', '=', 'd.id')->whereNull('s.cancelled_at'))
            ->leftJoin('taxpayers as t', 't.id', '=', 'd.taxpayer_id')
            ->where('d.module', static::MODULE)->where('d.status', '!=', 'annule')
            ->when($this->year, fn ($q) => $q->where('d.year', $this->year))
            ->when($this->status === 'paye', fn ($q) => $q->where(fn ($w) => $w->where('s.status', 'paye')->orWhere('d.status', 'paye')))
            ->when($this->status === 'en_attente', fn ($q) => $q->where(fn ($w) => $w->where('s.status', 'en_attente')->orWhere(fn ($x) => $x->where('d.status', 'emis')->whereNull('s.id'))))
            ->when($this->status === 'impaye', fn ($q) => $q->where(fn ($w) => $w->whereNull('s.id')->orWhere('s.status', '!=', 'paye')))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('d.number', 'like', "%{$this->search}%")
                ->orWhere('s.slip_number', 'like', "%{$this->search}%")->orWhere('s.receipt_number', 'like', "%{$this->search}%")
                ->orWhere('t.last_name', 'like', "%{$this->search}%")->orWhere('t.cin', 'like', "%{$this->search}%")));

        $totals = (clone $query)->selectRaw("COUNT(*) n, COALESCE(SUM(d.total),0) emitted, COALESCE(SUM(CASE WHEN s.status='paye' THEN s.amount ELSE 0 END),0) paid")->first();

        return view('livewire.modules.register', [
            'rows' => $query->orderByDesc('d.year')->orderByDesc('d.id')
                ->select(['d.id', 'd.number', 'd.year', 'd.quarter', 'd.total', 'd.status', 'd.notes', 'd.declared_on',
                    's.slip_number', 's.status as slip_status', 's.receipt_number', 's.receipt_date', 's.amount', 's.payment_mode',
                    't.last_name', 't.first_name', 't.company_name', 't.cin'])
                ->paginate(50),
            'totals' => $totals,
            'years' => DB::table('tax_declarations')->where('module', static::MODULE)->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year'),
        ]);
    }
}

<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Models\StampBook;
use App\Domain\Documents\Services\StampService;
use App\Livewire\AppPage;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;

/** Régie des timbres et tickets — tableau de bord (Flask /regie/) : stock par valeur, carnets affectés, versements par service et employé. */
#[Title('Timbres & tickets')]
class Timbres extends AppPage
{
    public const ROUTE = 'documents.timbres';

    public function render(StampService $service)
    {
        return view('livewire.documents.timbres', [
            'stats' => $service->stats(),
            'stock' => DB::table('stamp_values as v')->leftJoin('stamp_books as b', fn ($j) => $j->on('b.stamp_value_id', '=', 'v.id')->where('b.status', 'en_stock'))
                ->where('v.is_active', true)->groupBy('v.id', 'v.label', 'v.unit_value', 'v.units_per_book')
                ->selectRaw('v.label, v.unit_value, v.units_per_book, COUNT(b.id) as books')->get(),
            'assigned' => StampBook::with(['stampValue', 'service', 'employee'])->where('status', 'affecte')->latest('assigned_on')->limit(20)->get(),
            'byService' => DB::table('services as s')->leftJoin('stamp_books as b', 'b.service_id', '=', 's.id')->where('s.is_active', true)
                ->groupBy('s.id', 's.name')->selectRaw("s.name, SUM(b.status='affecte') as assigned, SUM(b.status='consomme') as consumed, COALESCE(SUM(b.amount_paid),0) as paid")
                ->orderByDesc('paid')->orderBy('s.name')->get(),
            'byEmployee' => DB::table('employees as e')->leftJoin('services as s', 's.id', '=', 'e.service_id')->leftJoin('stamp_books as b', 'b.employee_id', '=', 'e.id')
                ->where('e.is_active', true)->groupBy('e.id', 'e.last_name', 'e.first_name', 's.name')
                ->selectRaw("e.last_name, e.first_name, s.name as service, SUM(b.status='affecte') as assigned, SUM(b.status='consomme') as consumed, COALESCE(SUM(b.amount_paid),0) as paid")
                ->orderByDesc('paid')->limit(10)->get(),
        ]);
    }
}

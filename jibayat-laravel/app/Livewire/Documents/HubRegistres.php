<?php

namespace App\Livewire\Documents;

use App\Domain\Documents\Services\RegisterService;
use App\Livewire\AppPage;
use App\Support\PyMath;
use Livewire\Attributes\Title;

/** Hub des registres — Flask /registre/ : par taxe, redevables, déclarations, émis, recouvré, reste et taux. */
#[Title('Hub des registres')]
class HubRegistres extends AppPage
{
    public const ROUTE = 'documents.registres';

    public function render(RegisterService $service)
    {
        $stats = $service->moduleStats();
        $emitted = array_sum(array_column($stats, 'emitted'));
        $paid = array_sum(array_column($stats, 'paid'));

        return view('livewire.documents.hub-registres', [
            'stats' => $stats,
            'totals' => ['emitted' => $emitted, 'paid' => $paid, 'remaining' => max(0.0, PyMath::round($emitted - $paid, 2)),
                'rate' => $emitted > 0 ? PyMath::round($paid / $emitted * 100, 1) : 0.0,
                'declarations' => array_sum(array_column($stats, 'declarations')), 'receipts' => array_sum(array_column($stats, 'receipts'))],
        ]);
    }
}

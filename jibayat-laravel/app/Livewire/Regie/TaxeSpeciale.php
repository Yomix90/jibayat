<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Models\CashEntry;
use App\Livewire\AppPage;
use App\Livewire\Regie\Concerns\HasPeriod;
use App\Models\Rubric;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Bordereau de la taxe spéciale (abattage) : recettes du mois saisies sur la rubrique marquée « taxe spéciale »
 * (Paramètres ➜ Rubriques), avec les quantités (têtes, kg) et les montants.
 */
#[Title('Taxe spéciale')]
class TaxeSpeciale extends AppPage
{
    use HasPeriod;

    public const ROUTE = 'regie.taxe-speciale';

    #[Url(as: 'rubrique')]
    public string $rubric = '';

    public static function rubrics()
    {
        $special = Rubric::where('special_tax', true)->orderBy('code')->get();

        return $special->isNotEmpty() ? $special : Rubric::where('manual_entry', true)->where(fn ($q) => $q->where('label', 'like', '%abatt%')->orWhere('label', 'like', '%viande%'))->orderBy('code')->get();
    }

    public static function entries(int $rubricId, int $year, int $month)
    {
        return CashEntry::active()->where('status', 'valide')->where('rubric_id', $rubricId)->whereYear('entry_date', $year)->whereMonth('entry_date', $month)
            ->orderBy('entry_date')->orderBy('id')->get();
    }

    public function render()
    {
        $rubrics = self::rubrics();
        $rubric = $rubrics->firstWhere('id', (int) $this->rubric) ?? $rubrics->first();

        return view('livewire.regie.taxe-speciale', ['rubrics' => $rubrics, 'current' => $rubric,
            'entries' => $rubric ? self::entries($rubric->id, $this->year, $this->month) : collect()]);
    }
}

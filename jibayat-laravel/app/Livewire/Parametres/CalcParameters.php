<?php

namespace App\Livewire\Parametres;

use App\Models\CalcParameter;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;

/**
 * Paramètres de calcul (pénalités, majorations, amendes, échéances, seuils) — Flask : /parametres.
 * « Valeurs légales » ne remet à jour que les paramètres existants (comme Flask).
 */
#[Title('Paramètres de calcul')]
class CalcParameters extends SettingsPage
{
    public const ROUTE = 'parametres.calcul';

    /** Ordre d'affichage des modules (Flask : module_order). */
    public const MODULE_ORDER = ['TNB', 'DEBITS_BOISSONS', 'TRANSPORT_VOYAGEURS', 'STATIONNEMENT', 'FOURRIERE', 'OCCUPATION_DOMAINE', 'LOCATION_LOCAUX', 'AFFERMAGE_SOUKS', 'GLOBAL'];

    /** Valeurs légales (Flask : reinitialiser_parametres, Loi 47-06). */
    public const LEGAL_DEFAULTS = [
        ['TNB', 'PENALITE_RETARD', '10'], ['TNB', 'MAJORATION_1ER_MOIS', '5'], ['TNB', 'MAJORATION_MOIS_SUP', '0.5'],
        ['TNB', 'AMENDE_NON_DECLARATION', '15'], ['TNB', 'SEUIL_EMISSION', '200'], ['TNB', 'DATE_LIMITE', '31/03'],
        ['DEBITS_BOISSONS', 'PENALITE_RETARD', '10'], ['DEBITS_BOISSONS', 'MAJORATION_1ER_MOIS', '5'],
        ['DEBITS_BOISSONS', 'MAJORATION_MOIS_SUP', '0.5'], ['DEBITS_BOISSONS', 'AMENDE_NON_DECLARATION', '15'],
        ['DEBITS_BOISSONS', 'DATE_LIMITE_TRIMESTRE', '30'], ['DEBITS_BOISSONS', 'DATE_LIMITE', '31/03'],
        ['STATIONNEMENT', 'PENALITE_RETARD', '10'], ['STATIONNEMENT', 'MAJORATION_1ER_MOIS', '5'],
        ['STATIONNEMENT', 'MAJORATION_MOIS_SUP', '0.5'], ['STATIONNEMENT', 'DATE_LIMITE', '31/01'],
        ['TRANSPORT_VOYAGEURS', 'PENALITE_RETARD', '10'], ['TRANSPORT_VOYAGEURS', 'MAJORATION_1ER_MOIS', '5'],
        ['TRANSPORT_VOYAGEURS', 'MAJORATION_MOIS_SUP', '0.5'], ['TRANSPORT_VOYAGEURS', 'DATE_LIMITE', '31/01'],
        ['OCCUPATION_DOMAINE', 'PENALITE_RETARD', '10'], ['OCCUPATION_DOMAINE', 'MAJORATION_1ER_MOIS', '5'],
        ['OCCUPATION_DOMAINE', 'DATE_LIMITE', '31/01'], ['LOCATION_LOCAUX', 'PENALITE_RETARD', '10'],
        ['LOCATION_LOCAUX', 'DELAI_PAIEMENT_LOYER', '10'], ['AFFERMAGE_SOUKS', 'PENALITE_RETARD', '0'],
        ['AFFERMAGE_SOUKS', 'DATE_LIMITE', '31/01'], ['GLOBAL', 'SEUIL_EMISSION_MIN', '200'], ['GLOBAL', 'DELAI_AVIS', '30'],
    ];

    /** @var array<int, string> id ➜ valeur */
    public array $values = [];

    public function mount(): void
    {
        $this->values = CalcParameter::pluck('value', 'id')->map(fn ($v) => (string) $v)->all();
    }

    public function save(): void
    {
        $this->authorizeWrite();
        $this->validate(['values.*' => ['nullable', 'string', 'max:50']]);
        $changed = 0;
        DB::transaction(function () use (&$changed) {
            foreach (CalcParameter::whereIn('id', array_keys($this->values))->get() as $p) {
                $new = trim((string) ($this->values[$p->id] ?? ''));
                if ($new !== (string) $p->value) {
                    $p->update(['value' => $new]);
                    $changed++;
                }
            }
        });
        $this->notify("{$changed} paramètre(s) mis à jour.");
    }

    public function resetLegal(): void
    {
        $this->authorizeWrite();
        $n = 0;
        foreach (self::LEGAL_DEFAULTS as [$module, $code, $value]) {
            $p = CalcParameter::where('module', $module)->where('code', $code)->first();
            if ($p && (string) $p->value !== $value) {
                $p->update(['value' => $value]);
                $n++;
            }
        }
        $this->mount();
        $this->notify("Valeurs légales rétablies ({$n} paramètre(s) modifié(s)).");
    }

    public function render()
    {
        $order = array_flip(self::MODULE_ORDER);

        return view('livewire.parametres.calc-parameters', [
            'groups' => CalcParameter::orderBy('code')->get()->groupBy('module')
                ->sortBy(fn ($items, $module) => $order[$module] ?? 99),
        ]);
    }
}

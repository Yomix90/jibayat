<?php

namespace App\Domain\Transport\Services;

use App\Domain\Boissons\Services\BeverageCalculator;
use App\Domain\Transport\Models\Vehicle;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transport — droit de stationnement + taxe sur le transport public de voyageurs (TPV), par trimestre.
 * Portage de Flask modules/stationnement.py :
 *   trimestres_non_payes_sta ➜ unpaidQuarters()   _tarif_pour_type ➜ tariffFor()
 *   _get_sta_items_data      ➜ situation()        sta_payer (calcul) ➜ computeQuarter()
 * Vérifié par tests/Feature/Transport/TransportParityTest.php contre les fonctions Python.
 *
 * Règles (confirmées par le régisseur le 19/11/2026) : les deux taxes sont trimestrielles et configurées dans l'arrêté
 * fiscal — la valeur du tarif est le montant d'un trimestre ; tarif = tarif dont le libellé est le type du véhicule
 * (sinon le premier tarif) ; total = stationnement + TPV ; AUCUNE pénalité ni majoration de retard (ni sur le
 * stationnement, ni sur la TPV — Flask en appliquait sur la TPV) ; pas d'amende ; total < 200 DH ➜ « sous seuil ».
 */
class TransportCalculator
{
    public const MODULE = 'STATIONNEMENT';

    public const TPV = 'TRANSPORT_VOYAGEURS';

    public const BELOW_THRESHOLD = 200;

    /** @var array<string, Collection> */
    protected array $active = [];


    /** Tarifs actifs d'un module rattachés à l'arrêté actif (ou sans arrêté), par valeur croissante (Flask get_tarifs_module). */
    public function tariffs(string $module): Collection
    {
        if (isset($this->active[$module])) {
            return $this->active[$module];
        }
        $base = fn () => DB::table('tariffs as t')->join('rubrics as r', 'r.id', '=', 't.rubric_id')
            ->where('r.module', $module)->where('t.is_active', true)->orderBy('t.value')->orderBy('t.id');
        $rows = $base()->leftJoin('tax_orders as a', 'a.id', '=', 't.tax_order_id')
            ->where(fn ($q) => $q->where('a.status', 'actif')->orWhereNull('t.tax_order_id'))
            ->get(['t.id', 't.label', 't.value', 't.unit']);

        return $this->active[$module] = $rows->isEmpty() ? $base()->get(['t.id', 't.label', 't.value', 't.unit']) : $rows;
    }

    /** Montant trimestriel d'un tarif : la valeur de l'arrêté (les tarifs transport sont trimestriels). */
    public static function quarterly(?object $tariff): float
    {
        return $tariff ? PyMath::round((float) $tariff->value, 2) : 0.0;
    }

    /**
     * Tarif du type de véhicule : tarif de même libellé en vigueur à la date (arrêté de la période) ;
     * à défaut la règle Flask (libellé exact parmi les tarifs actifs, sinon le premier).
     */
    public function tariffFor(string $module, ?string $type, ?string $onDate = null): ?object
    {
        $label = mb_strtolower(trim((string) $type));
        if ($onDate && $label !== '') {
            $inForce = DB::table('tariffs as t')->join('rubrics as r', 'r.id', '=', 't.rubric_id')
                ->where('r.module', $module)->whereRaw('LOWER(TRIM(t.label)) = ?', [$label])
                ->where('t.start_date', '<=', $onDate)
                ->where(fn ($q) => $q->whereNull('t.end_date')->orWhere('t.end_date', '>', $onDate))
                ->orderByDesc('t.start_date')->orderByDesc('t.id')->first(['t.id', 't.label', 't.value', 't.unit']);
            if ($inForce) {
                return $inForce;
            }
        }
        $tariffs = $this->tariffs($module);

        return $tariffs->first(fn ($t) => mb_strtolower(trim((string) $t->label)) === $label) ?? $tariffs->first();
    }

    /** [stationnement, TPV] trimestriels pour un trimestre (arrêté en vigueur au 1er jour du trimestre). */
    public function amounts(Vehicle $vehicle, ?int $year = null, ?int $quarter = null): array
    {
        $on = $year ? sprintf('%04d-%02d-01', $year, ($quarter - 1) * 3 + 1) : CarbonImmutable::today()->toDateString();

        return [self::quarterly($this->tariffFor(self::MODULE, $vehicle->type, $on)), self::quarterly($this->tariffFor(self::TPV, $vehicle->type, $on))];
    }

    protected function declarations(Vehicle $vehicle): Collection
    {
        return DB::table('tax_declarations')->where('module', self::MODULE)->where('taxable_type', 'vehicle')
            ->where('taxable_id', $vehicle->id)->where('status', '!=', 'annule')->whereNull('cancelled_at')
            ->get(['year', 'quarter', 'status']);
    }

    public static function startOf(Vehicle $vehicle): array
    {
        return [(int) ($vehicle->tax_start_year ?: 2020), (int) ($vehicle->tax_start_quarter ?: 1)];
    }

    /** Trimestres échus depuis le début d'imposition, sans déclaration (payée ou émise). */
    public function unpaidQuarters(Vehicle $vehicle, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $done = $this->declarations($vehicle)->map(fn ($d) => "{$d->year}-{$d->quarter}")->flip();
        [$startYear, $startQuarter] = self::startOf($vehicle);
        $out = [];
        for ($y = $startYear; $y <= $today->year; $y++) {
            for ($q = 1; $q <= 4; $q++) {
                if (($y === $startYear && $q < $startQuarter) || isset($done["{$y}-{$q}"])) {
                    continue;
                }
                $due = BeverageCalculator::deadline($y, $q);
                if ($due <= $today->toDateString()) {
                    $out[] = ['annee' => $y, 'trimestre' => $q, 'label' => BeverageCalculator::QUARTERS[$q], 'echeance' => $due];
                }
            }
        }

        return $out;
    }

    /** Situation pour la liste (Flask _get_sta_items_data) : nombre de trimestres × (stationnement + TPV), sans pénalités. */
    public function situation(Vehicle $vehicle, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $decls = $this->declarations($vehicle);
        $paid = $decls->where('status', 'paye')->map(fn ($d) => "{$d->year}-{$d->quarter}")->flip();
        $issued = $decls->where('status', '!=', 'paye')->map(fn ($d) => "{$d->year}-{$d->quarter}")->flip();
        [$startYear, $startQuarter] = self::startOf($vehicle);
        [$sta, $tpv] = $this->amounts($vehicle);
        $perQuarter = PyMath::round($sta + $tpv, 2);

        $notDeclared = $pending = 0;
        $years = [];
        for ($y = $startYear; $y <= $today->year; $y++) {
            for ($q = 1; $q <= 4; $q++) {
                if (($y === $startYear && $q < $startQuarter) || isset($paid["{$y}-{$q}"])) {
                    continue;
                }
                if (BeverageCalculator::deadline($y, $q) <= $today->toDateString()) {
                    $years[$y] = true;
                    isset($issued["{$y}-{$q}"]) ? $pending++ : $notDeclared++;
                }
            }
        }

        return [
            'sta' => $sta, 'tpv' => $tpv, 'per_quarter' => $perQuarter,
            'nb_non_declares' => $notDeclared, 'nb_emises' => $pending, 'nb_non_paye' => $notDeclared + $pending,
            'annees_non_payees' => array_keys($years),
            'montant_non_declare' => PyMath::round($notDeclared * $perQuarter, 2),
            'montant_en_attente' => PyMath::round($pending * $perQuarter, 2),
            'montant_impaye' => PyMath::round(($notDeclared + $pending) * $perQuarter, 2),
        ];
    }

    /**
     * Calcul d'un trimestre (Flask sta_payer, sans pénalités). Stockage comme Flask : base = TPV, taux = stationnement.
     *
     * @return array{sta: float, tpv: float, principal: float, penalite: float, majoration: float, total: float, echeance: string, statut: string}
     */
    public function computeQuarter(float $sta, float $tpv, int $year, int $quarter, string $paidOn): array
    {
        $principal = PyMath::round($tpv + $sta, 2);

        return ['sta' => $sta, 'tpv' => $tpv, 'principal' => $principal, 'penalite' => 0.0, 'majoration' => 0.0,
            'total' => $principal, 'echeance' => BeverageCalculator::deadline($year, $quarter),
            'statut' => $principal < self::BELOW_THRESHOLD ? 'sous_seuil' : 'emis'];
    }
}

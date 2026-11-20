<?php

namespace App\Domain\Tnb\Services;

use App\Domain\Caisse\Services\Penalties;
use App\Domain\Declarations\Services\DeclarationService;
use App\Domain\Tnb\Models\Parcel;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calcul de la TNB — portage exact de Flask modules/tnb.py :
 *   _calcul_exoneration        ➜ isExempt()
 *   _calcul_annees_redevables  ➜ dueYears()
 *   _compute_tarifs_annee      ➜ computeYear()
 * Vérifié par tests/Feature/Tnb/TnbParityTest.php contre les fonctions Python.
 *
 * Règles : tarif de l'année (zone puis surface ; à défaut, surface seule) ; principal = valeur (unité « DH »)
 * ou superficie × valeur, au centime supérieur ; échéance au 28 février ; passé l'échéance :
 * amende = max(principal × AMENDE_NON_DECLARATION %, 500) + pénalité et majoration de retard ;
 * total au centime supérieur. Seuil d'émission : total < 200 DH ➜ « sous seuil ».
 */
class TnbCalculator
{
    public const BELOW_THRESHOLD = 200;

    protected ?Collection $tariffs = null;

    public function __construct(protected Penalties $penalties) {}

    /**
     * Tous les tarifs de la rubrique TNB (Flask : sans filtre « actif », ordre date de début décroissante).
     */
    public function tariffs(): Collection
    {
        return $this->tariffs ??= DB::table('tariffs as t')->join('rubrics as r', 'r.id', '=', 't.rubric_id')
            ->where('r.module', 'TNB')->orderByDesc('t.start_date')->orderBy('t.id')
            ->get(['t.id', 't.code', 't.label', 't.value', 't.unit', 't.surface_min', 't.surface_max', 't.start_date', 't.end_date'])
            ->map(fn ($t) => (object) [
                'id' => $t->id, 'code' => (string) $t->code, 'label' => (string) $t->label, 'value' => (float) $t->value,
                'unit' => (string) $t->unit, 'surface_min' => (float) ($t->surface_min ?? 0),
                'surface_max' => $t->surface_max !== null ? (float) $t->surface_max : INF,
                'start' => substr((string) $t->start_date, 0, 10), 'end' => $t->end_date ? substr((string) $t->end_date, 0, 10) : null,
            ]);
    }

    public function amendePercent(): float
    {
        return $this->penalties->param('TNB', 'AMENDE_NON_DECLARATION', 15);
    }

    /** Exonération : permis de construire dont la période d'exonération couvre l'année. */
    public static function isExempt(iterable $permits, int $year): bool
    {
        foreach ($permits as $p) {
            $p = (object) $p;
            if ($p->type === 'construction' && $p->exemption_end_year && ($p->exemption_start_year ?? 0) <= $year && $year <= $p->exemption_end_year) {
                return true;
            }
        }

        return false;
    }

    public function constructionPermits(Parcel $parcel): Collection
    {
        return DB::table('building_permits')->where('parcel_id', $parcel->id)->where('type', 'construction')
            ->get(['type', 'exemption_start_year', 'exemption_end_year']);
    }

    public static function startYear(Parcel $parcel): int
    {
        if ($parcel->tax_start_year) {
            return (int) $parcel->tax_start_year;
        }
        if ($parcel->acquired_on) {
            return max(2020, (int) $parcel->acquired_on->format('Y'));
        }

        return 2020;
    }

    /** Années redevables : du début d'imposition à l'année en cours, hors années payées et exonérées. */
    public function dueYears(Parcel $parcel, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $paid = DB::table('tax_declarations')->where('module', 'TNB')->where('taxable_type', 'parcel')
            ->where('taxable_id', $parcel->id)->where('status', 'paye')->pluck('year')->map(fn ($y) => (int) $y)->flip();
        $permits = $this->constructionPermits($parcel);

        $years = [];
        for ($y = self::startYear($parcel); $y <= $today->year; $y++) {
            if (! isset($paid[$y]) && ! self::isExempt($permits, $y)) {
                $years[] = $y;
            }
        }

        return $years;
    }

    public function exemptYears(Parcel $parcel, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $permits = $this->constructionPermits($parcel);
        $start = $parcel->acquired_on ? max(2020, (int) $parcel->acquired_on->format('Y')) : 2020;

        return array_values(array_filter(range($start, $today->year), fn ($y) => self::isExempt($permits, $y)));
    }

    /** Tarif applicable (Flask : passe 1 zone + surface, passe 2 surface seule). */
    public function tariffFor(string $zone, float $area, int $year): ?object
    {
        $z = mb_strtoupper(trim($zone ?: 'A'));
        $inYear = fn ($t) => $t->start <= "{$year}-12-31" && ($t->end === null || $t->end >= "{$year}-01-01");
        $inArea = fn ($t) => $t->surface_min <= $area && $area <= $t->surface_max;

        foreach ($this->tariffs() as $t) {
            if (! $inYear($t)) {
                continue;
            }
            $label = mb_strtoupper($t->label);
            $code = mb_strtoupper($t->code);
            $hasZone = false;
            foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
                if (str_contains($label, "ZONE {$letter}") || str_contains($code, "-{$letter}")) {
                    $hasZone = true;
                    break;
                }
            }
            if ($hasZone && (str_contains($label, "ZONE {$z}") || str_contains($code, "-{$z}") || $label === $z || $code === $z) && $inArea($t)) {
                return $t;
            }
        }
        foreach ($this->tariffs() as $t) {
            if ($inYear($t) && $inArea($t)) {
                return $t;
            }
        }

        return null;
    }

    /**
     * @return array{taux: float, principal: float, penalite: float, majoration: float, amende: float, total: float, tariff: ?object, echeance: string}
     */
    public function computeYear(string $zone, float $area, int $year, ?string $onDate = null, ?int $parcelId = null): array
    {
        $onDate ??= CarbonImmutable::today()->toDateString();
        $rate = $principal = 0.0;
        $tariff = $this->tariffFor($zone, $area, $year);
        if ($tariff) {
            $rate = $tariff->value;
            $principal = PyMath::ceilCents($tariff->unit === 'DH' ? $rate : $area * $rate);
        }
        $due = sprintf('%d-02-28', $year);
        $penalty = $surcharge = $fine = 0.0;
        if ($principal > 0 && $onDate > $due) {
            // Majoration pour défaut ou retard de déclaration (art. 134) : pas due si la déclaration
            // annuelle (art. 47) a été déposée avant le 1er mars.
            if (! ($parcelId && DeclarationService::timelyAnnual('TNB', $parcelId, $year))) {
                $fine = max(PyMath::ceilCents($principal * $this->amendePercent() / 100), 500);
            }
            [$penalty, $surcharge] = $this->penalties->compute($principal, $due, $onDate, 'TNB');
        }

        return [
            'taux' => $rate, 'principal' => $principal, 'penalite' => $penalty, 'majoration' => $surcharge,
            'amende' => (float) $fine, 'total' => PyMath::ceilCents($principal + $penalty + $surcharge + $fine),
            'tariff' => $tariff, 'echeance' => $due,
        ];
    }

    /**
     * Situation d'un terrain (Flask : _get_tnb_dossiers_data / tnb_paiement) : années non déclarées
     * (à émettre) et années émises en attente, avec le calcul de chaque année.
     */
    public function situation(Parcel $parcel, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $emitted = DB::table('tax_declarations')->where('module', 'TNB')->where('taxable_type', 'parcel')
            ->where('taxable_id', $parcel->id)->whereNotIn('status', ['paye', 'annule'])->whereNull('cancelled_at')
            ->pluck('year')->map(fn ($y) => (int) $y)->flip();

        $notDeclared = $pending = [];
        $totalNotDeclared = $totalPending = 0.0;
        foreach ($this->dueYears($parcel, $today) as $y) {
            $calc = $this->computeYear((string) ($parcel->zone ?? 'A'), (float) $parcel->area, $y, $today->toDateString(), $parcel->id) + ['annee' => $y];
            if (isset($emitted[$y])) {
                $pending[] = $calc;
                $totalPending += $calc['total'];
            } else {
                $notDeclared[] = $calc;
                $totalNotDeclared += $calc['total'];
            }
        }

        return [
            'not_declared' => $notDeclared,
            'pending' => $pending,
            'total_not_declared' => PyMath::ceilCents($totalNotDeclared),
            'total_pending' => PyMath::ceilCents($totalPending),
            'total_due' => PyMath::ceilCents($totalNotDeclared + $totalPending),
        ];
    }

    /** « De l'année 2021 à l'année 2026 » — Flask format_periode_non_payee (cas des années). */
    public static function periodLabel(array $years, bool $arabic = false): string
    {
        if (! $years) {
            return $arabic ? 'لا توجد فترات غير مسواة' : 'Aucune période impayée';
        }
        sort($years);
        [$from, $to] = [reset($years), end($years)];
        if ($from === $to) {
            return $arabic ? "عن سنة {$from}" : "Année {$from}";
        }

        return $arabic ? "من سنة {$from} إلى سنة {$to}" : "De l'année {$from} à l'année {$to}";
    }
}

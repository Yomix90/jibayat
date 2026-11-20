<?php

namespace App\Domain\Boissons\Services;

use App\Domain\Declarations\Services\DeclarationService;
use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Caisse\Services\Penalties;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Taxe sur les débits de boissons — portage de Flask modules/tdb.py :
 *   _get_deadline_tdb        ➜ deadline()
 *   trimestres_non_payes     ➜ unpaidQuarters()
 *   _get_taux_etablissement  ➜ rateFor()
 *   _get_tdb_items_data      ➜ situation()   (estimations de la liste)
 *   tdb_declarer (calcul)    ➜ computeQuarter()
 * Vérifié par tests/Feature/Boissons/BeverageParityTest.php contre les fonctions Python.
 *
 * Règles : taux (%) selon le type d'établissement ; principal = CA × taux / 100 (arrondi Python) ;
 * déclaré après l'échéance (dernier jour du mois suivant le trimestre) ➜ pénalité + majoration ;
 * T4 déclaré à partir du 1er avril de l'année suivante sans déclaration des recettes (art. 67-II) dans le délai
 * ➜ amende max(principal × 15 %, 500) ;
 * total < 200 DH ➜ « sous seuil ».
 */
class BeverageCalculator
{
    public const MODULE = 'DEBITS_BOISSONS';

    public const QUARTERS = [1 => 'T1 (Jan–Mar)', 2 => 'T2 (Avr–Jun)', 3 => 'T3 (Jul–Sep)', 4 => 'T4 (Oct–Déc)'];

    public const QUARTERS_AR = [1 => 'الفصل الأول', 2 => 'الفصل الثاني', 3 => 'الفصل الثالث', 4 => 'الفصل الرابع'];

    protected const DEADLINES = [1 => [4, 30], 2 => [7, 31], 3 => [10, 31], 4 => [1, 31]];

    public const BELOW_THRESHOLD = 200;

    protected ?Collection $tariffs = null;

    public function __construct(protected Penalties $penalties) {}

    /** Échéance légale (art. 65 loi 47-06) : dernier jour du mois qui suit le trimestre ; T4 ➜ 31 janvier N+1. */
    public static function deadline(int $year, int $quarter): string
    {
        [$m, $d] = self::DEADLINES[$quarter];

        return sprintf('%04d-%02d-%02d', $quarter === 4 ? $year + 1 : $year, $m, $d);
    }

    /**
     * Échéance utilisée pour les pénalités d'une déclaration. Flask (tdb_declarer) prenait le 31 janvier de la MÊME année
     * pour le T4 ; réglage config('jibayat.boissons.t4_deadline_next_year') (true = échéance légale, par défaut).
     */
    public static function penaltyDeadline(int $year, int $quarter): string
    {
        if ($quarter === 4 && ! config('jibayat.boissons.t4_deadline_next_year', true)) {
            return sprintf('%04d-01-31', $year);
        }

        return self::deadline($year, $quarter);
    }

    /** Tarifs actifs du module rattachés à un arrêté actif (ou sans arrêté), par valeur croissante (Flask get_tarifs_module). */
    public function tariffs(): Collection
    {
        if ($this->tariffs) {
            return $this->tariffs;
        }
        $base = fn () => DB::table('tariffs as t')->join('rubrics as r', 'r.id', '=', 't.rubric_id')
            ->where('r.module', self::MODULE)->where('t.is_active', true)->orderBy('t.value')->orderBy('t.id');
        $rows = $base()->leftJoin('tax_orders as a', 'a.id', '=', 't.tax_order_id')
            ->where(fn ($q) => $q->where('a.status', 'actif')->orWhereNull('t.tax_order_id'))
            ->get(['t.id', 't.label', 't.value']);
        if ($rows->isEmpty()) {
            $rows = $base()->get(['t.id', 't.label', 't.value']);
        }

        return $this->tariffs = $rows;
    }

    /** Taux selon les mots-clés du type d'établissement ; à défaut le premier tarif ; sans tarif 10 %. */
    public function rateFor(?string $type): float
    {
        $tariffs = $this->tariffs();
        if ($tariffs->isEmpty()) {
            return 10.0;
        }
        $clean = mb_strtolower(trim((string) $type));
        $keywords = [
            'cafe' => ['café', 'cafe', 'salon'],
            'restaurant' => ['restaurant', 'snack', 'pizzeria', 'fast'],
            'hotel' => ['hotel', 'hôtel', 'auberge', 'residence'],
            'bar' => ['bar', 'pub', 'lounge', 'debit', 'débit'],
        ];
        foreach ($keywords as $kws) {
            if (collect($kws)->contains(fn ($kw) => str_contains($clean, $kw))) {
                foreach ($tariffs as $t) {
                    $label = mb_strtolower((string) $t->label);
                    if (collect($kws)->contains(fn ($kw) => str_contains($label, $kw))) {
                        return (float) $t->value;
                    }
                }
            }
        }

        return (float) $tariffs->first()->value;
    }

    /**
     * Taux applicable à une date, pris dans l'arrêté fiscal : tarif de la rubrique « Débits de boissons » de même libellé
     * que la catégorie de l'établissement (tarif choisi à la création), en vigueur à cette date
     * (date de début ≤ date < date de fin). D'un arrêté à l'autre, un tarif garde son libellé (TaxOrderService).
     * Sans tarif lié (établissements importés de Flask) : règle Flask par mots-clés sur le type.
     */
    public function rateForOutlet(BeverageOutlet $outlet, ?string $onDate = null): float
    {
        if (! $outlet->tariff_id) {
            return $this->rateFor($outlet->type);
        }
        $onDate ??= CarbonImmutable::today()->toDateString();
        $label = DB::table('tariffs')->where('id', $outlet->tariff_id)->value('label');
        $inForce = DB::table('tariffs as t')->join('rubrics as r', 'r.id', '=', 't.rubric_id')
            ->where('r.module', self::MODULE)->where('t.label', $label)
            ->where('t.start_date', '<=', $onDate)
            ->where(fn ($q) => $q->whereNull('t.end_date')->orWhere('t.end_date', '>', $onDate))
            ->orderByDesc('t.start_date')->orderByDesc('t.id')->value('t.value');
        if ($inForce !== null) {
            return (float) $inForce;
        }

        return (float) DB::table('tariffs')->where('id', $outlet->tariff_id)->value('value');
    }

    /** Taux d'un trimestre : arrêté en vigueur au premier jour du trimestre. */
    public function rateForQuarter(BeverageOutlet $outlet, int $year, int $quarter): float
    {
        return $this->rateForOutlet($outlet, sprintf('%04d-%02d-01', $year, ($quarter - 1) * 3 + 1));
    }

    protected function declarations(BeverageOutlet $outlet): Collection
    {
        return DB::table('tax_declarations')->where('module', self::MODULE)->where('taxable_type', 'beverage_outlet')
            ->where('taxable_id', $outlet->id)->where('status', '!=', 'annule')->whereNull('cancelled_at')
            ->get(['id', 'year', 'quarter', 'status', 'base_amount', 'total']);
    }

    public static function startOf(BeverageOutlet $outlet): array
    {
        return [(int) ($outlet->tax_start_year ?: 2022), (int) ($outlet->tax_start_quarter ?: 1)];
    }

    /** Trimestres échus, depuis le début d'imposition, sans déclaration (payée ou émise). */
    public function unpaidQuarters(BeverageOutlet $outlet, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $done = $this->declarations($outlet)->map(fn ($d) => "{$d->year}-{$d->quarter}")->flip();
        [$startYear, $startQuarter] = self::startOf($outlet);
        $stops = DeclarationService::cessationPeriods($outlet->id);   // période d'arrêt déclarée : exonérée

        $out = [];
        for ($y = $startYear; $y <= $today->year; $y++) {
            for ($q = 1; $q <= 4; $q++) {
                if (($y === $startYear && $q < $startQuarter) || isset($done["{$y}-{$q}"]) || DeclarationService::isQuarterExempt($stops, $y, $q)) {
                    continue;
                }
                $due = self::deadline($y, $q);
                if ($due > $today->toDateString()) {
                    continue;
                }
                $out[] = ['annee' => $y, 'trimestre' => $q, 'label' => self::QUARTERS[$q], 'echeance' => $due];
            }
        }

        return $out;
    }

    /** Situation pour la liste (Flask _get_tdb_items_data) : trimestres dus, émis, estimation sur le dernier CA déclaré. */
    public function situation(BeverageOutlet $outlet, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $decls = $this->declarations($outlet);
        $paid = $decls->where('status', 'paye')->map(fn ($d) => "{$d->year}-{$d->quarter}")->flip();
        $issued = $decls->where('status', '!=', 'paye')->map(fn ($d) => "{$d->year}-{$d->quarter}")->flip();
        $last = $decls->sortBy([['year', 'desc'], ['quarter', 'desc']])->first();
        $lastBase = (float) ($last->base_amount ?? 0);
        [$startYear, $startQuarter] = self::startOf($outlet);
        $stops = DeclarationService::cessationPeriods($outlet->id);

        $notDeclared = $pending = 0;
        $years = [];
        for ($y = $startYear; $y <= $today->year; $y++) {
            for ($q = 1; $q <= 4; $q++) {
                if (($y === $startYear && $q < $startQuarter) || isset($paid["{$y}-{$q}"])
                    || (! isset($issued["{$y}-{$q}"]) && DeclarationService::isQuarterExempt($stops, $y, $q))) {
                    continue;
                }
                if (self::deadline($y, $q) <= $today->toDateString()) {
                    $years[$y] = true;
                    isset($issued["{$y}-{$q}"]) ? $pending++ : $notDeclared++;
                }
            }
        }
        $rate = $this->rateForOutlet($outlet);
        $perQuarter = $lastBase > 0 ? PyMath::round($lastBase * ($rate / 100), 2) : 0.0;
        $late = PyMath::round($perQuarter * $notDeclared, 2);
        $waiting = PyMath::round($perQuarter * $pending, 2);

        return [
            'rate' => $rate, 'last_base' => $lastBase, 'nb_non_declares' => $notDeclared, 'nb_emises' => $pending,
            'nb_non_paye' => $notDeclared + $pending, 'annees_non_payees' => array_keys($years),
            'montant_estime_retard' => $late, 'montant_en_attente' => $waiting, 'total_impaye' => PyMath::round($late + $waiting, 2),
        ];
    }

    /**
     * Calcul d'un trimestre déclaré (Flask tdb_declarer) au taux donné.
     *
     * @return array{base: float, taux: float, principal: float, penalite: float, majoration: float, amende: float, total: float, echeance: string, statut: string}
     */
    public function computeQuarter(float $base, float $rate, int $year, int $quarter, string $declaredOn, ?int $outletId = null): array
    {
        $principal = PyMath::round($base * $rate / 100, 2);
        $due = self::penaltyDeadline($year, $quarter);
        $penalty = $surcharge = $fine = 0.0;
        if ($principal > 0) {
            if ($declaredOn > $due) {
                [$penalty, $surcharge] = $this->penalties->compute($principal, $due, $declaredOn, self::MODULE);
            }
            // Majoration pour défaut de déclaration des recettes (art. 67-II, avant le 1er avril N+1) :
            // pas due si cette déclaration a été déposée dans le délai.
            if ($quarter === 4 && $declaredOn >= sprintf('%04d-04-01', $year + 1)
                && ! ($outletId && DeclarationService::timelyAnnual(self::MODULE, $outletId, $year))) {
                $fine = max(PyMath::round($principal * $this->penalties->param(self::MODULE, 'AMENDE_NON_DECLARATION', 15) / 100, 2), 500.0);
            }
        }
        $total = PyMath::round($principal + $penalty + $surcharge + $fine, 2);

        return ['base' => $base, 'taux' => $rate, 'principal' => $principal, 'penalite' => $penalty, 'majoration' => $surcharge,
            'amende' => $fine, 'total' => $total, 'echeance' => $due, 'statut' => $total < self::BELOW_THRESHOLD ? 'sous_seuil' : 'emis'];
    }

    /** « Du Trimestre 1 2025 au Trimestre 1 2026 » — Flask format_periode_non_payee (cas des trimestres). */
    public static function periodLabel(array $quarters, bool $arabic = false): string
    {
        if (! $quarters) {
            return $arabic ? 'لا توجد فترات غير مسواة' : 'Aucune période impayée';
        }
        usort($quarters, fn ($a, $b) => [$a['annee'], $a['trimestre']] <=> [$b['annee'], $b['trimestre']]);
        $first = reset($quarters);
        $last = end($quarters);
        if (count($quarters) === 1 || [$first['annee'], $first['trimestre']] === [$last['annee'], $last['trimestre']]) {
            return $arabic ? "عن الفصل {$first['trimestre']} لسنة {$first['annee']}" : "Trimestre {$first['trimestre']} de l'année {$first['annee']}";
        }

        return $arabic
            ? "من الفصل {$first['trimestre']} لسنة {$first['annee']} إلى الفصل {$last['trimestre']} لسنة {$last['annee']}"
            : "Du Trimestre {$first['trimestre']} {$first['annee']} au Trimestre {$last['trimestre']} {$last['annee']}";
    }
}

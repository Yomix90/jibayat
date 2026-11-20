<?php

namespace App\Domain\Souk\Services;

use App\Domain\Souk\Models\SoukContract;
use App\Domain\Souk\Models\SoukContractRenewal;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Affermage des souks — portage de Flask modules/souks.py :
 *   _calculer_periodes_renouvellement ➜ periods()      _get_redevance_pour_mois ➜ feeFor()
 *   _calculer_mois_non_payes_sou      ➜ unpaidMonths() _grouper_mois_par_periode ➜ groups()
 *   _get_sou_items_data               ➜ situation()
 * Vérifié par tests/Feature/Souk/SoukParityTest.php contre les fonctions Python.
 *
 * Règles : redevance mensuelle (à défaut annuelle / 12) ; le contrat se renouvelle automatiquement tous les
 * « durée » ans avec une revalorisation de « taux » % (taux 0 ou vide ➜ 5 %, comme Flask) ; mois dus du mois de début
 * au mois de min(date de fin, aujourd'hui), chacun à la redevance de sa période.
 */
class SoukCalculator
{
    public const MODULE = 'AFFERMAGE_SOUKS';

    public const MONTHS_FR = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

    public static function monthlyFee(SoukContract $c): float
    {
        return (float) $c->monthly_fee ?: (float) ($c->annual_fee ?? 0) / 12;
    }

    public static function rate(SoukContract $c): float
    {
        return (float) $c->increase_rate ?: 5.0;
    }

    public static function duration(SoukContract $c): int
    {
        return max(1, (int) ($c->duration_years ?: 1));
    }

    /** Fin d'une période : début + durée ans − 1 jour (relativedelta Python : 29/02 ➜ 28/02). */
    public static function periodEnd(CarbonImmutable $start, int $years): CarbonImmutable
    {
        return $start->addYearsNoOverflow($years)->subDay();
    }

    /**
     * Périodes depuis le début du contrat jusqu'à aujourd'hui.
     *
     * @return list<array{renouvellement: int, date_debut: string, date_fin: string, redevance_mensuelle: float}>
     */
    public function periods(SoukContract $c, ?CarbonImmutable $today = null): array
    {
        if (! $c->starts_on) {
            return [];
        }
        $today ??= CarbonImmutable::today();
        $years = self::duration($c);
        $rate = self::rate($c) / 100;
        $fee = PyMath::round(self::monthlyFee($c), 2);
        $start = CarbonImmutable::parse($c->starts_on->toDateString());
        // Prolongations enregistrées : la redevance choisie à la prolongation remplace la revalorisation automatique.
        $renewals = $c->id ? SoukContractRenewal::where('souk_contract_id', $c->id)->get()->keyBy('renewal_no') : collect();
        $out = [];
        for ($n = 0; $start->lessThanOrEqualTo($today); $n++) {
            $end = self::periodEnd($start, $years);
            if ($r = $renewals->get($n)) {
                $fee = PyMath::round((float) $r->monthly_fee, 2);
            }
            $out[] = ['renouvellement' => $n, 'date_debut' => $start->toDateString(), 'date_fin' => $end->toDateString(), 'redevance_mensuelle' => $fee];
            $start = $end->addDay();
            $fee = PyMath::round($fee * (1 + $rate), 2);
        }

        return $out;
    }

    /**
     * Redevance d'un mois (1er du mois). Hors de toute période : Flask prenait la DERNIÈRE période, ce qui facturait le
     * mois de début (contrat commencé en cours de mois) au tarif le plus récent. Ici, un mois antérieur à la première
     * période prend la redevance initiale (écart documenté).
     */
    public static function feeFor(array $periods, string $monthStart): array
    {
        foreach ($periods as $p) {
            if ($p['date_debut'] <= $monthStart && $monthStart <= $p['date_fin']) {
                return [$p['redevance_mensuelle'], $p['renouvellement']];
            }
        }
        if (! $periods) {
            return [0.0, 0];
        }
        $first = $periods[0];

        return $monthStart < $first['date_debut'] ? [$first['redevance_mensuelle'], 0] : [end($periods)['redevance_mensuelle'], 0];
    }

    /** Mois payés / émis (déclarations non annulées). */
    public function settledMonths(SoukContract $c): array
    {
        $paid = $emitted = [];
        foreach (DB::table('tax_declaration_periods as p')->join('tax_declarations as d', 'd.id', '=', 'p.tax_declaration_id')
            ->where('d.module', self::MODULE)->where('d.taxable_type', 'souk_contract')->where('d.taxable_id', $c->id)
            ->where('d.status', '!=', 'annule')->whereNull('d.cancelled_at')->get(['p.period', 'd.status']) as $r) {
            $r->status === 'paye' ? $paid[$r->period] = true : $emitted[$r->period] = true;
        }

        return ['paid' => $paid, 'emitted' => $emitted];
    }

    /**
     * @return list<array{mois: string, label: string, redevance: float, renouvellement: int, est_emis: bool}>
     */
    public function unpaidMonths(SoukContract $c, ?CarbonImmutable $today = null): array
    {
        if (! $c->starts_on) {
            return [];
        }
        $today ??= CarbonImmutable::today();
        $end = $c->ends_on ? CarbonImmutable::parse($c->ends_on->toDateString())->min($today) : $today;
        ['paid' => $paid, 'emitted' => $emitted] = $this->settledMonths($c);
        $periods = $this->periods($c, $today);

        $out = [];
        $cur = CarbonImmutable::parse($c->starts_on->toDateString())->startOfMonth();
        for (; $cur->lessThanOrEqualTo($end->startOfMonth()); $cur = $cur->addMonth()) {
            $key = $cur->format('Y-m');
            if (isset($paid[$key])) {
                continue;
            }
            [$fee, $n] = self::feeFor($periods, $cur->toDateString());
            $out[] = ['mois' => $key, 'label' => self::MONTHS_FR[$cur->month - 1].' '.$cur->year, 'redevance' => $fee,
                'renouvellement' => $n, 'est_emis' => isset($emitted[$key])];
        }

        return $out;
    }

    /** Regroupe les mois consécutifs de même redevance (Flask _grouper_mois_par_periode). */
    public static function groups(array $months): array
    {
        $groups = [];
        $cur = null;
        foreach ($months as $m) {
            if ($cur === null || $m['redevance'] != $cur['redevance_mensuelle']) {
                if ($cur) {
                    $groups[] = $cur;
                }
                $cur = ['renouvellement' => $m['renouvellement'], 'redevance_mensuelle' => $m['redevance'], 'nb_mois' => 1,
                    'montant' => $m['redevance'], 'mois_debut' => $m['mois'], 'mois_fin' => $m['mois'], 'mois' => [$m['mois']]];
            } else {
                $cur['nb_mois']++;
                $cur['montant'] = PyMath::round($cur['montant'] + $m['redevance'], 2);
                $cur['mois_fin'] = $m['mois'];
                $cur['mois'][] = $m['mois'];
            }
        }
        if ($cur) {
            $groups[] = $cur;
        }

        return $groups;
    }

    /** Situation pour la liste (Flask _get_sou_items_data). */
    public function situation(SoukContract $c, ?CarbonImmutable $today = null): array
    {
        $months = $this->unpaidMonths($c, $today);
        $notDeclared = array_values(array_filter($months, fn ($m) => ! $m['est_emis']));
        $emitted = array_values(array_filter($months, fn ($m) => $m['est_emis']));
        $totNd = array_sum(array_column($notDeclared, 'redevance'));
        $totEm = array_sum(array_column($emitted, 'redevance'));
        $periods = $this->periods($c, $today);
        $today ??= CarbonImmutable::today();

        return [
            'months' => $months, 'rev_mens' => self::monthlyFee($c),
            'current_fee' => $periods ? self::feeFor($periods, $today->startOfMonth()->toDateString())[0] : self::monthlyFee($c),
            'nb_non_declares' => count($notDeclared), 'nb_emises' => count($emitted), 'nb_mois_impaye' => count($months),
            'total_non_declare' => PyMath::round($totNd, 2), 'total_en_attente' => PyMath::round($totEm, 2),
            'total_impaye' => PyMath::round($totNd + $totEm, 2),
        ];
    }

    /** « Du mois 2025-01 au mois 2025-06 » (Flask sou_avis_non_paiement). */
    public static function periodLabel(array $months, bool $arabic = false): string
    {
        if (! $months) {
            return $arabic ? 'لا توجد فترات غير مسواة' : 'Aucune période impayée';
        }
        [$a, $b] = [$months[0]['mois'], end($months)['mois']];
        if ($a === $b) {
            return $arabic ? "عن شهر {$a}" : "Mois {$a}";
        }

        return $arabic ? "من شهر {$a} إلى شهر {$b}" : "Du mois {$a} au mois {$b}";
    }
}

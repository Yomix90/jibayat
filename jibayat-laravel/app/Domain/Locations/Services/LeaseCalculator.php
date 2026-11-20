<?php

namespace App\Domain\Locations\Services;

use App\Domain\Locations\Models\Lease;
use App\Domain\Locations\Models\LeaseSector;
use App\Domain\Locations\Models\LeaseTariff;
use App\Domain\Locations\Models\Shop;
use App\Models\Tariff;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Calculs des loyers — portage fidèle de Flask modules/location.py :
 *   _calculer_loyer            ➜ monthlyRent()
 *   _get_historique_tarif_bail ➜ tariffHistory()
 *   _calculer_mois_non_payes   ➜ unpaidMonths()
 *   _grouper_par_tarif         ➜ groupByTariff()
 * Vérifié par tests/Feature/Locations/LeaseParityTest.php contre les résultats des fonctions Python.
 */
class LeaseCalculator
{
    public const MONTHS_FR = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

    /** Groupe de tarif du bail : celui de la boutique s'il y en a une, sinon celui du bail. */
    public function leaseTariff(Lease $lease): ?LeaseTariff
    {
        if ($lease->shop_id) {
            $shop = Shop::find($lease->shop_id);

            return $shop ? LeaseTariff::find($shop->lease_tariff_id) : null;
        }

        return $lease->lease_tariff_id ? LeaseTariff::find($lease->lease_tariff_id) : null;
    }

    /**
     * Loyer mensuel actuel. Priorité : tarif de l'arrêté lié au groupe de tarif ➜ valeur du groupe de tarif
     * ➜ tarif du secteur ➜ loyer saisi sur le bail. Type « m2 » : × superficie du bail (si > 0).
     */
    public function monthlyRent(Lease $lease): float
    {
        $area = (float) $lease->area;
        $lt = $this->leaseTariff($lease);

        if ($lt && $lt->tariff_id) {
            $fiscal = Tariff::find($lt->tariff_id);
            if ($fiscal) {
                $value = (float) $fiscal->value;

                return $lt->tariff_type === 'm2' && $area > 0 ? PyMath::round($value * $area, 2) : $value;
            }
        }
        if ($lt) {
            return $lt->tariff_type === 'm2' && $area > 0 ? PyMath::round((float) $lt->value * $area, 2) : (float) $lt->value;
        }
        if ($lease->lease_sector_id && ($sector = LeaseSector::find($lease->lease_sector_id))) {
            return $sector->tariff_type === 'm2' && $area > 0
                ? PyMath::round((float) $sector->monthly_rate * $area, 2)
                : (float) $sector->monthly_rate;
        }

        return (float) $lease->monthly_rent;
    }

    /**
     * Historique des tarifs de l'arrêté fiscal pour ce bail : tous les tarifs portant le même code
     * (et la même rubrique) que le tarif lié, du plus ancien au plus récent.
     *
     * @return list<array{start: CarbonImmutable, end: ?CarbonImmutable, value: float, order: string}>
     */
    public function tariffHistory(Lease $lease): array
    {
        $lt = $this->leaseTariff($lease);
        if (! $lt || ! $lt->tariff_id || ! ($ref = Tariff::find($lt->tariff_id))) {
            return [];
        }
        $area = (float) $lease->area;

        return DB::table('tariffs as t')->leftJoin('tax_orders as o', 'o.id', '=', 't.tax_order_id')
            ->where('t.rubric_id', $ref->rubric_id)->where('t.code', $ref->code)
            ->orderBy('t.start_date')->orderBy('t.id')
            ->get(['t.value', 't.start_date', 't.end_date', 'o.number'])
            ->map(function ($r) use ($lt, $area) {
                $value = (float) $r->value;
                if ($lt->tariff_type === 'm2' && $area > 0) {
                    $value = PyMath::round($value * $area, 2);
                }

                return [
                    'start' => CarbonImmutable::parse($r->start_date)->startOfDay(),
                    'end' => $r->end_date ? CarbonImmutable::parse($r->end_date)->startOfDay() : null,
                    'value' => $value,
                    'order' => (string) $r->number,
                ];
            })->all();
    }

    /**
     * Mois réglés et mois émis (en attente) d'un bail, d'après les déclarations non annulées.
     *
     * @return array{paid: array<string, true>, emitted: array<string, true>}
     */
    public function settledMonths(Lease $lease): array
    {
        $rows = DB::table('tax_declaration_periods as p')
            ->join('tax_declarations as d', 'd.id', '=', 'p.tax_declaration_id')
            ->where('d.module', 'LOCATION_LOCAUX')->where('d.taxable_type', 'lease')->where('d.taxable_id', $lease->id)
            ->where('d.status', '!=', 'annule')->whereNull('d.cancelled_at')
            ->get(['p.period', 'd.status']);

        $paid = $emitted = [];
        foreach ($rows as $r) {
            if ($r->status === 'paye') {
                $paid[$r->period] = true;
            } else {
                $emitted[$r->period] = true;
            }
        }

        return ['paid' => $paid, 'emitted' => $emitted];
    }

    /**
     * Mois non payés, du mois de début jusqu'au mois de min(date de fin, aujourd'hui), avec le tarif
     * applicable à chaque mois (historique de l'arrêté, sinon loyer actuel).
     *
     * @return list<array{mois: string, label: string, tarif: float, tarif_source: string, est_emis: bool}>
     */
    public function unpaidMonths(Lease $lease, ?CarbonImmutable $today = null): array
    {
        if (! $lease->starts_on) {
            return [];
        }
        $today ??= CarbonImmutable::today();
        $start = CarbonImmutable::parse($lease->starts_on)->startOfMonth();
        $end = $lease->ends_on ? CarbonImmutable::parse($lease->ends_on)->min($today) : $today;
        $end = $end->startOfMonth();

        ['paid' => $paid, 'emitted' => $emitted] = $this->settledMonths($lease);
        $fixed = $this->monthlyRent($lease);
        $history = $this->tariffHistory($lease);
        $hasLocTariff = $lease->shop_id || $lease->lease_tariff_id;

        $months = [];
        for ($current = $start; $current->lessThanOrEqualTo($end); $current = $current->addMonth()) {
            $key = $current->format('Y-m');
            if (isset($paid[$key])) {
                continue;
            }
            $tariff = $fixed;
            $source = 'bail';
            if ($history) {
                $found = null;
                foreach (array_reverse($history) as $h) {
                    if ($h['start']->lessThanOrEqualTo($current) && ($h['end'] === null || $h['end']->greaterThan($current))) {
                        $found = $h;
                        break;
                    }
                }
                if ($found === null) {
                    foreach (array_reverse($history) as $h) {
                        if ($h['start']->lessThanOrEqualTo($current)) {
                            $found = $h;
                            break;
                        }
                    }
                }
                if ($found) {
                    $tariff = $found['value'];
                    $source = 'arrete:'.$found['order'];
                }
            } elseif ($hasLocTariff) {
                $source = 'loc_tarif';
            }
            $months[] = [
                'mois' => $key,
                'label' => self::MONTHS_FR[$current->month - 1].' '.$current->year,
                'tarif' => $tariff,
                'tarif_source' => $source,
                'est_emis' => isset($emitted[$key]),
            ];
        }

        return $months;
    }

    /** Regroupe les mois consécutifs de même tarif (affichage « 3 mois × 350 DH »). */
    public function groupByTariff(array $months): array
    {
        $groups = [];
        $current = null;
        foreach ($months as $m) {
            if ($current === null || $m['tarif'] != $current['tarif']) {
                if ($current) {
                    $groups[] = $current;
                }
                $current = ['tarif' => $m['tarif'], 'nb_mois' => 1, 'montant' => $m['tarif'], 'mois_debut' => $m['mois'], 'mois_fin' => $m['mois'], 'mois' => [$m['mois']]];
            } else {
                $current['nb_mois']++;
                $current['montant'] = PyMath::round($current['montant'] + $m['tarif'], 2);
                $current['mois_fin'] = $m['mois'];
                $current['mois'][] = $m['mois'];
            }
        }
        if ($current) {
            $groups[] = $current;
        }

        return $groups;
    }

    public static function total(array $months): float
    {
        return PyMath::round(array_sum(array_column($months, 'tarif')), 2);
    }

    /**
     * Situation d'un bail pour les listes (Flask : _get_loc_items_data) : mois non déclarés / émis, montants.
     */
    public function situation(Lease $lease, ?CarbonImmutable $today = null): array
    {
        $months = $this->unpaidMonths($lease, $today);
        $notDeclared = array_values(array_filter($months, fn ($m) => ! $m['est_emis']));
        $emitted = array_values(array_filter($months, fn ($m) => $m['est_emis']));

        return [
            'months' => $months,
            'nb_non_declares' => count($notDeclared),
            'nb_emises' => count($emitted),
            'nb_non_paye' => count($months),
            'total_non_declare' => self::total($notDeclared),
            'total_en_attente' => self::total($emitted),
            'total_du' => PyMath::round(self::total($notDeclared) + self::total($emitted), 2),
            'periode' => self::periodLabel($months),
        ];
    }

    /** « 6 mois du 1/2026 au 6/2026 » — Flask helpers.format_periode_non_payee (cas des mois). */
    public static function periodLabel(array $months, bool $arabic = false): string
    {
        if (! $months) {
            return $arabic ? 'مسوى بالكامل' : 'À jour';
        }
        $keys = array_column($months, 'mois');
        sort($keys);
        $fmt = fn ($k) => ((int) substr($k, 5, 2)).'/'.substr($k, 0, 4);
        $n = count($keys);
        [$from, $to] = [$fmt($keys[0]), $fmt(end($keys))];

        if ($n === 1 || $from === $to) {
            return $arabic ? "شهر واحد ({$from})" : "1 mois ({$from})";
        }

        return $arabic ? "{$n} شهراً من {$from} إلى {$to}" : "{$n} mois du {$from} au {$to}";
    }
}

<?php

namespace App\Domain\DomainePublic\Services;

use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Occupation du domaine public — portage de Flask modules/occupation.py.
 * Règles (confirmées par le régisseur le 20/11/2026) : montant = tarif de l'arrêté fiscal × superficie occupée ;
 * facturation au trimestre OU à l'année entière ; AUCUNE pénalité ni majoration de retard.
 *   _calculer_bases_tarif ➜ bases()   _get_odp_tarif ➜ tariffFor()   _get_odp_items_data ➜ situation()
 * Vérifié par tests/Feature/DomainePublic/OccupationParityTest.php contre les fonctions Python.
 */
class OccupationCalculator
{
    public const MODULE = 'OCCUPATION_DOMAINE';

    /** Seuil d'émission propre au domaine public (Flask : 100 DH). */
    public const BELOW_THRESHOLD = 100;

    public const QUARTERS = [1 => 'T1 (Jan–Mar)', 2 => 'T2 (Avr–Jun)', 3 => 'T3 (Jul–Sep)', 4 => 'T4 (Oct–Déc)'];

    /** Échéance d'un trimestre : son dernier jour (Flask DEADLINE_TRIM de l'ODP). */
    protected const DEADLINES = [1 => '03-31', 2 => '06-30', 3 => '09-30', 4 => '12-31'];

    protected ?Collection $tariffs = null;

    public static function deadline(int $year, int $quarter): string
    {
        return $quarter === 0 ? "{$year}-12-31" : "{$year}-".self::DEADLINES[$quarter];
    }

    /** Tarifs actifs de l'arrêté actif (ou sans arrêté), par valeur croissante (Flask get_tarifs_module). */
    public function tariffs(): Collection
    {
        if ($this->tariffs) {
            return $this->tariffs;
        }
        $base = fn () => DB::table('tariffs as t')->join('rubrics as r', 'r.id', '=', 't.rubric_id')
            ->where('r.module', self::MODULE)->where('t.is_active', true)->orderBy('t.value')->orderBy('t.id');
        $rows = $base()->leftJoin('tax_orders as a', 'a.id', '=', 't.tax_order_id')
            ->where(fn ($q) => $q->where('a.status', 'actif')->orWhereNull('t.tax_order_id'))
            ->get(['t.id', 't.code', 't.label', 't.value', 't.unit']);

        return $this->tariffs = $rows->isEmpty() ? $base()->get(['t.id', 't.code', 't.label', 't.value', 't.unit']) : $rows;
    }

    /**
     * Tarif du type d'occupation : même code ou même libellé (casse et espaces ignorés), parmi TOUS les tarifs.
     * Écart : la boucle Flask s'arrêtait au premier tarif (return None), donc seul le tarif le moins cher était
     * reconnu et les autres types faisaient échouer la déclaration. À date donnée, on prend le tarif de même libellé
     * en vigueur (arrêté de la période). Sans correspondance : premier tarif ; sans tarif : 50 DH/m²/an (Flask).
     */
    public function tariffFor(?string $type, ?string $onDate = null): object
    {
        $key = mb_strtolower(trim((string) $type));
        $found = $key === '' ? null : $this->tariffs()->first(fn ($t) => mb_strtolower(trim((string) $t->code)) === $key || mb_strtolower(trim((string) $t->label)) === $key);
        if ($found && $onDate) {
            $inForce = DB::table('tariffs as t')->join('rubrics as r', 'r.id', '=', 't.rubric_id')
                ->where('r.module', self::MODULE)->where('t.label', $found->label)
                ->where('t.start_date', '<=', $onDate)->where(fn ($q) => $q->whereNull('t.end_date')->orWhere('t.end_date', '>', $onDate))
                ->orderByDesc('t.start_date')->orderByDesc('t.id')->first(['t.id', 't.code', 't.label', 't.value', 't.unit']);
            $found = $inForce ?? $found;
        }

        return $found ?? $this->tariffs()->first() ?? (object) ['id' => null, 'code' => 'ODP-STD', 'label' => 'Tarif standard ODP', 'value' => 50.0, 'unit' => 'DH/m²/an'];
    }

    /**
     * [trimestre, année] = superficie × tarif selon l'unité (Flask _calculer_bases_tarif) :
     * trimestre ➜ ×1 / ×4 ; an ➜ ÷4 / ×1 ; mois ➜ ×3 / ×12 ; jour ➜ ×90 / ×365 ; sinon trimestriel.
     *
     * @return array{0: float, 1: float}
     */
    public static function bases(float $area, ?object $tariff): array
    {
        if (! $tariff) {
            return [0.0, 0.0];
        }
        $v = (float) $tariff->value;
        $unit = mb_strtolower((string) $tariff->unit);
        if (str_contains($unit, 'trimestre')) {
            $q = PyMath::round($area * $v, 2);

            return [$q, PyMath::round($q * 4, 2)];
        }
        if (str_contains($unit, 'an') || str_contains($unit, 'annuel')) {
            $y = PyMath::round($area * $v, 2);

            return [PyMath::round($y / 4, 2), $y];
        }
        if (str_contains($unit, 'mois')) {
            $q = PyMath::round($area * $v * 3, 2);

            return [$q, PyMath::round($q * 4, 2)];
        }
        if (str_contains($unit, 'jour')) {
            return [PyMath::round($area * $v * 90, 2), PyMath::round($area * $v * 365, 2)];
        }
        $q = PyMath::round($area * $v, 2);

        return [$q, PyMath::round($q * 4, 2)];
    }

    /** [trimestre, année, tarif] pour une période (arrêté en vigueur au 1er jour) ou aujourd'hui. */
    public function amounts(PublicOccupation $o, ?int $year = null, ?int $quarter = null): array
    {
        $on = $year ? sprintf('%04d-%02d-01', $year, $quarter ? ($quarter - 1) * 3 + 1 : 1) : CarbonImmutable::today()->toDateString();
        $tariff = $this->tariffFor($o->type, $on);

        return [...self::bases((float) $o->area, $tariff), $tariff];
    }

    public static function startOf(PublicOccupation $o): array
    {
        $y = $o->tax_start_year ?: ($o->starts_on ? (int) $o->starts_on->format('Y') : 2022);

        return [(int) $y, (int) ($o->tax_start_quarter ?: 1)];
    }

    /** Périodes déclarées (non annulées) : [« année-trimestre » => statut], trimestre 0 = année entière. */
    protected function declared(PublicOccupation $o): array
    {
        $out = [];
        foreach (DB::table('tax_declarations')->where('module', self::MODULE)->where('taxable_type', 'public_occupation')
            ->where('taxable_id', $o->id)->where('status', '!=', 'annule')->whereNull('cancelled_at')->get(['year', 'quarter', 'status']) as $d) {
            $key = "{$d->year}-{$d->quarter}";
            $out[$key] = ($out[$key] ?? null) === 'paye' ? 'paye' : $d->status;
        }

        return $out;
    }

    /**
     * Trimestres échus depuis le début d'imposition, non payés : [annee, trimestre, echeance, est_emis].
     * Un trimestre est couvert par sa déclaration ou par celle de son année entière.
     */
    public function dueQuarters(PublicOccupation $o, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $decl = $this->declared($o);
        [$sy, $sq] = self::startOf($o);
        $out = [];
        for ($y = $sy; $y <= $today->year; $y++) {
            for ($q = 1; $q <= 4; $q++) {
                if ($y === $sy && $q < $sq) {
                    continue;
                }
                $year = $decl["{$y}-0"] ?? null;
                $quarter = $decl["{$y}-{$q}"] ?? null;
                if ($year === 'paye' || $quarter === 'paye' || self::deadline($y, $q) > $today->toDateString()) {
                    continue;
                }
                $out[] = ['annee' => $y, 'trimestre' => $q, 'label' => self::QUARTERS[$q], 'echeance' => self::deadline($y, $q),
                    'est_emis' => $year !== null || $quarter !== null];
            }
        }

        return $out;
    }

    /** Situation pour la liste (Flask _get_odp_items_data). */
    public function situation(PublicOccupation $o, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        [$q, $y, $tariff] = $this->amounts($o);
        $due = $this->dueQuarters($o, $today);
        $nd = count(array_filter($due, fn ($d) => ! $d['est_emis']));
        $em = count($due) - $nd;
        $expired = ($o->ends_on && $o->ends_on->toDateString() < $today->toDateString()) || in_array($o->status, ['expire', 'suspendu'], true);

        return [
            'tariff' => $tariff, 'trim_base' => $q, 'an_base' => $y, 'est_expire' => $expired, 'quarters' => $due,
            'nb_non_declares' => $nd, 'nb_emises' => $em, 'nb_impayes' => $nd + $em,
            'montant_non_declare' => PyMath::round($nd * $q, 2), 'montant_en_attente' => PyMath::round($em * $q, 2),
            'montant_impaye' => PyMath::round(($nd + $em) * $q, 2),
        ];
    }

    /**
     * Périodes proposées à l'encaissement : trimestres échus non déclarés, et pour chaque année l'option « année entière »
     * si aucun de ses trimestres n'est déjà payé ou émis (écart : Flask la proposait toujours, d'où un double paiement possible).
     */
    public function payable(PublicOccupation $o, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $decl = $this->declared($o);
        [$sy, $sq] = self::startOf($o);
        $quarters = array_values(array_filter($this->dueQuarters($o, $today), fn ($d) => ! $d['est_emis']));
        $years = [];
        foreach (array_unique(array_column($quarters, 'annee')) as $y) {
            $taken = isset($decl["{$y}-0"]) || collect([1, 2, 3, 4])->contains(fn ($q) => isset($decl["{$y}-{$q}"]));
            if (! $taken && ! ($y === $sy && $sq > 1)) {
                $years[] = $y;
            }
        }

        return ['quarters' => $quarters, 'years' => $years];
    }

    /** Montant d'une période (trimestre 0 = année entière), sans pénalité. */
    public function computePeriod(PublicOccupation $o, int $year, int $quarter): array
    {
        [$q, $y, $tariff] = $this->amounts($o, $year, $quarter);
        $amount = $quarter === 0 ? $y : $q;

        return ['principal' => $amount, 'total' => $amount, 'taux' => (float) $tariff->value, 'tariff' => $tariff,
            'echeance' => self::deadline($year, $quarter), 'statut' => $amount < self::BELOW_THRESHOLD ? 'sous_seuil' : 'emis'];
    }

    public static function periodLabel(array $quarters, bool $arabic = false): string
    {
        return \App\Domain\Boissons\Services\BeverageCalculator::periodLabel($quarters, $arabic);
    }
}

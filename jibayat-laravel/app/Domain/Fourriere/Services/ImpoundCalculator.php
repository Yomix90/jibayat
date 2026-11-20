<?php

namespace App\Domain\Fourriere\Services;

use App\Domain\Fourriere\Models\ImpoundCase;
use App\Support\PyMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fourrière — portage de Flask modules/fourriere.py :
 *   _nb_jours ➜ days()   _enrichir_dossier ➜ amountDue()   _get_tarif ➜ dailyRate()   _get_fou_items_data ➜ stats()
 * Montant dû = nombre de jours (jour d'entrée compris, minimum 1) × tarif journalier du dossier + frais de remorquage.
 * Vérifié par tests/Feature/Fourriere/ImpoundParityTest.php contre les fonctions Python.
 */
class ImpoundCalculator
{
    public const MODULE = 'FOURRIERE';

    public const STATUSES = ['en_fourriere' => 'En fourrière', 'en_attente_sortie' => 'En attente de sortie', 'sortie' => 'Sorti'];

    /** Jours de garde : aujourd'hui − date de mise en fourrière + 1, minimum 1 ; 0 sans date. */
    public static function days(?string $impoundedOn, ?CarbonImmutable $today = null): int
    {
        if (! $impoundedOn) {
            return 0;
        }
        $today ??= CarbonImmutable::today();
        try {
            $d = CarbonImmutable::parse(substr($impoundedOn, 0, 10))->startOfDay();
        } catch (\Throwable) {
            return 0;
        }

        return max(1, (int) $d->diffInDays($today->startOfDay(), false) + 1);
    }

    public static function amountDue(ImpoundCase $c, ?CarbonImmutable $today = null): float
    {
        return PyMath::round(self::days($c->impounded_on?->toDateString(), $today) * (float) $c->daily_rate + (float) $c->towing_fee, 2);
    }

    /** @return array{days: int, amount: float, plus265: bool, plus365: bool} */
    public static function enrich(ImpoundCase $c, ?CarbonImmutable $today = null): array
    {
        $days = self::days($c->impounded_on?->toDateString(), $today);

        return ['days' => $days, 'amount' => self::amountDue($c, $today), 'plus265' => $days >= 265, 'plus365' => $days >= 365];
    }

    /** Types de véhicules : tarifs actifs de la rubrique Fourrière, hors remorquage (Flask : regroupés par libellé). */
    public function vehicleTypes(): Collection
    {
        return DB::table('tariffs as t')->join('rubrics as r', 'r.id', '=', 't.rubric_id')
            ->where('r.module', self::MODULE)->where('t.is_active', true)->where('t.label', 'not like', '%remorquage%')
            ->groupBy('t.label')->orderBy('t.label')
            ->selectRaw('t.label, MAX(t.value) as daily_rate')->get();
    }

    /** Tarif journalier d'un type (Flask _get_tarif : libellé exact, tarif actif) ; 0 si inconnu. */
    public function dailyRate(?string $type): float
    {
        $v = DB::table('tariffs as t')->join('rubrics as r', 'r.id', '=', 't.rubric_id')
            ->where('r.module', self::MODULE)->where('t.label', (string) $type)->where('t.is_active', true)->orderBy('t.id')->value('t.value');

        return $v !== null ? (float) $v : 0.0;
    }

    /** Frais de remorquage proposés par défaut : tarif « remorquage » de l'arrêté (0 sans tarif). */
    public function defaultTowingFee(): float
    {
        return (float) (DB::table('tariffs as t')->join('rubrics as r', 'r.id', '=', 't.rubric_id')
            ->where('r.module', self::MODULE)->where('t.is_active', true)->where('t.label', 'like', '%remorquage%')->value('t.value') ?? 0);
    }

    /** Indicateurs de la liste (Flask _get_fou_items_data). */
    public static function stats(Collection $rows): array
    {
        $notOut = $rows->where('case.status', '!=', 'sortie');

        return [
            'total' => $rows->count(),
            'actifs' => $rows->whereIn('case.status', ['en_fourriere', 'en_attente_sortie'])->count(),
            'en_fourriere' => $rows->where('case.status', 'en_fourriere')->count(),
            'attente_sortie' => $rows->where('case.status', 'en_attente_sortie')->count(),
            'sortie' => $rows->where('case.status', 'sortie')->count(),
            'plus265' => $notOut->where('days', '>=', 265)->count(),
            'plus365' => $notOut->where('days', '>=', 365)->count(),
            'total_du' => PyMath::round($notOut->sum('amount'), 2),
        ];
    }

    /** Un paiement validé par le régisseur existe-t-il ? (condition de sortie, Flask fou_sortie) */
    public static function hasValidatedPayment(ImpoundCase $c): bool
    {
        return DB::table('payment_slips as s')->join('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->where('d.module', self::MODULE)->where('d.taxable_type', 'impound_case')->where('d.taxable_id', $c->id)
            ->whereNull('s.cancelled_at')->whereIn('s.status', ['valide', 'encaisse', 'paye'])->exists();
    }

    public static function hasPendingPayment(ImpoundCase $c): bool
    {
        return DB::table('payment_slips as s')->join('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->where('d.module', self::MODULE)->where('d.taxable_type', 'impound_case')->where('d.taxable_id', $c->id)
            ->whereNull('s.cancelled_at')->where('s.status', 'en_attente')->exists();
    }
}

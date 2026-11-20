<?php

namespace App\Domain\Souk\Services;

use App\Domain\Souk\Models\SoukContract;
use App\Domain\Souk\Models\SoukContractRenewal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie d'un contrat d'affermage — Flask souks.sou_ajouter / sou_renouveler / sou_terminer.
 */
class ContractService
{
    public function __construct(protected SoukCalculator $calculator) {}

    /** Prochain n° : plus grand n° numérique + 1 (Flask get_next_seq_num). */
    public static function nextNumber(): string
    {
        $max = (int) SoukContract::whereRaw("number REGEXP '^[0-9]+$'")->max(DB::raw('CAST(number AS UNSIGNED)'));

        return (string) ($max > 0 ? $max + 1 : SoukContract::count() + 1);
    }

    /** Date de fin par défaut : début + durée − 1 jour. */
    public static function defaultEnd(string $startsOn, int $years): string
    {
        return SoukCalculator::periodEnd(CarbonImmutable::parse($startsOn), max(1, $years))->toDateString();
    }

    /**
     * Proposition de prolongation : la période qui suit la date de fin actuelle et sa redevance par défaut
     * (redevance de la période en cours augmentée du taux du contrat).
     *
     * @return array{renouvellement: int, date_debut: string, date_fin: string, redevance_actuelle: float, redevance: float, taux: float}
     */
    public function nextPeriod(SoukContract $c): array
    {
        $limit = $c->ends_on ? $c->ends_on->toDateString() : CarbonImmutable::today()->toDateString();
        $periods = $this->calculator->periods($c, CarbonImmutable::parse($limit)->addYears(SoukCalculator::duration($c) + 1));
        $current = null;
        foreach ($periods as $p) {
            if ($p['date_debut'] > $limit) {
                return ['renouvellement' => $p['renouvellement'], 'date_debut' => $p['date_debut'], 'date_fin' => $p['date_fin'],
                    'redevance_actuelle' => $current['redevance_mensuelle'] ?? SoukCalculator::monthlyFee($c), 'redevance' => $p['redevance_mensuelle'],
                    'taux' => SoukCalculator::rate($c)];
            }
            $current = $p;
        }
        throw new \LogicException('Aucune période suivante.');
    }

    /**
     * Prolongation en fin de période (règle de la commune, 20/11/2026) : le locataire peut prolonger son contrat d'une
     * période avec une augmentation de la redevance ; l'agent peut modifier le taux ou la redevance proposés.
     * Flask déplaçait la date de DÉBUT du contrat (mois impayés effacés) : ici le début est conservé, la date de fin est
     * prolongée et la redevance de la nouvelle période est enregistrée.
     *
     * @return array{date_debut: string, date_fin: string, redevance: float}
     */
    public function renew(SoukContract $c, User $by, ?float $monthlyFee = null, ?float $rate = null, ?string $notes = null): array
    {
        return DB::transaction(function () use ($c, $by, $monthlyFee, $rate, $notes) {
            $next = $this->nextPeriod($c);
            $fee = $monthlyFee ?? ($rate !== null ? round($next['redevance_actuelle'] * (1 + $rate / 100), 2) : $next['redevance']);
            $appliedRate = $next['redevance_actuelle'] > 0 ? round(($fee / $next['redevance_actuelle'] - 1) * 100, 2) : null;
            SoukContractRenewal::updateOrCreate(['souk_contract_id' => $c->id, 'renewal_no' => $next['renouvellement']], [
                'starts_on' => $next['date_debut'], 'ends_on' => $next['date_fin'], 'monthly_fee' => $fee, 'increase_rate' => $appliedRate,
                'notes' => $notes, 'agent_id' => $by->id]);
            $c->update(['ends_on' => $next['date_fin'], 'status' => 'actif']);
            $out = ['date_debut' => $next['date_debut'], 'date_fin' => $next['date_fin'], 'redevance' => $fee];
            activity('parametres')->causedBy($by)->performedOn($c)->withProperties($out + ['augmentation' => $appliedRate])
                ->log("Prolongation du contrat d'affermage {$c->number} jusqu'au {$next['date_fin']}");

            return $out;
        });
    }

    /** Résiliation : statut « résilié », la date de fin devient la date de résiliation (mois suivants non dus). */
    public function terminate(SoukContract $c, User $by, string $on, ?string $reason = null): void
    {
        $c->update(['status' => 'resilie', 'ends_on' => $on]);
        activity('parametres')->causedBy($by)->performedOn($c)->withProperties(['date' => $on, 'motif' => $reason])
            ->log("Résiliation du contrat d'affermage {$c->number}");
    }
}

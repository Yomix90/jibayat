<?php

namespace App\Domain\DomainePublic\Services;

use App\Domain\DomainePublic\Models\PublicOccupation;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Renouvellement d'une autorisation d'occupation — Flask occupation.odp_renouveler. */
class OccupationService
{
    public const DURATIONS = ['1_trimestre' => '3 mois', '2_trimestres' => '6 mois', '1_an' => '1 an'];

    /** La date de fin (ou aujourd'hui si elle est vide) est prolongée de 3 mois, 6 mois ou 1 an ; le statut redevient « en cours ». */
    public function renew(PublicOccupation $o, string $duration, User $by): string
    {
        $base = $o->ends_on ? CarbonImmutable::parse($o->ends_on->toDateString()) : CarbonImmutable::today();
        $new = match ($duration) {
            '1_trimestre' => $base->addMonthsNoOverflow(3),
            '2_trimestres' => $base->addMonthsNoOverflow(6),
            default => $base->addYearNoOverflow(),
        };
        $o->update(['ends_on' => $new->toDateString(), 'status' => 'actif']);
        activity('parametres')->causedBy($by)->performedOn($o)->withProperties(['jusqu_au' => $new->toDateString()])
            ->log("Renouvellement de l'occupation {$o->number}");

        return $new->toDateString();
    }
}

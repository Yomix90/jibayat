<?php

namespace App\Domain\Caisse\Services;

use App\Models\CalcParameter;
use App\Support\PyMath;
use Carbon\CarbonImmutable;

/**
 * Pénalité et majoration de retard — portage exact de Flask helpers.calculer_penalites (loi 47-06) :
 *   pénalité  = majore(montant × PENALITE_RETARD %)                         (défaut 10 %)
 *   mois      = écart calendaire, +1 si le jour de paiement dépasse le jour d'échéance (échéance < 28) ; min. 1
 *   majoration = majore(montant × MAJORATION_1ER_MOIS % + montant × MAJORATION_MOIS_SUP % × (mois − 1))  (5 % / 0,5 %)
 * « majore » = arrondi au centime supérieur (helpers.majore_centimes). Rien si le paiement est dans les délais.
 */
class Penalties
{
    /** @var array<string, float|null> */
    protected array $cache = [];

    /** Flask helpers.get_param : valeur numérique du paramètre, sinon la valeur par défaut. */
    public function param(string $module, string $code, float $default): float
    {
        $key = "{$module}.{$code}";
        if (! array_key_exists($key, $this->cache)) {
            $value = CalcParameter::where('module', $module)->where('code', $code)->value('value');
            $this->cache[$key] = is_numeric($value) ? (float) $value : null;
        }

        return $this->cache[$key] ?? $default;
    }

    /**
     * @return array{0: float, 1: float} [pénalité, majoration]
     */
    public function compute(float $amount, string $dueDate, ?string $paymentDate = null, string $module = 'GLOBAL'): array
    {
        try {
            $due = CarbonImmutable::parse(substr($dueDate, 0, 10))->startOfDay();
            $paid = CarbonImmutable::parse(substr($paymentDate ?? CarbonImmutable::today()->toDateString(), 0, 10))->startOfDay();
        } catch (\Throwable) {
            return [0.0, 0.0];
        }
        if ($paid->lessThanOrEqualTo($due)) {
            return [0.0, 0.0];
        }

        $penPct = $this->param($module, 'PENALITE_RETARD', 10) / 100;
        $maj1 = $this->param($module, 'MAJORATION_1ER_MOIS', 5) / 100;
        $majS = $this->param($module, 'MAJORATION_MOIS_SUP', 0.5) / 100;

        $penalty = PyMath::ceilCents($amount * $penPct);

        $months = ($paid->year - $due->year) * 12 + ($paid->month - $due->month);
        if ($paid->day > $due->day && $months > 0 && $due->day < 28) {
            $months++;
        }
        $months = max(1, $months);
        $extra = max(0, $months - 1);
        $surcharge = PyMath::ceilCents($amount * $maj1 + $amount * $majS * $extra);

        return [$penalty, $surcharge];
    }
}

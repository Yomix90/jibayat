<?php

namespace App\Support;

/**
 * Arrondis identiques à Python (Flask), pour la parité des calculs.
 *
 * Python round(x, n) arrondit la valeur binaire EXACTE du flottant, à la moitié paire :
 * round(2.675, 2) = 2.67 (2.675 vaut en réalité 2.67499999…), round(0.125, 2) = 0.12.
 * PHP round() n'a pas ce comportement : on passe donc par le développement décimal exact.
 */
class PyMath
{
    public static function round(float $value, int $digits = 2): float
    {
        if (! is_finite($value)) {
            return $value;
        }
        // Développement décimal exact du double (dtoa) sur 50 décimales (maximum de PHP) : suffisant pour trancher.
        $exact = sprintf('%.50F', $value);
        $negative = str_starts_with($exact, '-');
        $exact = ltrim($exact, '-');
        [$int, $frac] = explode('.', $exact) + [1 => ''];
        $frac = str_pad($frac, $digits + 1, '0');

        $kept = $int.substr($frac, 0, $digits);
        $rest = substr($frac, $digits);
        $first = (int) $rest[0];
        $tail = rtrim(substr($rest, 1), '0');

        $roundUp = $first > 5 || ($first === 5 && ($tail !== '' || ((int) substr($kept, -1)) % 2 === 1));
        $number = ltrim($kept, '0') === '' ? '0' : ltrim($kept, '0');
        if ($roundUp) {
            $number = bcadd($number, '1', 0);
        }
        $result = (float) bcdiv($number, bcpow('10', (string) $digits, 0), $digits);

        return $negative ? -$result : $result;
    }

    /** Python math.ceil(round(v * 100, 4)) / 100 — Flask helpers.majore_centimes. */
    public static function ceilCents(float $value): float
    {
        if ($value <= 0) {
            return 0.0;
        }
        $v = self::round($value, 6);

        return ceil(self::round($v * 100, 4)) / 100;
    }
}

<?php

namespace App\Support;

/**
 * Formats d'affichage (montants en DH à la marocaine : 12 345,67).
 */
class Format
{
    public static function money(float|int|string|null $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, ',', ' ');
    }

    /** Montant abrégé pour les indicateurs (12,3 k / 1,2 M). */
    public static function short(float|int|string|null $value): string
    {
        $v = (float) $value;

        return match (true) {
            abs($v) >= 1_000_000 => number_format($v / 1_000_000, 2, ',', ' ').' M',
            abs($v) >= 10_000 => number_format($v / 1_000, 1, ',', ' ').' k',
            default => number_format($v, 2, ',', ' '),
        };
    }

    public static function taxpayer(object $row): string
    {
        $name = trim(($row->last_name ?? '').' '.($row->first_name ?? ''));

        return $name !== '' ? $name : ($row->company_name ?? '—');
    }

    public static function period(object $row): string
    {
        return ($row->year ?? '').(! empty($row->quarter) ? ' T'.$row->quarter : '');
    }
}

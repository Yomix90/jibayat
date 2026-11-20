<?php

namespace App\Domain\Documents\Services;

/** Montant en toutes lettres — portage exact de Flask tgr_parser.chiffre_en_lettre (y compris ses accords). */
class AmountInWords
{
    protected const UNITS = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];

    protected const TENS = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

    protected static function hundreds(int $n, bool $pluralHundred = true): string
    {
        if ($n === 0) {
            return '';
        }
        $c = intdiv($n, 100);
        $rest = $n % 100;
        $parts = [];
        if ($c === 1) {
            $parts[] = 'cent';
        } elseif ($c > 1) {
            $parts[] = self::UNITS[$c].' cent'.($rest === 0 && $pluralHundred ? 's' : '');
        }
        if ($rest > 0) {
            if ($rest < 20) {
                $parts[] = self::UNITS[$rest];
            } else {
                $d = intdiv($rest, 10);
                $u = $rest % 10;
                if ($d === 7) {
                    $parts[] = $u === 1 ? 'soixante et onze' : 'soixante-'.self::UNITS[10 + $u];
                } elseif ($d === 9) {
                    $parts[] = 'quatre-vingt-'.self::UNITS[10 + $u];
                } elseif ($d === 8) {
                    $parts[] = $u === 0 ? 'quatre-vingts' : 'quatre-vingt-'.self::UNITS[$u];
                } elseif ($u === 1 && in_array($d, [2, 3, 4, 5, 6], true)) {
                    $parts[] = self::TENS[$d].' et un';
                } elseif ($u === 0) {
                    $parts[] = self::TENS[$d];
                } else {
                    $parts[] = self::TENS[$d].'-'.self::UNITS[$u];
                }
            }
        }

        return implode(' ', $parts);
    }

    public static function dirhams(float $amount): string
    {
        if ($amount == 0) {
            return 'zéro Dirham';
        }
        $whole = (int) $amount;
        $cents = (int) \App\Support\PyMath::round(($amount - $whole) * 100, 0);
        $millions = intdiv($whole, 1_000_000);
        $rest = $whole % 1_000_000;
        $thousands = intdiv($rest, 1000);
        $units = $rest % 1000;
        $parts = [];
        if ($millions > 0) {
            $parts[] = self::hundreds($millions).($millions === 1 ? ' million' : ' millions');
        }
        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'mille' : self::hundreds($thousands).' mille';
        }
        if ($units > 0) {
            $parts[] = self::hundreds($units, $thousands > 0 || $millions > 0);
        }
        $out = implode(' ', $parts).' Dirham'.($whole > 1 ? 's' : '');
        if ($cents > 0) {
            $out .= ' et '.self::hundreds($cents).' centime'.($cents > 1 ? 's' : '');
        }

        return $out;
    }
}

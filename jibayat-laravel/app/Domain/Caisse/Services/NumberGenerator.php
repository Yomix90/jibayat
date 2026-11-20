<?php

namespace App\Domain\Caisse\Services;

use Illuminate\Support\Facades\DB;

/**
 * Numérotation des pièces — Flask helpers.gen_num : « {préfixe}{année}{n° sur 5 chiffres} »,
 * n = 5 derniers chiffres du plus grand numéro de l'année + 1.
 * Appelé dans une transaction : la ligne la plus haute est verrouillée (lockForUpdate) pour éviter les doublons.
 */
class NumberGenerator
{
    public function next(string $table, string $column, string $prefix, ?int $year = null): string
    {
        $year ??= (int) now()->year;
        $base = $prefix.$year;
        $max = DB::table($table)->where($column, 'like', $base.'%')
            ->orderByRaw("CHAR_LENGTH({$column}) desc, {$column} desc")->lockForUpdate()->value($column);

        $n = $max && ctype_digit(substr($max, -5)) ? ((int) substr($max, -5)) + 1 : 1;

        return $base.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}

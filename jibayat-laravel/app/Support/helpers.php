<?php

if (! function_exists('tr')) {
    /**
     * Traduit un libellé venant d'une variable (tableaux d'indicateurs, statuts, types…) ;
     * renvoie tel quel tout ce qui n'est pas une chaîne ou n'a pas de traduction.
     */
    function tr(mixed $value): mixed
    {
        return is_string($value) && $value !== '' ? __($value) : $value;
    }
}

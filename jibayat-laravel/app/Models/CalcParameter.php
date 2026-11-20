<?php

namespace App\Models;

use App\Support\Concerns\Journaled;
use Illuminate\Database\Eloquent\Model;

/**
 * Paramètre de calcul. Table Flask d’origine : parametres_calcul.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class CalcParameter extends Model
{
    use Journaled;

    protected $table = 'calc_parameters';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }
}

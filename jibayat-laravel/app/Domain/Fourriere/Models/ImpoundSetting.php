<?php

namespace App\Domain\Fourriere\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Paramètre fourrière. Table Flask d’origine : fou_parametres.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class ImpoundSetting extends Model
{
    protected $table = 'impound_settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

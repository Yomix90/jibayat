<?php

namespace App\Domain\Documents\Models;

use App\Support\Concerns\Journaled;
use Illuminate\Database\Eloquent\Model;

/**
 * Valeur inactive (timbre). Table Flask d’origine : regie_valeurs.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class StampValue extends Model
{
    use Journaled;

    protected $table = 'stamp_values';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'unit_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}

<?php

namespace App\Domain\Fourriere\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Type de véhicule (fourrière). Table Flask d’origine : fou_types_vehicule.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class ImpoundVehicleType extends Model
{
    protected $table = 'impound_vehicle_types';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}

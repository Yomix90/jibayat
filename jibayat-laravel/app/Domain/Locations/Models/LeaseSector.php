<?php

namespace App\Domain\Locations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Secteur de location. Table Flask d’origine : loc_secteurs.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class LeaseSector extends Model
{
    protected $table = 'lease_sectors';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'monthly_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function leaseTariffs(): HasMany
    {
        return $this->hasMany(LeaseTariff::class);
    }
}

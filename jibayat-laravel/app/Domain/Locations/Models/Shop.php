<?php

namespace App\Domain\Locations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Boutique / local. Table Flask d’origine : loc_boutiques.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class Shop extends Model
{
    protected $table = 'shops';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
        ];
    }

    public function leaseTariff(): BelongsTo
    {
        return $this->belongsTo(LeaseTariff::class, 'lease_tariff_id');
    }
}

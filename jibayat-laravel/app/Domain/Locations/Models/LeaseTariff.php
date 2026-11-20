<?php

namespace App\Domain\Locations\Models;

use App\Models\Tariff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groupe de tarif de location. Table Flask d’origine : loc_tarifs.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class LeaseTariff extends Model
{
    protected $table = 'lease_tariffs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function leaseSector(): BelongsTo
    {
        return $this->belongsTo(LeaseSector::class, 'lease_sector_id');
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }
}

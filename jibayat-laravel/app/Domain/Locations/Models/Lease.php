<?php

namespace App\Domain\Locations\Models;

use App\Domain\Caisse\Models\TaxDeclaration;
use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Bail. Table Flask d’origine : baux.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class Lease extends Model
{
    protected $table = 'leases';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'monthly_rent' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'taxpayer_id');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_id');
    }

    public function leaseSector(): BelongsTo
    {
        return $this->belongsTo(LeaseSector::class, 'lease_sector_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function leaseTariff(): BelongsTo
    {
        return $this->belongsTo(LeaseTariff::class, 'lease_tariff_id');
    }

    public function declarations(): MorphMany
    {
        return $this->morphMany(TaxDeclaration::class, 'taxable');
    }
}

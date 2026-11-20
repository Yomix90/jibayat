<?php

namespace App\Domain\Transport\Models;

use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Caisse\Models\TaxDeclaration;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Véhicule (stationnement + TPV). Table Flask d’origine : vehicules.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class Vehicle extends Model
{
    protected $table = 'vehicles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'authorized_on' => 'date',
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

    public function declarations(): MorphMany
    {
        return $this->morphMany(TaxDeclaration::class, 'taxable');
    }
}

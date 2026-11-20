<?php

namespace App\Domain\Souk\Models;

use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contrat d’affermage. Table Flask d’origine : affermages.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class SoukContract extends Model
{
    protected $table = 'souk_contracts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'annual_fee' => 'decimal:2',
            'monthly_fee' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'increase_rate' => 'decimal:4',
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

    public function declarations(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Domain\Caisse\Models\TaxDeclaration::class, 'taxable');
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SoukContractDocument::class)->latest();
    }

    public const STATUSES = ['actif' => 'En cours', 'suspendu' => 'Suspendu', 'resilie' => 'Résilié', 'expire' => 'Expiré'];
}

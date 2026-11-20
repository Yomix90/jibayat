<?php

namespace App\Domain\Fourriere\Models;

use App\Models\Municipality;
use App\Models\Taxpayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dossier de fourrière. Table Flask d’origine : dossiers_fourriere.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class ImpoundCase extends Model
{
    protected $table = 'impound_cases';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'impounded_on' => 'date',
            'daily_rate' => 'decimal:2',
            'towing_fee' => 'decimal:2',
            'paid_on' => 'date',
            'released_on' => 'date',
            'release_validated_on' => 'date',
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

    public function paymentAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_agent_id');
    }

    public function declarations(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Domain\Caisse\Models\TaxDeclaration::class, 'taxable');
    }

    public function auctionEntry(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AuctionGroupVehicle::class);
    }

    public function sale(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AuctionSale::class);
    }
}

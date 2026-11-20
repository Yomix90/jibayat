<?php

namespace App\Domain\Fourriere\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Véhicule d’un groupe d’enchères. Table Flask d’origine : fou_vehicules_enchere.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class AuctionGroupVehicle extends Model
{
    protected $table = 'auction_group_vehicles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function auctionGroup(): BelongsTo
    {
        return $this->belongsTo(AuctionGroup::class, 'auction_group_id');
    }

    public function impoundCase(): BelongsTo
    {
        return $this->belongsTo(ImpoundCase::class, 'impound_case_id');
    }
}

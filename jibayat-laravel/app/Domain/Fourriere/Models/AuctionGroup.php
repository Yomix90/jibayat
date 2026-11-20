<?php

namespace App\Domain\Fourriere\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Groupe d’enchères. Table Flask d’origine : fou_groupes_enchere.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class AuctionGroup extends Model
{
    protected $table = 'auction_groups';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'auction_on' => 'date',
            'opening_price' => 'decimal:2',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function vehicles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuctionGroupVehicle::class)->orderBy('sort_order');
    }

    public function sales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuctionSale::class);
    }
}

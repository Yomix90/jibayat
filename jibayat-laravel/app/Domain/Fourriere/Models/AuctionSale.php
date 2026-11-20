<?php

namespace App\Domain\Fourriere\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vente aux enchères. Table Flask d’origine : fou_ventes.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class AuctionSale extends Model
{
    protected $table = 'auction_sales';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hammer_price' => 'decimal:2',
            'sold_on' => 'date',
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

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}

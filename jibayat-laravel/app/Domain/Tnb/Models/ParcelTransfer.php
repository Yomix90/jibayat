<?php

namespace App\Domain\Tnb\Models;

use App\Models\Taxpayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transfert de propriété. Table Flask d’origine : transferts_terrain.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class ParcelTransfer extends Model
{
    protected $table = 'parcel_transfers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'transferred_on' => 'date',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class, 'parcel_id');
    }

    public function fromTaxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'from_taxpayer_id');
    }

    public function toTaxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'to_taxpayer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}

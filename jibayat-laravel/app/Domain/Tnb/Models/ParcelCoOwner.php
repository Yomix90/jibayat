<?php

namespace App\Domain\Tnb\Models;

use App\Models\Taxpayer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Copropriétaire (indivision). Table Flask d’origine : terrain_coproprietaires.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class ParcelCoOwner extends Model
{
    protected $table = 'parcel_co_owners';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'share_percent' => 'decimal:4',
            'joined_on' => 'date',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class, 'parcel_id');
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'taxpayer_id');
    }
}

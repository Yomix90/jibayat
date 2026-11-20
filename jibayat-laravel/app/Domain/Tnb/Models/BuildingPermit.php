<?php

namespace App\Domain\Tnb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permis (exonération TNB). Table Flask d’origine : permis.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class BuildingPermit extends Model
{
    protected $table = 'building_permits';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'filed_on' => 'date',
            'issued_on' => 'date',
            'authorized_on' => 'date',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class, 'parcel_id');
    }
}

<?php

namespace App\Domain\Tnb\Models;

use App\Models\Taxpayer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dossier TNB (un par redevable). Table Flask d’origine : dossiers_tnb.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class TnbFile extends Model
{
    protected $table = 'tnb_files';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'taxpayer_id');
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'taxpayer_id', 'taxpayer_id');
    }
}

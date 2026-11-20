<?php

namespace App\Domain\Tnb\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pièce jointe terrain. Table Flask d’origine : tnb_documents.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class ParcelDocument extends Model
{
    protected $table = 'parcel_documents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class, 'parcel_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

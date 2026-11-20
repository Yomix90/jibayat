<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pièce jointe contribuable. Table Flask d’origine : ctb_documents.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class TaxpayerDocument extends Model
{
    protected $table = 'taxpayer_documents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'taxpayer_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

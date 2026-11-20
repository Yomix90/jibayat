<?php

namespace App\Domain\Souk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pièce jointe affermage. Table Flask d’origine : affermage_docs.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class SoukContractDocument extends Model
{
    protected $table = 'souk_contract_documents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [

        ];
    }

    public function soukContract(): BelongsTo
    {
        return $this->belongsTo(SoukContract::class, 'souk_contract_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

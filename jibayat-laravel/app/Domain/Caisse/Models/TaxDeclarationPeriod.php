<?php

namespace App\Domain\Caisse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mois réglé par une déclaration. Table Flask d’origine : declarations.notes.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class TaxDeclarationPeriod extends Model
{
    protected $table = 'tax_declaration_periods';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function taxDeclaration(): BelongsTo
    {
        return $this->belongsTo(TaxDeclaration::class, 'tax_declaration_id');
    }
}

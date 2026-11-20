<?php

namespace App\Domain\Documents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de recette du relevé GIR. Table Flask d’origine : lignes_recettes.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class TreasuryStatementLine extends Model
{
    protected $table = 'treasury_statement_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function treasuryStatement(): BelongsTo
    {
        return $this->belongsTo(TreasuryStatement::class, 'treasury_statement_id');
    }
}

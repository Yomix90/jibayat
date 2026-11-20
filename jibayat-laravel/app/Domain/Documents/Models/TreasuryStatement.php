<?php

namespace App\Domain\Documents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Relevé GIR mensuel. Table Flask d’origine : bordereaux_versement.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class TreasuryStatement extends Model
{
    protected $table = 'treasury_statements';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'grand_total' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TreasuryStatementLine::class);
    }

    public function issueSlips(): HasMany
    {
        return $this->hasMany(IssueSlip::class);
    }
}

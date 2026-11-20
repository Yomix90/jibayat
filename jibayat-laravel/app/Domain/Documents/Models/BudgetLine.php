<?php

namespace App\Domain\Documents\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ligne budgétaire. Table Flask d’origine : regie_budget.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class BudgetLine extends Model
{
    protected $table = 'budget_lines';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'previous_actual' => 'decimal:2',
            'proposed' => 'decimal:2',
            'actual' => 'decimal:2',
        ];
    }
}

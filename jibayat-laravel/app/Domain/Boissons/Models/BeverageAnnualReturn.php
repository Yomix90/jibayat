<?php

namespace App\Domain\Boissons\Models;

use App\Models\Taxpayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Déclaration annuelle TDB. Table Flask d’origine : declarations_annuelles_tdb.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class BeverageAnnualReturn extends Model
{
    protected $table = 'beverage_annual_returns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base_q1' => 'decimal:2',
            'base_q2' => 'decimal:2',
            'base_q3' => 'decimal:2',
            'base_q4' => 'decimal:2',
            'total_base' => 'decimal:2',
            'rate' => 'decimal:4',
            'amount_due' => 'decimal:2',
            'declared_on' => 'date',
        ];
    }

    public function beverageOutlet(): BelongsTo
    {
        return $this->belongsTo(BeverageOutlet::class, 'beverage_outlet_id');
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'taxpayer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}

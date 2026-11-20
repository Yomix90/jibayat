<?php

namespace App\Domain\Documents\Models;

use App\Models\User;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bordereau d’émission. Table Flask d’origine : bordereaux_emission.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class IssueSlip extends Model
{
    use Cancellable;

    protected $table = 'issue_slips';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'current_amount' => 'decimal:2',
            'carried_forward' => 'decimal:2',
            'total' => 'decimal:2',
            'generated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function treasuryStatement(): BelongsTo
    {
        return $this->belongsTo(TreasuryStatement::class, 'treasury_statement_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}

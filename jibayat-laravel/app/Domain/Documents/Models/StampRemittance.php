<?php

namespace App\Domain\Documents\Models;

use App\Models\User;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bordereau de versement au percepteur. Table Flask d’origine : regie_bordereaux.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class StampRemittance extends Model
{
    use Cancellable;

    protected $table = 'stamp_remittances';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'remitted_on' => 'date',
            'total_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function books(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StampBook::class);
    }
}

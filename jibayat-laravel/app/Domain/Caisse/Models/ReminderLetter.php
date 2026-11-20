<?php

namespace App\Domain\Caisse\Models;

use App\Models\User;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lettre de relance. Table Flask d’origine : lettres_notification.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class ReminderLetter extends Model
{
    use Cancellable;

    protected $table = 'reminder_letters';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'generated_on' => 'date',
            'sent_on' => 'date',
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
}

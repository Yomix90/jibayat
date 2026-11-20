<?php

namespace App\Domain\Caisse\Models;

use App\Models\Municipality;
use App\Models\Taxpayer;
use App\Models\User;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avis de non-paiement. Table Flask d’origine : avis_non_paiement.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class NonPaymentNotice extends Model
{
    use Cancellable;

    protected $table = 'non_payment_notices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'issued_on' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function taxDeclaration(): BelongsTo
    {
        return $this->belongsTo(TaxDeclaration::class, 'tax_declaration_id');
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'taxpayer_id');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_id');
    }

    public function reminderLetter(): BelongsTo
    {
        return $this->belongsTo(ReminderLetter::class, 'reminder_letter_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}

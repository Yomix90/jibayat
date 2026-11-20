<?php

namespace App\Domain\Caisse\Models;

use App\Models\Taxpayer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de lettre de relance. Table Flask d’origine : lettres_details.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class ReminderLetterItem extends Model
{
    protected $table = 'reminder_letter_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
        ];
    }

    public function reminderLetter(): BelongsTo
    {
        return $this->belongsTo(ReminderLetter::class, 'reminder_letter_id');
    }

    public function nonPaymentNotice(): BelongsTo
    {
        return $this->belongsTo(NonPaymentNotice::class, 'non_payment_notice_id');
    }

    public function taxDeclaration(): BelongsTo
    {
        return $this->belongsTo(TaxDeclaration::class, 'tax_declaration_id');
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'taxpayer_id');
    }
}

<?php

namespace App\Domain\Caisse\Models;

use App\Models\Municipality;
use App\Models\Taxpayer;
use App\Models\User;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bulletin de versement / quittance. Table Flask d’origine : bulletins.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class PaymentSlip extends Model
{
    use Cancellable;

    protected $table = 'payment_slips';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'receipt_date' => 'date',
            'collected_on' => 'date',
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

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}

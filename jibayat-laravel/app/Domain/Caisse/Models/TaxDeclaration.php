<?php

namespace App\Domain\Caisse\Models;

use App\Models\Municipality;
use App\Models\Taxpayer;
use App\Models\User;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Déclaration / liquidation. Table Flask d’origine : declarations.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class TaxDeclaration extends Model
{
    use Cancellable;

    protected $table = 'tax_declarations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'principal' => 'decimal:2',
            'late_penalty' => 'decimal:2',
            'surcharge' => 'decimal:2',
            'non_filing_fine' => 'decimal:2',
            'total' => 'decimal:2',
            'declared_on' => 'date',
            'due_on' => 'date',
            'paid_on' => 'date',
            'cancelled_at' => 'datetime',
        ];
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

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Objet taxé : terrain, établissement, véhicule, occupation, dossier fourrière, bail ou contrat souk. */
    public function taxable(): MorphTo
    {
        return $this->morphTo();
    }

    public function periods(): HasMany
    {
        return $this->hasMany(TaxDeclarationPeriod::class);
    }

    public function paymentSlips(): HasMany
    {
        return $this->hasMany(PaymentSlip::class);
    }
}

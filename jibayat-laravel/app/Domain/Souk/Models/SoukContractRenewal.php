<?php

namespace App\Domain\Souk\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Prolongation d'un contrat d'affermage pour une période, avec sa redevance (augmentation choisie à la prolongation). */
class SoukContractRenewal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'monthly_fee' => 'decimal:2', 'increase_rate' => 'decimal:2'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SoukContract::class, 'souk_contract_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}

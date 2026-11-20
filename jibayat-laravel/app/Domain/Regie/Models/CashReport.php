<?php

namespace App\Domain\Regie\Models;

use App\Models\User;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Procès-verbal de la régie : arrêté de caisse, vérification, récolement, remise de service. */
class CashReport extends Model
{
    use Cancellable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['report_date' => 'date', 'data' => 'array', 'theoretical_balance' => 'decimal:2', 'counted_balance' => 'decimal:2', 'difference' => 'decimal:2', 'cancelled_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($q)
    {
        return $q->whereNull('cancelled_at');
    }
}

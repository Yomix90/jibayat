<?php

namespace App\Domain\Regie\Models;

use App\Domain\Documents\Models\Employee;
use App\Domain\Documents\Models\StampValue;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bon de décharge de tickets remis à un agent (souk…) ; soldé au retour (vendus = remis − rendus). */
class TicketDischarge extends Model
{
    use Cancellable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['discharge_date' => 'date', 'settled_on' => 'date', 'amount_paid' => 'decimal:2', 'corrections' => 'array', 'cancelled_at' => 'datetime'];
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(StampValue::class, 'stamp_value_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function cashEntry(): BelongsTo
    {
        return $this->belongsTo(CashEntry::class);
    }

    public function sold(): int
    {
        return max(0, (int) $this->quantity - (int) $this->returned);
    }

    public function scopeActive($q)
    {
        return $q->whereNull('cancelled_at');
    }
}

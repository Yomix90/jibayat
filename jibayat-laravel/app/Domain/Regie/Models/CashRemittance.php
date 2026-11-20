<?php

namespace App\Domain\Regie\Models;

use App\Models\User;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bordereau de versement au comptable de rattachement (sortie de caisse). */
class CashRemittance extends Model
{
    use Cancellable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['remitted_on' => 'date', 'period_from' => 'date', 'period_to' => 'date', 'acknowledged_on' => 'date', 'amount' => 'decimal:2', 'lines' => 'array', 'cancelled_at' => 'datetime'];
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

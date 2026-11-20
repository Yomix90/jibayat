<?php

namespace App\Domain\Regie\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Clôture mensuelle de la régie (verrou du mois). Une réouverture est motivée et conservée. */
class CashClosure extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'closed_at' => 'datetime', 'reopened_at' => 'datetime', 'opening_balance' => 'decimal:2', 'receipts' => 'decimal:2', 'remittances' => 'decimal:2', 'closing_balance' => 'decimal:2'];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeActive($q)
    {
        return $q->whereNull('reopened_at');
    }
}

<?php

namespace App\Domain\Regie\Models;

use App\Models\Rubric;
use App\Models\Taxpayer;
use App\Models\User;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Recette saisie à la régie (brouillon ➜ validée). Une fois validée : annulation motivée seulement. */
class CashEntry extends Model
{
    use Cancellable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'amount' => 'decimal:2', 'quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'validated_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function scopeActive($q)
    {
        return $q->whereNull('cancelled_at');
    }
}

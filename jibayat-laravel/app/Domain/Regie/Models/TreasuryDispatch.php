<?php

namespace App\Domain\Regie\Models;

use App\Models\User;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bordereau d'envoi des pièces au Trésor ; le receveur en accuse réception. */
class TreasuryDispatch extends Model
{
    use Cancellable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_on' => 'date', 'acknowledged_on' => 'date', 'items' => 'array', 'cancelled_at' => 'datetime'];
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

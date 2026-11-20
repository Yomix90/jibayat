<?php

namespace App\Domain\Regie\Models;

use App\Domain\Documents\Models\StampValue;
use App\Support\Concerns\Cancellable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mouvement de stock tickets / vignettes : dotation de la réserve TGR, retrait vers la régie, ajustements. */
class StockMovement extends Model
{
    use Cancellable;

    protected $table = 'stamp_stock_movements';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['moved_on' => 'date', 'cancelled_at' => 'datetime'];
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(StampValue::class, 'stamp_value_id');
    }

    public function scopeActive($q)
    {
        return $q->whereNull('cancelled_at');
    }
}

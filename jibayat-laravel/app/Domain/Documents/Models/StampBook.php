<?php

namespace App\Domain\Documents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Carnet de timbres. Table Flask d’origine : regie_carnets.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class StampBook extends Model
{
    protected $table = 'stamp_books';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'assigned_on' => 'date',
            'employee_paid_on' => 'date',
            'remitted_on' => 'date',
            'amount_paid' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function stampPack(): BelongsTo
    {
        return $this->belongsTo(StampPack::class, 'stamp_pack_id');
    }

    public function stampValue(): BelongsTo
    {
        return $this->belongsTo(StampValue::class, 'stamp_value_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function stampRemittance(): BelongsTo
    {
        return $this->belongsTo(StampRemittance::class, 'stamp_remittance_id');
    }
}

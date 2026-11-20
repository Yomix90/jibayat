<?php

namespace App\Models;

use App\Support\Concerns\Journaled;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarif d’un arrêté fiscal. Table Flask d’origine : tarifs.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class Tariff extends Model
{
    use Journaled;

    protected $table = 'tariffs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'surface_min' => 'decimal:2',
            'surface_max' => 'decimal:2',
        ];
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class, 'rubric_id');
    }

    public function taxOrder(): BelongsTo
    {
        return $this->belongsTo(TaxOrder::class, 'tax_order_id');
    }
}

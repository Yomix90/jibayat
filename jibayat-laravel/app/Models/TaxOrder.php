<?php

namespace App\Models;

use App\Support\Concerns\Journaled;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Arrêté fiscal. Table Flask d’origine : arretes_fiscaux.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class TaxOrder extends Model
{
    use Journaled;

    protected $table = 'tax_orders';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(Tariff::class);
    }
}

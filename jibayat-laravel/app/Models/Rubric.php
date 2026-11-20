<?php

namespace App\Models;

use App\Support\Concerns\Journaled;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rubrique fiscale (code budgétaire). Table Flask d’origine : rubriques.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class Rubric extends Model
{
    use Journaled;

    protected $table = 'rubrics';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_id');
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(Tariff::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contribuable / redevable. Table Flask d’origine : contribuables.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class Taxpayer extends Model
{
    protected $table = 'taxpayers';

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

    /** Nom affiché : « Nom Prénom » ou la raison sociale (Flask : format_nom_bilingue, partie FR). */
    public function displayName(): string
    {
        $name = trim(($this->last_name ?? '').' '.($this->first_name ?? ''));

        return $name !== '' ? $name : ($this->company_name ?? '—');
    }

    public function displayNameAr(): ?string
    {
        $name = trim(($this->last_name_ar ?? '').' '.($this->first_name_ar ?? ''));

        return $name !== '' ? $name : $this->company_name_ar;
    }
}

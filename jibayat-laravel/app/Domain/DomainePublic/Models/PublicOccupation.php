<?php

namespace App\Domain\DomainePublic\Models;

use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Occupation du domaine public. Table Flask d’origine : occupations.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class PublicOccupation extends Model
{
    protected $table = 'public_occupations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'renewal_contacted_at' => 'datetime',
            'area' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function taxpayer(): BelongsTo
    {
        return $this->belongsTo(Taxpayer::class, 'taxpayer_id');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_id');
    }
}

<?php

namespace App\Domain\Boissons\Models;

use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Caisse\Models\TaxDeclaration;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Débit de boissons. Table Flask d’origine : etablissements_boissons.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class BeverageOutlet extends Model
{
    protected $table = 'beverage_outlets';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'authorized_on' => 'date',
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

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tariff::class, 'tariff_id');
    }

    public function declarations(): MorphMany
    {
        return $this->morphMany(TaxDeclaration::class, 'taxable');
    }

    /** Libellé du type : tarif lié, sinon le texte importé de Flask. */
    public function typeLabel(): string
    {
        return $this->tariff?->label ?? (in_array($this->type, [null, '', 'None'], true) ? '—' : (string) $this->type);
    }
}

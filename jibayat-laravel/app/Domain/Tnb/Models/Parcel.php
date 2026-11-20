<?php

namespace App\Domain\Tnb\Models;

use App\Models\Municipality;
use App\Models\Taxpayer;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Caisse\Models\TaxDeclaration;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Terrain / parcelle. Table Flask d’origine : terrains.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class Parcel extends Model
{
    protected $table = 'parcels';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'acquired_on' => 'date',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
            'is_census' => 'boolean',
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

    public function permits(): HasMany
    {
        return $this->hasMany(BuildingPermit::class)->orderByDesc('authorized_on');
    }

    public function coOwners(): HasMany
    {
        return $this->hasMany(ParcelCoOwner::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(ParcelTransfer::class)->orderByDesc('transferred_on')->orderByDesc('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ParcelDocument::class)->latest();
    }

    public function declarations(): MorphMany
    {
        return $this->morphMany(TaxDeclaration::class, 'taxable');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(TnbFile::class, 'taxpayer_id', 'taxpayer_id');
    }

    /** « Lotissement X · lot 12 · TF 1234/56 » */
    public function locationLabel(): string
    {
        return collect([$this->subdivision, $this->parcel_number ? 'lot '.$this->parcel_number : null, $this->land_title ? 'TF '.$this->land_title : null, $this->address])
            ->filter()->implode(' · ') ?: '—';
    }
}

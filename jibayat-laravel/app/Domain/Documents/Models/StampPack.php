<?php

namespace App\Domain\Documents\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paquet de timbres. Table Flask d’origine : regie_paquets.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class StampPack extends Model
{
    protected $table = 'stamp_packs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
        ];
    }

    public function stampValue(): BelongsTo
    {
        return $this->belongsTo(StampValue::class, 'stamp_value_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function books(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StampBook::class);
    }
}

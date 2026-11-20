<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal des imports Excel. Table Flask d’origine : imports_historique.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class ImportLog extends Model
{
    protected $table = 'import_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}

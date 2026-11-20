<?php

namespace App\Models;

use App\Support\Concerns\Journaled;
use Illuminate\Database\Eloquent\Model;

/**
 * Commune. Table Flask d’origine : communes.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class Municipality extends Model
{
    use Journaled;

    protected $table = 'municipalities';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

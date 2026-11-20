<?php

namespace App\Models;

use App\Support\Concerns\Journaled;
use Illuminate\Database\Eloquent\Model;

/**
 * Module activable. Table Flask d’origine : app_modules.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class AppModule extends Model
{
    use Journaled;

    protected $table = 'app_modules';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

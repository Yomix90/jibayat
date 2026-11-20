<?php

namespace App\Domain\Documents\Models;

use App\Support\Concerns\Journaled;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Service communal. Table Flask d’origine : regie_services.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class Service extends Model
{
    use Journaled;

    protected $table = 'services';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}

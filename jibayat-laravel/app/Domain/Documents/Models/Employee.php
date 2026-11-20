<?php

namespace App\Domain\Documents\Models;

use App\Support\Concerns\Journaled;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Employé détenteur de carnets. Table Flask d’origine : regie_employes.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class Employee extends Model
{
    use Journaled;

    protected $table = 'employees';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}

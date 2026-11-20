<?php

namespace App\Domain\Documents\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuration annuelle des bordereaux d’émission. Table Flask d’origine : emission_config.
 * Généré en phase 3 (schéma + relations) ; la logique métier arrive en phase 5.
 */
class IssueConfig extends Model
{
    protected $table = 'issue_configs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'first_part_value' => 'decimal:2',
            'second_part_value' => 'decimal:2',
        ];
    }
}

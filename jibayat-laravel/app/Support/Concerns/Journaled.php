<?php

namespace App\Support\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Journalisation automatique des créations / modifications / suppressions
 * (Journal d'activité). Les colonnes listées dans $journalExcept ne sont jamais enregistrées.
 */
trait Journaled
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('parametres')
            ->logAll()
            ->logExcept(array_merge(['updated_at', 'created_at'], $this->journalExcept ?? []))
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => match ($event) {
                'created' => 'Création',
                'updated' => 'Modification',
                'deleted' => 'Suppression',
                default => ucfirst($event),
            });
    }
}

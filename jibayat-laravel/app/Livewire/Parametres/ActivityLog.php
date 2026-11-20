<?php

namespace App\Livewire\Parametres;

use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Journal d'activité — connexions, encaissements, annulations, modifications de paramètres
 * (spatie/laravel-activitylog ; Flask : user_logs importés sous le journal « flask »).
 */
#[Title("Journal d'activité")]
class ActivityLog extends SettingsPage
{
    public const ROUTE = 'parametres.journal';

    use WithPagination;

    #[Url(as: 'journal')]
    public string $log = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'du')]
    public string $from = '';

    #[Url(as: 'au')]
    public string $to = '';

    public const LOGS = [
        'auth' => 'Connexions',
        'parametres' => 'Paramètres',
        'pieces' => 'Pièces (annulations)',
        'flask' => 'Ancienne application',
        'default' => 'Autres',
    ];

    public function updating($name): void
    {
        if (in_array($name, ['log', 'search', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public static function subjectLabel(?string $type): string
    {
        return match (class_basename((string) $type)) {
            'Tariff' => 'Tarif', 'TaxOrder' => 'Arrêté fiscal', 'Rubric' => 'Rubrique', 'CalcParameter' => 'Paramètre',
            'User' => 'Utilisateur', 'Municipality' => 'Commune', 'Service' => 'Service', 'Employee' => 'Employé',
            'StampValue' => 'Valeur de timbre', 'AppModule' => 'Module', 'Setting' => 'Réglage', 'Role' => 'Rôle',
            'TaxDeclaration' => 'Déclaration', 'PaymentSlip' => 'Bulletin', '' => '', default => class_basename((string) $type),
        };
    }

    public function render()
    {
        $activities = Activity::with('causer')
            ->when($this->log, fn ($q) => $q->where('log_name', $this->log))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('description', 'like', "%{$this->search}%")
                ->orWhere('properties', 'like', "%{$this->search}%")))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->latest('id')
            ->paginate(30);

        return view('livewire.parametres.activity-log', ['activities' => $activities, 'logs' => self::LOGS]);
    }
}

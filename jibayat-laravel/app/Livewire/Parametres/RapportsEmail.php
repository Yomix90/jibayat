<?php

namespace App\Livewire\Parametres;

use App\Domain\Systeme\AlertService;
use App\Domain\Systeme\ReportService;
use App\Mail\RevenueReport;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Title;

/** Rapports par e-mail et alertes : choix par compte, aperçu des alertes du jour, envoi d'essai. */
#[Title('Rapports par e-mail')]
class RapportsEmail extends SettingsPage
{
    public const ROUTE = 'parametres.rapports-email';

    /** @var array<int, array{alerts: bool, report: string}> */
    public array $prefs = [];

    public function mount(): void
    {
        $this->prefs = User::where('is_active', true)->whereNotNull('email')->get()
            ->mapWithKeys(fn ($u) => [$u->id => ['alerts' => (bool) $u->email_alerts, 'report' => $u->email_report ?: 'aucun']])->all();
    }

    public function save(): void
    {
        $this->authorizeWrite();
        $this->validate(['prefs.*.report' => ['required', 'in:'.implode(',', array_keys(ReportService::FREQUENCIES))]]);
        foreach ($this->prefs as $id => $p) {
            User::whereKey($id)->update(['email_alerts' => (bool) $p['alerts'], 'email_report' => $p['report']]);
        }
        activity('systeme')->causedBy(auth()->user())->log('Préférences des rapports par e-mail modifiées');
        $this->notify('Préférences enregistrées.');
    }

    public function sendTest(ReportService $reports): void
    {
        $this->authorizeWrite();
        $me = auth()->user();
        if (! $me->email) {
            $this->notify('Ajoutez d’abord une adresse e-mail à votre compte.', 'warning');

            return;
        }
        [$from, $to] = ReportService::period('hebdomadaire');
        try {
            Mail::to($me->email)->send(new RevenueReport($reports->build($from, $to), 'Envoi d’essai'));
        } catch (\Throwable $e) {
            $this->notify('Envoi impossible : '.$e->getMessage(), 'error');

            return;
        }
        $this->notify("Rapport d’essai envoyé à {$me->email}.");
    }

    public function runAlerts(AlertService $alerts): void
    {
        $this->authorizeWrite();
        $this->notify($alerts->dispatch().' notification(s) envoyée(s).');
    }

    public function render(AlertService $alerts)
    {
        return view('livewire.parametres.rapports-email', [
            'users' => User::where('is_active', true)->whereNotNull('email')->orderBy('name')->get(),
            'withoutEmail' => User::where('is_active', true)->whereNull('email')->count(),
            'alerts' => $alerts->collect(), 'mailer' => config('mail.default'), 'from' => config('mail.from.address'),
        ]);
    }
}

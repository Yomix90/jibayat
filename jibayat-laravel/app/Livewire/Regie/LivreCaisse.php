<?php

namespace App\Livewire\Regie;

use App\Domain\Regie\Services\Ledger;
use App\Domain\Regie\Services\MonthLock;
use App\Domain\Regie\Services\RemittanceService;
use App\Livewire\AppPage;
use App\Livewire\Regie\Concerns\HasPeriod;
use App\Models\Setting;
use Livewire\Attributes\Title;

/**
 * Livre de caisse (Modèle 6) : report du mois précédent, recettes et versements jour par jour, solde.
 * Le panneau « Paramètres de la régie » fixe le solde de départ, le plafond d'encaisse et le comptable.
 */
#[Title('Livre de caisse')]
class LivreCaisse extends AppPage
{
    use HasPeriod;

    public const ROUTE = 'regie.livre-caisse';

    public bool $showSettings = false;

    public array $settings = [];

    public function openSettings(): void
    {
        $this->authorizeWrite('config.gerer');
        $o = Ledger::opening();
        $this->settings = ['solde_initial' => (string) $o['amount'], 'solde_initial_date' => $o['date'] === '2000-01-01' ? '' : $o['date'],
            'plafond' => (string) Setting::get('regie.plafond', 0), 'comptable' => RemittanceService::accountant(),
            'regisseur' => (string) Setting::get('regie.regisseur', ''), 'rib' => (string) Setting::get('regie.rib', '')];
        $this->resetValidation();
        $this->showSettings = true;
    }

    public function saveSettings(): void
    {
        $this->authorizeWrite('config.gerer');
        $this->validate(['settings.solde_initial' => ['required', 'numeric', 'min:0'], 'settings.solde_initial_date' => ['required', 'date'],
            'settings.plafond' => ['nullable', 'numeric', 'min:0'], 'settings.comptable' => ['required', 'string', 'max:150'],
            'settings.regisseur' => ['nullable', 'string', 'max:150'], 'settings.rib' => ['nullable', 'string', 'max:40']]);
        if (\App\Domain\Regie\Models\CashClosure::active()->exists()) {
            $this->addError('settings.solde_initial', __('Des mois sont déjà clôturés : rouvrez-les avant de changer le solde de départ.'));

            return;
        }
        foreach ($this->settings as $k => $v) {
            Setting::put("regie.$k", (string) $v);
        }
        activity('regie')->causedBy(auth()->user())->withProperties($this->settings)->log('Paramètres de la régie');
        $this->showSettings = false;
        $this->notify('Paramètres de la régie enregistrés.');
    }

    public function render(Ledger $ledger)
    {
        return view('livewire.regie.livre-caisse', [
            'book' => $ledger->cashBook($this->year, $this->month), 'opening' => Ledger::opening(),
            'closure' => MonthLock::closure($this->year, $this->month),
        ]);
    }
}

<?php

namespace App\Livewire\Fourriere;

use App\Domain\Fourriere\Models\ImpoundSetting;
use App\Domain\Fourriere\Services\ImpoundCalculator;
use App\Livewire\AppPage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;

/** Paramètres fourrière — Flask /fourriere/parametres : déposants, motifs, états des véhicules (les tarifs sont dans l'arrêté fiscal). */
#[Title('Paramètres — Fourrière')]
class Parametres extends AppPage
{
    public const ROUTE = 'fourriere.parametres';

    public const CATEGORIES = ['deposant' => 'Déposants', 'motif' => 'Motifs', 'etat_vehicule' => 'États des véhicules'];

    public array $newLabel = ['deposant' => '', 'motif' => '', 'etat_vehicule' => ''];

    public function add(string $category): void
    {
        $this->authorizeWrite('config.gerer');
        abort_unless(array_key_exists($category, self::CATEGORIES), 422);
        $this->validate(["newLabel.{$category}" => ['required', 'string', 'max:255',
            Rule::unique('impound_settings', 'label')->where('category', $category)]], [], ["newLabel.{$category}" => 'libellé']);
        $label = trim($this->newLabel[$category]);
        ImpoundSetting::create(['category' => $category, 'code' => Str::upper(Str::slug($label, '_')), 'label' => $label,
            'sort_order' => ImpoundSetting::where('category', $category)->max('sort_order') + 1, 'is_active' => true]);
        $this->newLabel[$category] = '';
        $this->notify('Paramètre ajouté.');
    }

    public function toggle(int $id): void
    {
        $this->authorizeWrite('config.gerer');
        $s = ImpoundSetting::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
    }

    public function render(ImpoundCalculator $calc)
    {
        return view('livewire.fourriere.parametres', [
            'settings' => ImpoundSetting::whereIn('category', array_keys(self::CATEGORIES))->orderBy('sort_order')->orderBy('label')->get()->groupBy('category'),
            'types' => $calc->vehicleTypes(), 'towing' => $calc->defaultTowingFee(),
        ]);
    }
}

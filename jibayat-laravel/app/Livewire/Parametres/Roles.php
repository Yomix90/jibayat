<?php

namespace App\Livewire\Parametres;

use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles & permissions — Flask : /roles/permissions (droits par module : voir, ajouter, modifier,
 * supprimer, exporter) + droits transversaux (bulletins, validation des paiements, configuration).
 * L'administrateur a toujours tous les droits.
 */
#[Title('Rôles & permissions')]
class Roles extends SettingsPage
{
    public const ROUTE = 'parametres.roles';

    #[Url(as: 'role')]
    public string $role = 'regisseur';

    /** @var array<string, bool> permission ➜ cochée */
    public array $granted = [];

    public function mount(): void
    {
        $this->load();
    }

    public function updatedRole(): void
    {
        $this->load();
    }

    protected function load(): void
    {
        $role = Role::findByName($this->role, 'web');
        $names = $role->permissions->pluck('name')->all();
        $this->granted = Permission::pluck('name')->mapWithKeys(fn ($n) => [str_replace('.', '__', $n) => in_array($n, $names, true)])->all();
    }

    public function save(): void
    {
        $this->authorizeWrite();
        abort_if($this->role === 'administrateur', 403);
        $names = collect($this->granted)->filter()->keys()->map(fn ($k) => str_replace('__', '.', $k))->all();
        $role = Role::findByName($this->role, 'web');
        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $role->syncPermissions($names);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity('parametres')->causedBy(auth()->user())->performedOn($role)
            ->withProperties(['avant' => $before, 'apres' => collect($names)->sort()->values()->all()])
            ->log('Modification des permissions du rôle '.config("jibayat.roles.{$this->role}", $this->role));
        $this->notify('Permissions enregistrées.');
    }

    public function render()
    {
        return view('livewire.parametres.roles', [
            'roles' => Role::withCount('users')->orderBy('id')->get(),
            'modules' => config('jibayat.permission_modules'),
            'actions' => config('jibayat.permission_actions'),
            'globals' => config('jibayat.global_permissions'),
        ]);
    }
}

<?php

namespace App\Livewire\Parametres;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Spatie\Permission\Models\Role;

/**
 * Comptes utilisateurs — Flask : /utilisateurs. Pas de suppression : un compte se désactive
 * (Flask fait de même). Un mot de passe fixé par l'administrateur doit être changé à la 1re connexion.
 */
#[Title('Utilisateurs')]
class Users extends SettingsPage
{
    public const ROUTE = 'parametres.utilisateurs';

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $username = '';

    public string $name = '';

    public string $name_ar = '';

    public string $email = '';

    public string $phone = '';

    public string $role = '';

    public string $password = '';

    public function create(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'username', 'name', 'name_ar', 'email', 'phone', 'role', 'password');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeWrite();
        $u = User::findOrFail($id);
        $this->editingId = $u->id;
        $this->username = $u->username;
        $this->name = $u->name;
        $this->name_ar = (string) $u->name_ar;
        $this->email = (string) $u->email;
        $this->phone = (string) $u->phone;
        $this->role = (string) $u->getRoleNames()->first();
        $this->password = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();
        $this->username = mb_strtolower(trim($this->username));
        $data = $this->validate([
            'username' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::exists('roles', 'name')],
            'password' => [$this->editingId ? 'nullable' : 'required', Password::defaults()],
        ], [
            'username.regex' => "L'identifiant ne peut contenir que des lettres minuscules, chiffres, points, tirets.",
        ], ['username' => 'identifiant', 'name' => 'nom', 'role' => 'rôle', 'password' => 'mot de passe']);

        if ($this->editingId === auth()->id() && $data['role'] !== 'administrateur' && auth()->user()->hasRole('administrateur')) {
            $this->addError('role', 'Vous ne pouvez pas retirer votre propre rôle d’administrateur.');

            return;
        }

        $attributes = collect($data)->except('role', 'password')->map(fn ($v) => $v === '' ? null : $v)->all();
        if ($data['password'] ?? null) {
            $attributes['password'] = Hash::make($data['password']);
            $attributes['must_change_password'] = true;
        }

        $user = $this->editingId ? tap(User::findOrFail($this->editingId))->update($attributes) : User::create($attributes + ['is_active' => true]);
        $user->syncRoles([$data['role']]);

        $this->notify($this->editingId ? 'Compte modifié.' : "Compte « {$user->username} » créé. Le mot de passe devra être changé à la 1re connexion.");
        $this->showForm = false;
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeWrite();
        if ($id === auth()->id()) {
            $this->notify('Vous ne pouvez pas désactiver votre propre compte.', 'error');

            return;
        }
        $u = User::findOrFail($id);
        $u->update(['is_active' => ! $u->is_active]);
        $this->notify($u->is_active ? 'Compte réactivé.' : 'Compte désactivé : la personne est déconnectée.');
    }

    public function render()
    {
        $users = User::with('roles')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")
                ->orWhere('username', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
            ->orderByDesc('is_active')->orderBy('name')->get();

        return view('livewire.parametres.users', [
            'users' => $users,
            'roles' => Role::orderBy('id')->get(),
        ]);
    }
}

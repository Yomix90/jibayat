<?php

namespace App\Models;

use App\Support\Concerns\Journaled;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['username', 'name', 'name_ar', 'email', 'email_alerts', 'email_report', 'locale', 'phone', 'password', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements WebAuthnAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Journaled, Notifiable, WebAuthnAuthentication;

    /** Jamais enregistrés dans le journal d'activité. */
    protected array $journalExcept = ['password', 'remember_token', 'last_login_at'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'email_alerts' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function recentPages(): HasMany
    {
        return $this->hasMany(RecentPage::class)->latest('visited_at');
    }

    /**
     * Libellé du rôle principal affiché dans le badge doré de l'en-tête.
     */
    public function roleLabel(): string
    {
        $role = $this->getRoleNames()->first();

        return __($role ? (config("jibayat.roles.$role") ?? ucfirst($role)) : 'Utilisateur');
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/u', trim($this->name)))
            ->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');
    }
}

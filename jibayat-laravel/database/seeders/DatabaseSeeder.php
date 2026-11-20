<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Rôles + un compte administrateur et un compte de démonstration par rôle.
     * Mot de passe : variable JIBAYAT_SEED_PASSWORD du fichier .env (changement exigé à la 1re connexion
     * sera activé en Phase 4 ; ces comptes servent à valider le visuel et les permissions).
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $password = env('JIBAYAT_SEED_PASSWORD') ?: 'changer-moi';

        $accounts = [
            ['admin', 'Administrateur', 'المدير', 'administrateur'],
            ['regisseur', 'Régisseur', 'وكيل المداخيل', 'regisseur'],
            ['suppleant', 'Régisseur suppléant', 'وكيل المداخيل المناوب', 'regisseur_suppleant'],
            ['collecteur', 'Collecteur', 'المحصل', 'collecteur'],
            ['controle', 'Contrôle', 'المراقبة', 'consultation'],
        ];

        foreach ($accounts as [$username, $name, $nameAr, $role]) {
            $user = User::firstOrCreate(
                ['username' => $username],
                ['name' => $name, 'name_ar' => $nameAr, 'password' => $password, 'is_active' => true],
            );
            $user->syncRoles([$role]);
        }
    }
}

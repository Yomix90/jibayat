<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles et permissions par défaut, repris des rôles Flask (database.py, roles_default) :
 *   super_admin/admin (tout)          ➜ administrateur (Gate::before : tous les droits)
 *   regisseur (voir, valider, bulletin) ➜ regisseur, regisseur_suppleant
 *   agent_assiette (voir, ajouter, modifier) ➜ collecteur
 *   consultant (voir)                 ➜ consultation
 * Les pages de configuration (Flask : peut_config) restent réservées à l'administrateur.
 * Rejouable : n'ajoute que ce qui manque ; les droits modifiés depuis l'écran Rôles sont conservés.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** Modules réservés à la configuration (Flask : peut_config). */
    private const CONFIG_MODULES = ['parametres', 'utilisateurs', 'systeme'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modules = array_keys(config('jibayat.permission_modules'));
        $actions = config('jibayat.permission_actions');

        $created = [];
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                if (Permission::findOrCreate("$module.$action", 'web')->wasRecentlyCreated) {
                    $created[] = "$module.$action";
                }
            }
        }
        foreach (array_keys(config('jibayat.global_permissions')) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $metier = array_values(array_diff($modules, self::CONFIG_MODULES));
        $perm = fn (array $mods, array $acts) => collect($mods)
            ->crossJoin($acts)->map(fn ($p) => "{$p[0]}.{$p[1]}")->all();

        // Régie des recettes et archives : le régisseur saisit, valide, clôture et établit les PV.
        $regieOwn = $perm(['recettes', 'archives'], ['voir', 'ajouter', 'modifier', 'exporter']);
        $regisseur = array_merge($perm($metier, ['voir']), $regieOwn, ['dashboard.voir', 'bulletins.creer', 'paiements.valider']);

        $rights = [
            'administrateur' => [],
            'regisseur' => $regisseur,
            'regisseur_suppleant' => $regisseur,
            'collecteur' => array_merge($perm($metier, ['voir', 'ajouter', 'modifier']), ['dashboard.voir']),
            // Lecture seule : pas d'accès à l'importation Excel (outil d'écriture).
            'consultation' => array_merge($perm(array_diff($metier, ['importation']), ['voir']), ['dashboard.voir']),
        ];

        // Droits par défaut appliqués à la création du rôle seulement : les réglages faits ensuite
        // dans Paramètres ➜ Rôles & permissions ne sont pas écrasés si le seeder est relancé.
        foreach ($rights as $role => $permissions) {
            $model = Role::findOrCreate($role, 'web');
            if ($model->wasRecentlyCreated || $model->permissions()->doesntExist()) {
                $model->syncPermissions($permissions);
            } elseif ($new = array_values(array_intersect($created, $permissions))) {
                // Permissions apparues avec une nouvelle version : accordées une fois aux rôles concernés.
                $model->givePermissionTo($new);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

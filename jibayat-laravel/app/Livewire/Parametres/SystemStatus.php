<?php

namespace App\Livewire\Parametres;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Title;
use Throwable;

/**
 * État du système : versions, base de données, volumes, dernier import Flask.
 */
#[Title('État du système')]
class SystemStatus extends SettingsPage
{
    public const ROUTE = 'parametres.etat';

    public function render()
    {
        $db = DB::connection();
        try {
            $dbVersion = $db->selectOne('select version() as v')->v;
            $dbSize = (float) $db->selectOne('select coalesce(sum(data_length + index_length), 0) as s from information_schema.tables where table_schema = ?', [$db->getDatabaseName()])->s;
        } catch (Throwable) {
            $dbVersion = $db->getDriverName();
            $dbSize = null;
        }

        $counts = collect([
            'users' => 'Utilisateurs', 'taxpayers' => 'Contribuables', 'parcels' => 'Terrains', 'leases' => 'Baux',
            'vehicles' => 'Véhicules', 'tax_declarations' => 'Déclarations', 'payment_slips' => 'Bulletins',
            'tariffs' => 'Tarifs', 'activity_log' => 'Lignes de journal',
        ])->map(fn ($label, $table) => ['label' => $label, 'count' => DB::table($table)->count()]);

        $reports = collect(File::glob(storage_path('app/import-reports/import-*.json')))->sort()->values();
        $lastImport = $reports->reject(fn ($f) => str_contains($f, 'simulation'))->last();
        $last = $lastImport ? json_decode(File::get($lastImport), true) : null;

        return view('livewire.parametres.system-status', [
            'info' => [
                'Application' => 'Jibayat '.config('jibayat.version'),
                'Laravel' => app()->version(),
                'PHP' => PHP_VERSION,
                'Base de données' => $db->getDriverName().' '.$dbVersion.' ('.$db->getDatabaseName().')',
                'Taille de la base' => $dbSize !== null ? number_format($dbSize / 1048576, 1, ',', ' ').' Mo' : '—',
                'Fuseau horaire' => config('app.timezone'),
                'Environnement' => app()->environment(),
                'Espace disque libre' => number_format(disk_free_space(base_path()) / 1073741824, 1, ',', ' ').' Go',
            ],
            'counts' => $counts,
            'lastImport' => $last,
        ]);
    }
}

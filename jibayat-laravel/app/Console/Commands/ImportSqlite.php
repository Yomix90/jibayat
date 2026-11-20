<?php

namespace App\Console\Commands;

use App\Domain\Import\SqliteImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * php artisan jibayat:import-sqlite {chemin} [--dry-run]
 *
 * Importe (ou ré-importe) une base Jibayat Flask exportée en clair. Rejouable sans doublons.
 * Affiche et enregistre un rapport : lignes source / insérées / mises à jour / rejetées / écarts,
 * avertissements et contrôles de sommes. Code de sortie 0 si aucun écart, 1 sinon.
 */
class ImportSqlite extends Command
{
    protected $signature = 'jibayat:import-sqlite
        {path : Chemin de la base SQLite en clair (ex. ..\\data\\jibayat_plain.db)}
        {--dry-run : Simule l’import puis annule tout (aucune écriture)}';

    protected $description = 'Importe la base Jibayat Flask (SQLite) dans MySQL, avec rapport de comptage et d’écarts';

    public function handle(): int
    {
        $path = $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');
        $this->info(($dryRun ? '[SIMULATION] ' : '')."Import de {$path}");

        try {
            $report = (new SqliteImporter($path))->run($dryRun, fn ($t) => $this->output->write("  · {$t}\r"));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Table Flask', 'Table Laravel', 'Source', 'Insérées', 'Mises à jour', 'Rejetées', 'Écart'],
            collect($report['tables'])->map(fn ($t) => [
                $t['source'].($t['missing'] ? ' (absente)' : ''), $t['target'],
                $t['source_count'], $t['inserted'], $t['updated'], $t['rejected'], $t['gap'],
            ])->all()
        );

        $sums = collect($report['tables'])->flatMap(fn ($t) => collect($t['sums'])->map(fn ($s) => [
            $t['source'].'.'.$s['column'], number_format($s['source'], 2, ',', ' '), number_format($s['target'], 2, ',', ' '),
            number_format($s['diff'], 2, ',', ' '), $s['ok'] ? 'OK' : 'ÉCART',
        ]));
        if ($sums->isNotEmpty()) {
            $this->line('Contrôle des sommes');
            $this->table(['Colonne', 'Source', 'Importé', 'Différence', ''], $sums->all());
        }

        foreach ($report['tables'] as $t) {
            foreach ($t['warnings'] as $w) {
                $this->warn("  ! {$t['source']} : {$w}");
            }
            foreach ($t['rejections'] as $r) {
                $this->error("  ✗ {$t['source']} : {$r}");
            }
        }
        foreach ($report['skipped'] as $s) {
            $this->line("  – {$s['source']} ({$s['rows']} lignes) non migrée : {$s['reason']}");
        }
        foreach ($report['unknown'] as $u) {
            $this->error("  ? table inconnue non migrée : {$u['source']} ({$u['rows']} lignes)");
        }

        $file = storage_path('app/import-reports/import-'.now()->format('Ymd-His').($dryRun ? '-simulation' : '').'.json');
        File::ensureDirectoryExists(dirname($file));
        File::put($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info("Rapport : {$file}");

        if ($report['ok']) {
            $this->info($dryRun ? 'Simulation réussie : aucun écart.' : 'Import terminé : aucun écart.');

            return self::SUCCESS;
        }
        $this->error('Import terminé AVEC écarts : voir le rapport.');

        return self::FAILURE;
    }
}

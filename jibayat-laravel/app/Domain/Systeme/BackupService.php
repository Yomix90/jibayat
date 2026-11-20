<?php

namespace App\Domain\Systeme;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Sauvegardes de la base MySQL (Flask : sauvegarde SQLite manuelle / automatique, copie vers un dossier externe, journal).
 * Moteur : mysqldump si disponible, sinon export SQL écrit en PHP. Fichiers gzip dans storage/app/private/backups.
 * La restauration est une opération destructrice : commande « php artisan jibayat:restore » (précédée d'une sauvegarde de précaution).
 */
class BackupService
{
    public const DIR = 'backups';

    public function settings(): array
    {
        return ['auto' => Setting::get('backup.auto', '1') === '1', 'keep' => (int) Setting::get('backup.keep', '30'), 'time' => Setting::get('backup.time', '02:00'),
            'copy_dir' => (string) Setting::get('backup.copy_dir', '')];
    }

    public function saveSettings(array $s, User $user): void
    {
        Setting::put('backup.auto', ! empty($s['auto']) ? '1' : '0');
        Setting::put('backup.keep', (string) max(1, (int) $s['keep']));
        Setting::put('backup.time', $s['time']);
        Setting::put('backup.copy_dir', trim((string) $s['copy_dir']));
        activity('systeme')->causedBy($user)->withProperties($s)->log('Réglages des sauvegardes modifiés');
    }

    /** @return list<array{name: string, size: int, date: \Carbon\CarbonImmutable}> */
    public function list(): array
    {
        return collect(Storage::disk('local')->files(self::DIR))->filter(fn ($f) => str_ends_with($f, '.sql.gz'))
            ->map(fn ($f) => ['name' => basename($f), 'size' => Storage::disk('local')->size($f),
                'date' => \Carbon\CarbonImmutable::createFromTimestamp(Storage::disk('local')->lastModified($f))->setTimezone(config('app.timezone'))])
            ->sortByDesc('name')->values()->all();
    }

    public function path(string $name): string
    {
        abort_unless(preg_match('/^jibayat_[0-9_-]+(_[a-z]+)?\.sql\.gz$/', $name) && Storage::disk('local')->exists(self::DIR.'/'.$name), 404);

        return Storage::disk('local')->path(self::DIR.'/'.$name);
    }

    /** Crée une sauvegarde ; renvoie [nom du fichier, messages]. */
    public function create(string $type, ?User $user = null): array
    {
        Storage::disk('local')->makeDirectory(self::DIR);
        $name = 'jibayat_'.now()->format('Y-m-d_His').'_'.$type.'.sql.gz';
        $target = Storage::disk('local')->path(self::DIR.'/'.$name);
        $sql = $this->dumpWithMysqldump() ?? $this->dumpWithPhp();
        file_put_contents($target, gzencode($sql, 6));
        $messages = ['Sauvegarde '.$name.' créée ('.number_format(filesize($target) / 1024, 0, ',', ' ').' Ko).'];

        $copy = $this->settings()['copy_dir'];
        if ($copy !== '') {
            try {
                File::ensureDirectoryExists($copy);
                File::copy($target, rtrim($copy, '\\/').DIRECTORY_SEPARATOR.$name);
                $messages[] = "Copie déposée dans $copy.";
            } catch (\Throwable $e) {
                $messages[] = "Copie vers $copy impossible : ".$e->getMessage();
            }
        }
        $pruned = $this->prune();
        if ($pruned) {
            $messages[] = "$pruned ancienne(s) sauvegarde(s) supprimée(s).";
        }
        activity('systeme')->causedBy($user)->withProperties(['fichier' => $name, 'type' => $type])->log('Sauvegarde de la base');

        return [$name, $messages];
    }

    /** Garde les N sauvegardes les plus récentes. */
    public function prune(): int
    {
        $old = array_slice($this->list(), $this->settings()['keep']);
        foreach ($old as $b) {
            Storage::disk('local')->delete(self::DIR.'/'.$b['name']);
        }

        return count($old);
    }

    public function delete(string $name, User $user): void
    {
        $this->path($name);
        Storage::disk('local')->delete(self::DIR.'/'.$name);
        activity('systeme')->causedBy($user)->withProperties(['fichier' => $name])->log('Sauvegarde supprimée');
    }

    protected function dumpWithMysqldump(): ?string
    {
        $bin = config('jibayat.backup.mysqldump');
        if (! $bin || config('database.default') !== 'mysql') {
            return null;
        }
        $c = config('database.connections.mysql');
        $p = new Process([$bin, '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'], '--single-transaction', '--routines',
            '--no-tablespaces', '--default-character-set=utf8mb4', $c['database']], null, ['MYSQL_PWD' => (string) $c['password']], null, 600);
        try {
            $p->run();
        } catch (\Throwable) {
            return null;
        }

        return $p->isSuccessful() && $p->getOutput() !== '' ? $p->getOutput() : null;
    }

    /** Export SQL portable (structure + données), utilisé quand mysqldump est absent. */
    public function dumpWithPhp(): string
    {
        $pdo = DB::connection()->getPdo();
        $out = "-- Jibayat : sauvegarde du ".now()->format('d/m/Y H:i')."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $row) {
            $table = array_values((array) $row)[0];
            $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `$table`"))[1];
            $out .= "DROP TABLE IF EXISTS `$table`;\n$create;\n";
            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use (&$out, $table, $pdo) {
                $values = $rows->map(fn ($r) => '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $r)).')')->implode(",\n");
                $cols = '`'.implode('`,`', array_keys((array) $rows->first())).'`';
                $out .= "INSERT INTO `$table` ($cols) VALUES\n$values;\n";
            });
            $out .= "\n";
        }

        return $out."SET FOREIGN_KEY_CHECKS=1;\n";
    }

    /** Restauration (commande artisan) : sauvegarde de précaution puis exécution du fichier SQL. */
    public function restore(string $name): string
    {
        $path = $this->path($name);
        [$precaution] = $this->create('precaution');
        DB::unprepared(gzdecode(file_get_contents($path)));

        return $precaution;
    }
}

<?php

namespace App\Console\Commands;

use App\Domain\Systeme\BackupService;
use Illuminate\Console\Command;

/** Sauvegarde de la base (planifiée chaque jour par le scheduler si la sauvegarde automatique est active). */
class Backup extends Command
{
    protected $signature = 'jibayat:backup {--type=manuel : manuel | auto} {--if-enabled : ne rien faire si la sauvegarde automatique est désactivée}';

    protected $description = 'Sauvegarde la base MySQL de Jibayat (fichier .sql.gz dans storage/app/private/backups)';

    public function handle(BackupService $service): int
    {
        if ($this->option('if-enabled') && ! $service->settings()['auto']) {
            $this->info('Sauvegarde automatique désactivée.');

            return self::SUCCESS;
        }
        [, $messages] = $service->create($this->option('type') === 'auto' ? 'auto' : 'manuel');
        foreach ($messages as $m) {
            $this->info($m);
        }

        return self::SUCCESS;
    }
}

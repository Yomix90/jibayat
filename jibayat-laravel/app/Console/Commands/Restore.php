<?php

namespace App\Console\Commands;

use App\Domain\Systeme\BackupService;
use Illuminate\Console\Command;

/** Restauration d'une sauvegarde : opération destructrice, précédée d'une sauvegarde de précaution. */
class Restore extends Command
{
    protected $signature = 'jibayat:restore {file : nom du fichier, ex. jibayat_2026-11-20_020000_auto.sql.gz} {--force : ne pas demander de confirmation}';

    protected $description = 'Remplace toute la base par le contenu d’une sauvegarde (une sauvegarde de précaution est faite avant)';

    public function handle(BackupService $service): int
    {
        $file = (string) $this->argument('file');
        if (! $this->option('force') && ! $this->confirm("Toutes les données actuelles seront remplacées par celles de $file. Continuer ?")) {
            return self::FAILURE;
        }
        $precaution = $service->restore($file);
        $this->info("Base restaurée depuis $file. Sauvegarde de précaution : $precaution");
        activity('systeme')->withProperties(['fichier' => $file, 'precaution' => $precaution])->log('Base restaurée depuis une sauvegarde');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Domain\Systeme\AlertService;
use App\Domain\Systeme\ReportService;
use Illuminate\Console\Command;

/** Alertes du jour (cloche + e-mail) et rapports de recettes par e-mail. */
class SendAlerts extends Command
{
    protected $signature = 'jibayat:alerts {--reports= : quotidien | hebdomadaire | mensuel (envoie le rapport au lieu des alertes)}';

    protected $description = 'Envoie les alertes du jour ou un rapport de recettes par e-mail';

    public function handle(AlertService $alerts, ReportService $reports): int
    {
        if ($f = $this->option('reports')) {
            if (! in_array($f, ['quotidien', 'hebdomadaire', 'mensuel'], true)) {
                $this->error('Fréquence inconnue.');

                return self::FAILURE;
            }
            $this->info($reports->send($f).' rapport(s) envoyé(s).');

            return self::SUCCESS;
        }
        $this->info($alerts->dispatch().' notification(s) créée(s).');

        return self::SUCCESS;
    }
}

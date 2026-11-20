<?php

namespace App\Domain\Systeme;

use App\Models\User;
use App\Notifications\AlertNotification;
use Illuminate\Support\Facades\DB;

/**
 * Alertes de l'application (notifications dans la cloche, et e-mail pour les comptes qui l'ont demandé) :
 * bulletins en attente du régisseur, véhicules aliénables en fourrière, autorisations et baux qui expirent,
 * sauvegarde trop ancienne. Chaque alerte n'est envoyée qu'une fois par jour et par utilisateur.
 */
class AlertService
{
    /** @return list<array{key: string, level: string, title: string, message: string, url: string, permission: string}> */
    public function collect(): array
    {
        $alerts = [];
        $today = today();
        $pending = DB::table('payment_slips')->where('status', 'en_attente')->whereNull('cancelled_at')->where('created_at', '<', $today->copy()->subDays(2))
            ->selectRaw('COUNT(*) n, COALESCE(SUM(amount),0) s')->first();
        if ($pending->n) {
            $alerts[] = ['key' => 'bulletins_en_attente', 'level' => 'warning', 'permission' => 'paiements.valider', 'url' => route('caisse.bulletins'),
                'title' => "{$pending->n} bulletin(s) en attente depuis plus de 2 jours",
                'message' => number_format((float) $pending->s, 2, ',', ' ').' DH à valider ou rejeter à la caisse.'];
        }
        $impound = DB::table('impound_cases')->where('status', 'en_fourriere')->where('impounded_on', '<=', $today->copy()->subDays(365))->count();
        if ($impound) {
            $alerts[] = ['key' => 'fourriere_alienables', 'level' => 'info', 'permission' => 'fourriere.voir', 'url' => route('fourriere.encheres'),
                'title' => "$impound véhicule(s) en fourrière depuis plus d'un an", 'message' => 'Ils peuvent être proposés à la vente aux enchères.'];
        }
        $soon = $today->copy()->addDays(30)->toDateString();
        // Domaine public : autorisations expirées ou échues dans les 30 jours, titulaire non contacté depuis 15 jours.
        $odp = DB::table('public_occupations')->where('is_active', true)->whereIn('status', ['actif', 'expire'])
            ->whereNotNull('ends_on')->where('ends_on', '<=', $soon)
            ->where(fn ($q) => $q->whereNull('renewal_contacted_at')->orWhere('renewal_contacted_at', '<', $today->copy()->subDays(15)))->count();
        if ($odp) {
            $alerts[] = ['key' => 'odp_expiration', 'level' => 'warning', 'permission' => 'domaine_public.voir', 'url' => route('domaine-public.renouveler'),
                'title' => "$odp autorisation(s) d'occupation expirée(s) ou à échéance : titulaire à contacter",
                'message' => 'Contactez le titulaire pour le renouvellement, puis notez le contact.'];
        }
        // Souk : contrats dont la période se termine dans les 60 jours (prolongation possible avec augmentation).
        $souk = DB::table('souk_contracts')->where('is_active', true)->where('status', 'actif')->whereNotNull('ends_on')
            ->whereBetween('ends_on', [$today->toDateString(), $today->copy()->addDays(60)->toDateString()])->count();
        if ($souk) {
            $alerts[] = ['key' => 'souk_fin_periode', 'level' => 'info', 'permission' => 'souk.voir', 'url' => route('souk.contrats'),
                'title' => "$souk contrat(s) de souk arrivent en fin de période", 'message' => 'Proposez la prolongation au locataire (avec augmentation).'];
        }
        // Débits de boissons : arrêts d'activité dont la date de reprise prévue est dépassée.
        $resume = DB::table('beverage_outlets as o')->join('assessment_declarations as d', fn ($j) => $j->on('d.declarable_id', '=', 'o.id')
            ->where('d.declarable_type', 'beverage_outlet')->where('d.type', 'cessation')->whereNull('d.cancelled_at'))
            ->where('o.status', 'cesse')->whereNotNull('d.resume_on')->where('d.resume_on', '<', $today->toDateString())
            ->whereNotExists(fn ($q) => $q->from('assessment_declarations as r')->whereColumn('r.declarable_id', 'o.id')->where('r.declarable_type', 'beverage_outlet')
                ->where('r.type', 'reprise')->whereNull('r.cancelled_at')->whereColumn('r.event_on', '>=', 'd.event_on'))
            ->distinct()->count('o.id');
        if ($resume) {
            $alerts[] = ['key' => 'tdb_reprise', 'level' => 'warning', 'permission' => 'boissons.voir', 'url' => route('boissons.cessation'),
                'title' => "$resume débit(s) de boissons : date de reprise dépassée", 'message' => 'Vérifiez si l’activité a repris et enregistrez la déclaration de reprise.'];
        }
        $leases = DB::table('leases')->where('is_active', true)->where('status', 'actif')->whereBetween('ends_on', [$today->toDateString(), $soon])->count();
        if ($leases) {
            $alerts[] = ['key' => 'baux_expiration', 'level' => 'info', 'permission' => 'locations.voir', 'url' => route('locations.locataires'),
                'title' => "$leases bail(aux) arrivent à échéance dans les 30 jours", 'message' => 'Vérifiez les renouvellements.'];
        }
        $last = collect(app(BackupService::class)->list())->first();
        if (! $last || $last['date']->lt(now()->subDays(2))) {
            $alerts[] = ['key' => 'sauvegarde_ancienne', 'level' => 'error', 'permission' => 'config.gerer', 'url' => route('parametres.sauvegardes'),
                'title' => $last ? 'Dernière sauvegarde le '.$last['date']->format('d/m/Y') : 'Aucune sauvegarde de la base',
                'message' => 'Lancez une sauvegarde et vérifiez la tâche planifiée.'];
        }

        return $alerts;
    }

    /** Envoie les alertes du jour ; renvoie le nombre de notifications créées. */
    public function dispatch(): int
    {
        $alerts = $this->collect();
        $n = 0;
        foreach (User::where('is_active', true)->get() as $user) {
            foreach ($alerts as $a) {
                if (! $user->can($a['permission'])) {
                    continue;
                }
                $already = $user->notifications()->where('created_at', '>=', today())->get()->contains(fn ($x) => ($x->data['key'] ?? null) === $a['key']);
                if (! $already) {
                    $user->notify(new AlertNotification($a));
                    $n++;
                }
            }
        }

        return $n;
    }
}

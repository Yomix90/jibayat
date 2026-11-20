<?php

namespace App\Domain\Systeme;

use App\Domain\Documents\Services\RegisterService;
use App\Mail\RevenueReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Rapports de recettes par e-mail (envois automatiques) : encaissements de la période par taxe, déclarations émises,
 * bulletins en attente et reste à recouvrer, envoyés aux comptes qui ont choisi la fréquence correspondante.
 */
class ReportService
{
    public const FREQUENCIES = ['aucun' => 'Aucun', 'quotidien' => 'Chaque jour (veille)', 'hebdomadaire' => 'Chaque lundi (semaine écoulée)', 'mensuel' => 'Le 1er du mois (mois écoulé)'];

    /** Période couverte : veille, semaine ou mois précédent. */
    public static function period(string $frequency, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return match ($frequency) {
            'quotidien' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'hebdomadaire' => [$now->subWeek()->startOfWeek(), $now->subWeek()->endOfWeek()],
            'mensuel' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
        };
    }

    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $paid = DB::table('payment_slips as s')->join('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->whereIn('s.status', ['paye', 'valide', 'encaisse'])->whereNull('s.cancelled_at')
            ->whereBetween('s.paid_on', [$from->toDateString(), $to->toDateString()])
            ->groupBy('d.module')->selectRaw('d.module, COUNT(*) n, COALESCE(SUM(s.amount),0) total')->get()
            ->map(fn ($r) => ['label' => RegisterService::MODULES[$r->module]['fr'] ?? $r->module, 'n' => (int) $r->n, 'total' => (float) $r->total]);
        $declared = DB::table('tax_declarations')->whereNull('cancelled_at')->where('status', '!=', 'annule')
            ->whereBetween('declared_on', [$from->toDateString(), $to->toDateString()])->selectRaw('COUNT(*) n, COALESCE(SUM(total),0) s')->first();
        $pending = DB::table('payment_slips')->where('status', 'en_attente')->whereNull('cancelled_at')->selectRaw('COUNT(*) n, COALESCE(SUM(amount),0) s')->first();
        $remaining = (float) DB::table('tax_declarations as d')->whereNotIn('d.status', ['paye', 'annule'])->whereNull('d.cancelled_at')
            ->whereNotExists(fn ($q) => $q->from('payment_slips as s')->whereColumn('s.tax_declaration_id', 'd.id')->whereIn('s.status', ['paye', 'valide', 'encaisse'])->whereNull('s.cancelled_at'))
            ->sum('d.total');

        return ['from' => $from, 'to' => $to, 'paid' => $paid->values()->all(), 'paid_total' => $paid->sum('total'), 'paid_n' => $paid->sum('n'),
            'declared_n' => (int) $declared->n, 'declared_total' => (float) $declared->s, 'pending_n' => (int) $pending->n, 'pending_total' => (float) $pending->s,
            'remaining' => $remaining];
    }

    /** Envoie le rapport de la fréquence donnée ; renvoie le nombre de destinataires. */
    public function send(string $frequency, ?CarbonImmutable $now = null): int
    {
        $users = User::where('is_active', true)->where('email_report', $frequency)->whereNotNull('email')->get()->filter(fn ($u) => $u->can('registres.voir'));
        if ($users->isEmpty()) {
            return 0;
        }
        [$from, $to] = self::period($frequency, $now);
        $report = $this->build($from, $to);
        foreach ($users as $u) {
            Mail::to($u->email)->send(new RevenueReport($report, self::FREQUENCIES[$frequency]));
        }
        activity('systeme')->withProperties(['frequence' => $frequency, 'destinataires' => $users->count()])->log('Rapport de recettes envoyé par e-mail');

        return $users->count();
    }
}

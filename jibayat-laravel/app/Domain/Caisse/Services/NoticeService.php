<?php

namespace App\Domain\Caisse\Services;

use App\Domain\Caisse\Models\NonPaymentNotice;
use App\Domain\Caisse\Models\ReminderLetter;
use App\Domain\Caisse\Models\ReminderLetterItem;
use App\Domain\Caisse\Models\TaxDeclaration;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Avis de non-paiement et lettres de relance — portage de Flask modules/avis.py.
 * Impayé = déclaration ni payée ni annulée, de montant positif. Un avis par déclaration et par lot ; une lettre regroupe des avis.
 */
class NoticeService
{
    public const LABELS = [
        'TNB' => 'Taxe Terrains Non Bâtis', 'DEBITS_BOISSONS' => 'Débits de Boissons', 'STATIONNEMENT' => 'Stationnement TPV',
        'FOURRIERE' => 'Fourrière Municipale', 'OCCUPATION_DOMAINE' => 'Occupation du Domaine Public',
        'LOCATION_LOCAUX' => 'Location des Locaux', 'AFFERMAGE_SOUKS' => 'Affermage des Souks',
    ];

    public const LETTER_TYPES = ['relance' => 'Lettre de relance', 'mise_en_demeure' => 'Mise en demeure', 'notification' => 'Notification'];

    public function __construct(protected NumberGenerator $numbers) {}

    protected function unpaidDeclarations()
    {
        return DB::table('tax_declarations as d')->whereNotIn('d.status', ['paye', 'annule'])->whereNull('d.cancelled_at')->where('d.total', '>', 0);
    }

    /** Flask _get_impaye_par_module : impayés avec redevable et dernier avis de la déclaration. */
    public function rows(?string $module = null): Collection
    {
        $lastNotice = DB::table('non_payment_notices')->whereNull('cancelled_at')->groupBy('tax_declaration_id')->selectRaw('tax_declaration_id, MAX(id) id');

        return $this->unpaidDeclarations()->join('taxpayers as c', 'c.id', '=', 'd.taxpayer_id')
            ->leftJoinSub($lastNotice, 'ln', 'ln.tax_declaration_id', '=', 'd.id')
            ->leftJoin('non_payment_notices as a', 'a.id', '=', 'ln.id')
            ->when($module, fn ($q) => $q->where('d.module', $module))
            ->orderBy('d.module')->orderBy('c.last_name')->orderByDesc('d.year')->orderBy('d.id')
            ->get(['d.id as decl_id', 'd.module', 'd.year', 'd.quarter', 'd.total', 'd.principal', 'd.late_penalty', 'd.status', 'd.due_on', 'd.number as decl_number',
                'c.id as taxpayer_id', 'c.last_name', 'c.first_name', 'c.last_name_ar', 'c.first_name_ar', 'c.company_name', 'c.cin', 'c.ice', 'c.phone', 'c.address',
                'c.number as taxpayer_number', 'a.id as notice_id', 'a.number as notice_number', 'a.issued_on as notice_date', 'a.status as notice_status',
                'a.batch_id', 'a.reminder_letter_id']);
    }

    /** Flask avis() : impayés regroupés par taxe, avec filtres. */
    public function overview(string $module = '', string $status = '', string $year = ''): array
    {
        $rows = $this->rows($module ?: null)
            ->when($status !== '', fn ($c) => $c->filter(fn ($r) => (string) $r->notice_status === $status))
            ->when($year !== '', fn ($c) => $c->filter(fn ($r) => (string) $r->year === $year));
        $groups = $rows->groupBy('module')->sortKeys()->map(fn ($g, $m) => [
            'module' => $m, 'label' => self::LABELS[$m] ?? $m, 'rows' => $g->values(), 'total' => round($g->sum(fn ($r) => (float) $r->total), 2),
            'nb_redevables' => $g->pluck('taxpayer_id')->unique()->count(), 'nb_avis_emis' => $g->whereNotNull('notice_id')->count(),
        ])->values();

        return ['groups' => $groups, 'total' => $groups->sum('total'),
            'years' => DB::table('tax_declarations')->distinct()->orderByDesc('year')->pluck('year')->all()];
    }

    /** Flask _get_stats_recouvrement (par taxe) : toutes les déclarations, y compris annulées (« total_du » = total émis). */
    public function stats(): Collection
    {
        return DB::table('tax_declarations')->groupBy('module')
            ->selectRaw("module, COUNT(*) nb, COUNT(DISTINCT taxpayer_id) nb_redevables, COALESCE(SUM(total),0) total_du,
                SUM(CASE WHEN status='emis' THEN 1 ELSE 0 END) nb_emis, COALESCE(SUM(CASE WHEN status='paye' THEN total ELSE 0 END),0) total_recouvre")
            ->orderByDesc('total_du')->get();
    }

    protected function batch(): string
    {
        return 'LOT'.now()->format('YmdHis');
    }

    protected function createNotice(object $d, string $batch): NonPaymentNotice
    {
        return NonPaymentNotice::create(['number' => $this->numbers->next('non_payment_notices', 'number', 'AVS'), 'tax_declaration_id' => $d->id,
            'taxpayer_id' => $d->taxpayer_id, 'municipality_id' => $d->municipality_id, 'amount_due' => $d->total, 'issued_on' => today(), 'batch_id' => $batch, 'status' => 'emis']);
    }

    /** Flask generer_avis : un avis pour chaque impayé qui n'a pas encore d'avis émis. */
    public function generate(?string $module, User $user): array
    {
        $batch = $this->batch();
        $n = DB::transaction(function () use ($module, $batch) {
            $decls = $this->unpaidDeclarations()->when($module, fn ($q) => $q->where('d.module', $module))
                ->whereNotExists(fn ($q) => $q->from('non_payment_notices as a')->whereColumn('a.tax_declaration_id', 'd.id')->where('a.status', 'emis')->whereNull('a.cancelled_at'))
                ->orderBy('d.id')->lockForUpdate()->get(['d.id', 'd.taxpayer_id', 'd.municipality_id', 'd.total']);
            foreach ($decls as $d) {
                $this->createNotice($d, $batch);
            }

            return $decls->count();
        });
        activity('avis')->causedBy($user)->withProperties(['module' => $module, 'lot' => $batch, 'avis' => $n])->log("$n avis de non-paiement générés");

        return [$n, $batch];
    }

    /** Flask generer_avis_individuel. */
    public function generateOne(int $declarationId, User $user): NonPaymentNotice
    {
        $d = TaxDeclaration::findOrFail($declarationId);
        if (NonPaymentNotice::where('tax_declaration_id', $d->id)->where('status', 'emis')->whereNull('cancelled_at')->exists()) {
            throw ValidationException::withMessages(['notice' => 'Cette déclaration a déjà un avis émis.']);
        }
        $notice = DB::transaction(fn () => $this->createNotice((object) $d->only('id', 'taxpayer_id', 'municipality_id', 'total'), $this->batch()));
        activity('avis')->causedBy($user)->performedOn($notice)->log("Avis {$notice->number} émis");

        return $notice;
    }

    /** Flask generer_lettre : lettre en brouillon regroupant les avis choisis (ou tous les avis émis de la taxe). */
    public function createLetter(?string $module, string $type, array $noticeIds, User $user): ReminderLetter
    {
        $letter = DB::transaction(function () use ($module, $type, $noticeIds, $user) {
            $notices = NonPaymentNotice::query()->whereNull('cancelled_at')
                ->when($noticeIds, fn ($q) => $q->whereIn('id', $noticeIds), fn ($q) => $q->where('status', 'emis')
                    ->when($module, fn ($w) => $w->whereHas('taxDeclaration', fn ($x) => $x->where('module', $module))))
                ->lockForUpdate()->get();
            if ($notices->isEmpty()) {
                throw ValidationException::withMessages(['letter' => 'Aucun avis à inclure dans la lettre.']);
            }
            $letter = ReminderLetter::create(['number' => $this->numbers->next('reminder_letters', 'number', 'LTR'), 'batch_id' => $this->batch(), 'module' => $module ?: null,
                'type' => $type, 'status' => 'brouillon', 'generated_on' => today(), 'agent_id' => $user->id,
                'taxpayer_count' => $notices->count(), 'total_amount' => round($notices->sum(fn ($n) => (float) $n->amount_due), 2)]);
            foreach ($notices as $n) {
                ReminderLetterItem::create(['reminder_letter_id' => $letter->id, 'non_payment_notice_id' => $n->id, 'tax_declaration_id' => $n->tax_declaration_id,
                    'taxpayer_id' => $n->taxpayer_id, 'amount_due' => $n->amount_due]);
                $n->update(['reminder_letter_id' => $letter->id]);
            }

            return $letter;
        });
        activity('avis')->causedBy($user)->performedOn($letter)->withProperties(['avis' => $letter->taxpayer_count])->log("Lettre {$letter->number} créée en brouillon");

        return $letter;
    }

    /** Flask lettre_approuver : brouillon ➜ envoyée. */
    public function approve(ReminderLetter $letter, User $user): void
    {
        if ($letter->status !== 'brouillon') {
            throw ValidationException::withMessages(['letter' => 'Seule une lettre en brouillon peut être marquée envoyée.']);
        }
        $letter->update(['status' => 'envoyee', 'sent_on' => today()]);
        activity('avis')->causedBy($user)->performedOn($letter)->log("Lettre {$letter->number} marquée envoyée");
    }

    /** Flask lettre_annuler : annulation motivée ; les avis redeviennent disponibles. */
    public function cancelLetter(ReminderLetter $letter, User $user, string $reason): void
    {
        DB::transaction(function () use ($letter, $user, $reason) {
            $letter->cancel($user, $reason);
            $letter->forceFill(['status' => 'annulee'])->save();
            NonPaymentNotice::where('reminder_letter_id', $letter->id)->update(['reminder_letter_id' => null]);
        });
    }

    /** Lignes d'impression (Flask lettre_imprimer). */
    public function letterLines(ReminderLetter $letter): Collection
    {
        return DB::table('reminder_letter_items as ld')->join('taxpayers as c', 'c.id', '=', 'ld.taxpayer_id')
            ->join('tax_declarations as d', 'd.id', '=', 'ld.tax_declaration_id')->leftJoin('non_payment_notices as a', 'a.id', '=', 'ld.non_payment_notice_id')
            ->where('ld.reminder_letter_id', $letter->id)->orderBy('c.last_name')->orderBy('d.year')
            ->get(['ld.amount_due', 'c.id as taxpayer_id', 'c.number as taxpayer_number', 'c.last_name', 'c.first_name', 'c.last_name_ar', 'c.first_name_ar', 'c.company_name',
                'c.company_name_ar', 'c.cin', 'c.address', 'c.address_ar', 'c.phone', 'd.year', 'd.quarter', 'd.module', 'd.number as decl_number', 'd.total', 'd.principal',
                'd.late_penalty', 'a.number as notice_number', 'a.issued_on']);
    }
}

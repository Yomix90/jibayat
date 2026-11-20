<?php

namespace App\Domain\Declarations\Services;

use App\Domain\Boissons\Models\BeverageOutlet;
use App\Domain\Caisse\Services\NumberGenerator;
use App\Domain\Declarations\Models\AssessmentDeclaration;
use App\Domain\Tnb\Models\Parcel;
use App\Domain\Transport\Models\Vehicle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Déclarations relatives à l'assiette — loi n° 47-06 relative à la fiscalité des collectivités territoriales,
 * modifiée par la loi n° 07-20 (en vigueur depuis le 1er janvier 2021) :
 *   TNB : art. 47 (déclaration annuelle des terrains, avant le 1er mars), art. 48 (changement de propriétaire,
 *         d'affectation ou cession, 45 jours) ;
 *   Débits de boissons : art. 67-I (déclaration d'existence, 30 jours), art. 67-II (déclaration annuelle des recettes,
 *         avant le 1er avril), art. 68 (cession, cessation, transfert, changement de forme juridique, 45 jours),
 *         art. 69 (inactivité partielle ou totale, avant le 31 janvier de l'année suivante) ;
 *   Transport public de voyageurs : art. 87 (déclaration d'existence au début de l'activité, déclaration d'arrêt).
 * Sanctions : art. 134 (défaut ou retard : majoration de 15 %, minimum 500 DH) et art. 135 (art. 48, 68, 87 : 15 %).
 * Règle de la commune (validée le 20/11/2026) : la période de cessation d'un débit de boissons est exonérée ;
 * le redevable signe la déclaration d'arrêt d'activité en indiquant le motif et la date de reprise prévue.
 */
class DeclarationService
{
    /** Taxe ➜ [libellé, objet (alias de morph), modèle]. */
    public const MODULES = [
        'TNB' => ['fr' => 'Taxe sur les terrains urbains non bâtis', 'ar' => 'الرسم على الأراضي الحضرية غير المبنية', 'object' => 'parcel', 'model' => Parcel::class, 'perm' => 'tnb'],
        'DEBITS_BOISSONS' => ['fr' => 'Taxe sur les débits de boissons', 'ar' => 'الرسم على محال بيع المشروبات', 'object' => 'beverage_outlet', 'model' => BeverageOutlet::class, 'perm' => 'boissons'],
        'STATIONNEMENT' => ['fr' => 'Taxe sur le transport public de voyageurs', 'ar' => 'الرسم على النقل العمومي للمسافرين', 'object' => 'vehicle', 'model' => Vehicle::class, 'perm' => 'transport'],
    ];

    /**
     * Types de déclaration : article, libellés FR/AR, délai (days = jours après le fait ; annual = date limite de l'année),
     * champs demandés et effet sur l'objet taxable.
     */
    public const TYPES = [
        'TNB' => [
            'annuelle_terrains' => ['art' => '47', 'fr' => 'Déclaration annuelle des terrains', 'ar' => 'الإقرار السنوي بالأراضي', 'annual' => '03-01', 'same_year' => true,
                'fields' => ['fiscal_year', 'amount'], 'amount_label' => 'Superficie déclarée (m²)', 'hint' => 'À déposer avant le 1er mars de chaque année, que le terrain soit imposable ou exonéré.'],
            'changement' => ['art' => '48', 'fr' => 'Déclaration de changement de propriétaire, d’affectation ou de cession', 'ar' => 'الإقرار بتغيير المالك أو بتخصيص الأرض أو تفويتها', 'days' => 45,
                'fields' => ['event_on', 'reason', 'new_holder'], 'hint' => 'Dans les 45 jours qui suivent le changement.'],
        ],
        'DEBITS_BOISSONS' => [
            'creation' => ['art' => '67-I', 'fr' => 'Déclaration d’existence (création de l’établissement)', 'ar' => 'التصريح بتأسيس المؤسسة', 'days' => 30,
                'fields' => ['event_on'], 'hint' => 'Dans les 30 jours qui suivent le début de l’activité.'],
            'recettes_annuelles' => ['art' => '67-II', 'fr' => 'Déclaration annuelle des recettes', 'ar' => 'الإقرار بالمداخيل السنوية', 'annual' => '04-01', 'same_year' => false,
                'fields' => ['fiscal_year', 'amount'], 'amount_label' => 'Recettes de l’année (DH, hors TVA)', 'hint' => 'Recettes de l’année écoulée, avant le 1er avril.'],
            'cessation' => ['art' => '68', 'fr' => 'Déclaration d’arrêt d’activité', 'ar' => 'الإقرار بإيقاف النشاط', 'days' => 45, 'effect' => 'cessation',
                'fields' => ['event_on', 'reason', 'resume_on'], 'hint' => 'Dans les 45 jours. Le redevable signe la déclaration et indique le motif et la date de reprise prévue ; la période d’arrêt est exonérée.'],
            'reprise' => ['art' => '—', 'fr' => 'Déclaration de reprise d’activité', 'ar' => 'الإقرار باستئناف النشاط', 'effect' => 'reprise',
                'fields' => ['event_on'], 'hint' => 'Met fin à la période d’arrêt : les trimestres redeviennent dus à partir du trimestre de reprise.'],
            'cession' => ['art' => '68', 'fr' => 'Déclaration de cession de l’activité', 'ar' => 'الإقرار بتفويت النشاط', 'days' => 45,
                'fields' => ['event_on', 'new_holder', 'reason'], 'hint' => 'Dans les 45 jours qui suivent la cession.'],
            'transfert' => ['art' => '68', 'fr' => 'Déclaration de transfert de l’établissement', 'ar' => 'الإقرار بنقل المؤسسة', 'days' => 45,
                'fields' => ['event_on', 'new_address', 'reason'], 'hint' => 'Dans les 45 jours qui suivent le transfert.'],
            'forme_juridique' => ['art' => '68', 'fr' => 'Déclaration de changement de forme juridique', 'ar' => 'الإقرار بتغيير الشكل القانوني للمؤسسة', 'days' => 45,
                'fields' => ['event_on', 'reason'], 'hint' => 'Dans les 45 jours qui suivent le changement.'],
            'inactivite' => ['art' => '69', 'fr' => 'Déclaration d’inactivité (partielle ou totale)', 'ar' => 'الإقرار بالعطالة', 'annual' => '02-01', 'same_year' => false,
                'fields' => ['fiscal_year', 'inactivity', 'reason', 'inactive_part'], 'hint' => 'Avant le 31 janvier de l’année suivante : situation de l’établissement, motifs et partie inactive.'],
        ],
        'STATIONNEMENT' => [
            'creation' => ['art' => '87', 'fr' => 'Déclaration d’existence (début d’activité)', 'ar' => 'التصريح بالتأسيس', 'days' => 0,
                'fields' => ['event_on'], 'hint' => 'Au début de l’activité.'],
            'cessation' => ['art' => '87', 'fr' => 'Déclaration d’arrêt d’activité (cession du véhicule, changement d’activité ou de forme juridique)', 'ar' => 'الإقرار بتوقيف النشاط', 'days' => 0, 'effect' => 'retrait',
                'fields' => ['event_on', 'reason', 'new_holder'], 'hint' => 'Lors de la cession du véhicule, du changement de nature de l’activité ou de forme juridique.'],
        ],
    ];

    public const CESSATION_REASONS = ['Travaux / rénovation', 'Raisons de santé', 'Difficultés économiques', 'Fermeture administrative', 'Saison (activité saisonnière)', 'Décès de l’exploitant', 'Autre'];

    public function __construct(protected NumberGenerator $numbers) {}

    public static function type(string $module, string $type): array
    {
        return self::TYPES[$module][$type] ?? throw ValidationException::withMessages(['type' => 'Type de déclaration inconnu.']);
    }

    /** Date limite légale. */
    public static function deadline(array $t, ?string $eventOn, ?int $fiscalYear): ?CarbonImmutable
    {
        if (isset($t['annual']) && $fiscalYear) {
            $year = $t['same_year'] ? $fiscalYear : $fiscalYear + 1;

            return CarbonImmutable::parse($year.'-'.$t['annual'])->subDay();   // « avant le 1er … » ➜ veille incluse
        }
        if (isset($t['days']) && $eventOn) {
            return CarbonImmutable::parse($eventOn)->addDays($t['days']);
        }

        return null;
    }

    public function object(string $module, int $id): Model
    {
        return self::MODULES[$module]['model']::findOrFail($id);
    }

    /** Enregistre une déclaration et applique son effet (arrêt / reprise d'activité, retrait du véhicule). */
    public function file(string $module, string $type, int $objectId, array $data, User $user): AssessmentDeclaration
    {
        $t = self::type($module, $type);
        $object = $this->object($module, $objectId);
        $filed = CarbonImmutable::parse($data['filed_on'] ?? today()->toDateString());
        $deadline = self::deadline($t, $data['event_on'] ?? null, isset($data['fiscal_year']) ? (int) $data['fiscal_year'] : null);

        $d = DB::transaction(function () use ($module, $type, $t, $object, $data, $filed, $deadline, $user) {
            if (($t['effect'] ?? null) === 'reprise' && ! $this->openCessation($module, $object)) {
                throw ValidationException::withMessages(['type' => 'Cet établissement n’a pas d’arrêt d’activité en cours.']);
            }
            if (($t['effect'] ?? null) === 'cessation' && $this->openCessation($module, $object)) {
                throw ValidationException::withMessages(['type' => 'Un arrêt d’activité est déjà en cours : déclarez d’abord la reprise.']);
            }
            $d = AssessmentDeclaration::create([
                'number' => $this->numbers->next('assessment_declarations', 'number', 'DAS'),
                'module' => $module, 'type' => $type, 'declarable_type' => self::MODULES[$module]['object'], 'declarable_id' => $object->id,
                'taxpayer_id' => $object->taxpayer_id, 'fiscal_year' => $data['fiscal_year'] ?? null, 'event_on' => $data['event_on'] ?? null,
                'filed_on' => $filed->toDateString(), 'deadline' => $deadline?->toDateString(), 'is_late' => $deadline && $filed->greaterThan($deadline),
                'resume_on' => $data['resume_on'] ?? null, 'reason' => $data['reason'] ?? null, 'amount' => $data['amount'] ?? null,
                'details' => array_filter(['new_holder' => $data['new_holder'] ?? null, 'new_address' => $data['new_address'] ?? null,
                    'inactivity' => $data['inactivity'] ?? null, 'inactive_part' => $data['inactive_part'] ?? null, 'notes' => $data['notes'] ?? null]),
                'signed' => (bool) ($data['signed'] ?? false), 'agent_id' => $user->id,
            ]);
            match ($t['effect'] ?? null) {
                'cessation' => $object->update(['status' => 'cesse']),
                'reprise' => $object->update(['status' => 'actif']),
                'retrait' => $object->update(['status' => 'retire']),
                default => null,
            };

            return $d;
        });
        activity('declarations')->causedBy($user)->performedOn($d)->withProperties(['type' => $type, 'objet' => $object->number ?? $object->id, 'hors_delai' => $d->is_late])
            ->log("{$t['fr']} enregistrée ({$d->number})");

        return $d;
    }

    /** Annulation motivée ; si c'était le dernier arrêt ou la dernière reprise, le statut de l'établissement est rétabli. */
    public function cancel(AssessmentDeclaration $d, User $user, string $reason): void
    {
        DB::transaction(function () use ($d, $user, $reason) {
            $d->cancel($user, $reason);
            $effect = self::TYPES[$d->module][$d->type]['effect'] ?? null;
            if (in_array($effect, ['cessation', 'reprise'], true) && $d->declarable) {
                $d->declarable->update(['status' => $this->openCessation($d->module, $d->declarable) ? 'cesse' : 'actif']);
            }
        });
    }

    /** Arrêt d'activité en cours (dernière cessation sans reprise postérieure). */
    public function openCessation(string $module, Model $object): ?AssessmentDeclaration
    {
        $last = AssessmentDeclaration::active()->where('module', $module)->where('declarable_type', self::MODULES[$module]['object'])
            ->where('declarable_id', $object->id)->whereIn('type', ['cessation', 'reprise'])->orderByDesc('event_on')->orderByDesc('id')->first();

        return $last && $last->type === 'cessation' ? $last : null;
    }

    /**
     * Périodes d'arrêt d'un débit de boissons : [date d'arrêt, date de reprise ou null].
     *
     * @return list<array{0: string, 1: ?string}>
     */
    public static function cessationPeriods(int $outletId): array
    {
        $rows = AssessmentDeclaration::active()->where('module', 'DEBITS_BOISSONS')->where('declarable_type', 'beverage_outlet')
            ->where('declarable_id', $outletId)->whereIn('type', ['cessation', 'reprise'])->whereNotNull('event_on')
            ->orderBy('event_on')->orderBy('id')->get(['type', 'event_on']);
        $out = [];
        $open = null;
        foreach ($rows as $r) {
            if ($r->type === 'cessation' && $open === null) {
                $open = $r->event_on->toDateString();
            } elseif ($r->type === 'reprise' && $open !== null) {
                $out[] = [$open, $r->event_on->toDateString()];
                $open = null;
            }
        }
        if ($open !== null) {
            $out[] = [$open, null];
        }

        return $out;
    }

    /**
     * Trimestre exonéré : entièrement compris dans une période d'arrêt (le trimestre de l'arrêt et celui de la reprise restent dus).
     */
    public static function isQuarterExempt(array $periods, int $year, int $quarter): bool
    {
        $start = CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1);
        $end = $start->addMonths(3)->subDay();
        foreach ($periods as [$from, $to]) {
            if ($start->toDateString() >= $from && ($to === null || $end->toDateString() < CarbonImmutable::parse($to)->startOfQuarter()->toDateString())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Déclaration annuelle déposée dans le délai (TNB art. 47 avant le 1er mars ; débits de boissons
     * art. 67-II avant le 1er avril de l'année suivante). Règle de youssef (20/11/2026) : sans elle,
     * majoration de 15 % du droit dû, minimum 500 DH.
     */
    public static function timelyAnnual(string $module, int $objectId, int $fiscalYear): bool
    {
        $type = ['TNB' => 'annuelle_terrains', 'DEBITS_BOISSONS' => 'recettes_annuelles'][$module] ?? null;
        if (! $type) {
            return false;
        }

        return AssessmentDeclaration::active()->where('module', $module)->where('type', $type)
            ->where('declarable_type', self::MODULES[$module]['object'])->where('declarable_id', $objectId)
            ->where('fiscal_year', $fiscalYear)->where('is_late', false)->exists();
    }

    /** Liste filtrée (registre). */
    public function query(?string $module, ?string $type, string $status = '', string $search = '')
    {
        return AssessmentDeclaration::with(['taxpayer', 'agent'])
            ->when($module, fn ($q) => $q->where('module', $module))->when($type, fn ($q) => $q->where('type', $type))
            ->when($status === 'late', fn ($q) => $q->where('is_late', true)->whereNull('cancelled_at'))
            ->when($status === 'cancelled', fn ($q) => $q->whereNotNull('cancelled_at'))
            ->when($status === 'unsigned', fn ($q) => $q->where('signed', false)->whereNull('cancelled_at'))
            ->when(($s = trim($search)) !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('reason', 'like', "%$s%")
                ->orWhereHas('taxpayer', fn ($t) => $t->where('last_name', 'like', "%$s%")->orWhere('company_name', 'like', "%$s%")->orWhere('cin', 'like', "%$s%"))))
            ->orderByDesc('filed_on')->orderByDesc('id');
    }

    /**
     * Déclarations annuelles manquantes de l'année : TNB (art. 47) et recettes des débits de boissons (art. 67-II).
     *
     * @return array{tnb: Collection, boissons: Collection, tnb_deadline: string, boissons_deadline: string}
     */
    public function missingAnnual(int $year): array
    {
        $filed = fn (string $module, string $type, int $fy) => AssessmentDeclaration::active()->where('module', $module)->where('type', $type)
            ->where('fiscal_year', $fy)->pluck('declarable_id')->all();
        $tnb = Parcel::with('taxpayer')->where('is_active', true)->where('is_archived', false)->where('is_census', false)
            ->whereNotIn('id', $filed('TNB', 'annuelle_terrains', $year))->orderBy('number')->get();
        $outlets = BeverageOutlet::with('taxpayer')->where('is_active', true)->where('status', 'actif')
            ->whereNotIn('id', $filed('DEBITS_BOISSONS', 'recettes_annuelles', $year - 1))->orderBy('name')->get();

        return ['tnb' => $tnb, 'boissons' => $outlets,
            'tnb_deadline' => self::deadline(self::TYPES['TNB']['annuelle_terrains'], null, $year)->toDateString(),
            'boissons_deadline' => self::deadline(self::TYPES['DEBITS_BOISSONS']['recettes_annuelles'], null, $year - 1)->toDateString()];
    }

    /** Libellé de l'objet taxable. */
    public static function objectLabel(?Model $o): string
    {
        return match (true) {
            $o instanceof Parcel => 'Terrain n° '.$o->number.($o->land_title ? ' · TF '.$o->land_title : ''),
            $o instanceof BeverageOutlet => $o->name.' (n° '.$o->number.')',
            $o instanceof Vehicle => 'Véhicule '.$o->registration.' (n° '.$o->number.')',
            default => '—',
        };
    }
}

<?php

namespace App\Livewire\Tnb;

use App\Domain\Caisse\Models\TaxDeclaration;
use App\Domain\Tnb\Models\BuildingPermit;
use App\Domain\Tnb\Models\Parcel;
use App\Domain\Tnb\Models\TnbFile;
use App\Domain\Tnb\Services\TnbCalculator;
use App\Livewire\AppPage;
use App\Livewire\Tnb\Concerns\EditsParcels;
use App\Support\PyMath;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Dossiers & terrains TNB — Flask /tnb (un dossier par redevable, indicateurs, filtres, alertes de fin d'exonération).
 */
#[Title('Dossiers & terrains TNB')]
class Terrains extends AppPage
{
    use EditsParcels;

    public const ROUTE = 'tnb.terrains';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'statut')]
    public string $situation = '';

    #[Url]
    public string $zone = '';

    #[Url]
    public string $lotissement = '';

    /**
     * Données de la liste (Flask _get_tnb_dossiers_data) : dossiers non archivés, terrains actifs avec leur situation,
     * indicateurs calculés sur tous les dossiers puis filtres appliqués. Tri : dossiers avec impayés d'abord.
     */
    public static function dossiers(TnbCalculator $calc, string $q = '', string $statut = '', string $zone = '', string $lotissement = ''): array
    {
        $parcels = Parcel::where('is_active', true)->where('is_archived', false)->where('is_census', false)->orderBy('id')->get()
            ->map(function (Parcel $p) use ($calc) {
                $s = $calc->situation($p);
                $years = array_merge(array_column($s['not_declared'], 'annee'), array_column($s['pending'], 'annee'));

                return ['parcel' => $p, 'nb_non_declares' => count($s['not_declared']), 'nb_emises' => count($s['pending']),
                    'nb_non_paye' => count($years), 'total_not_declared' => $s['total_not_declared'],
                    'total_pending' => $s['total_pending'], 'total_due' => $s['total_due'], 'period' => TnbCalculator::periodLabel($years)];
            })->groupBy(fn ($r) => $r['parcel']->taxpayer_id);

        $kpi = ['files' => 0, 'parcels' => 0, 'unpaid' => 0, 'pending' => 0, 'up_to_date' => 0, 'amount_due' => 0.0, 'amount_pending' => 0.0];
        $rows = [];
        $needle = mb_strtolower($q);
        foreach (TnbFile::with('taxpayer')->where('is_archived', false)->whereHas('taxpayer', fn ($t) => $t->where('is_active', true))->orderBy('number')->get() as $file) {
            $list = $parcels->get($file->taxpayer_id, collect());
            $due = PyMath::ceilCents($list->sum('total_due'));
            $pending = PyMath::ceilCents($list->sum('total_pending'));
            $nbNotDeclared = $list->where('nb_non_declares', '>', 0)->count();
            $nbPending = $list->where('nb_emises', '>', 0)->count();
            $nbUnpaid = $list->where('nb_non_paye', '>', 0)->count();

            $kpi['files']++;
            $kpi['parcels'] += $list->count();
            $kpi['amount_due'] += $due;
            $kpi['amount_pending'] += $pending;
            $nbNotDeclared ? $kpi['unpaid']++ : ($nbPending ? $kpi['pending']++ : $kpi['up_to_date']++);

            if (($statut === 'impaye' && ! $nbNotDeclared) || ($statut === 'en_attente' && ! $nbPending) || ($statut === 'ajour' && $nbUnpaid)) {
                continue;
            }
            if ($zone !== '' && ! $list->contains(fn ($r) => $r['parcel']->zone === $zone)) {
                continue;
            }
            if ($lotissement !== '' && ! $list->contains(fn ($r) => mb_strtolower((string) $r['parcel']->subdivision) === mb_strtolower($lotissement))) {
                continue;
            }
            if ($needle !== '') {
                $t = $file->taxpayer;
                $bloc = mb_strtolower(implode(' ', [$t->last_name, $t->first_name, $t->last_name_ar, $t->first_name_ar, $t->company_name, $t->cin, $t->rc, $file->number,
                    ...$list->map(fn ($r) => implode(' ', [$r['parcel']->number, $r['parcel']->land_title, $r['parcel']->parcel_number, $r['parcel']->subdivision, $r['parcel']->address]))]));
                if (! str_contains($bloc, $needle)) {
                    continue;
                }
            }
            $rows[] = ['file' => $file, 'taxpayer' => $file->taxpayer, 'parcels' => $list->values()->all(), 'total_due' => $due,
                'total_pending' => $pending, 'nb_unpaid' => $nbUnpaid, 'nb_not_declared' => $nbNotDeclared, 'nb_pending' => $nbPending];
        }
        usort($rows, fn ($a, $b) => [$a['nb_unpaid'] ? 0 : 1, $a['file']->number] <=> [$b['nb_unpaid'] ? 0 : 1, $b['file']->number]);
        $all = $parcels->flatten(1);

        return [
            'dossiers' => $rows, 'kpi' => $kpi,
            'zones' => $all->pluck('parcel.zone')->filter()->unique()->sort()->values(),
            'subdivisions' => $all->pluck('parcel.subdivision')->filter()->unique()->sort()->values(),
        ];
    }

    /** Permis de construire dont l'exonération se termine dans moins de 60 jours (Flask _check_permis_alerts). */
    public static function exemptionAlerts(): array
    {
        $today = today();

        return BuildingPermit::with('parcel.taxpayer')->where('type', 'construction')->whereNotNull('exemption_end_year')
            ->whereHas('parcel', fn ($p) => $p->where('is_active', true)->where('is_archived', false))->get()
            ->map(fn ($b) => ['permit' => $b, 'days' => (int) $today->diffInDays(today()->setDate($b->exemption_end_year, 12, 31), false)])
            ->filter(fn ($a) => $a['days'] >= 0 && $a['days'] <= 60)->values()->all();
    }

    /** Flask tnb_supprimer : refusé si une année est payée ; sinon terrain désactivé (les pièces restent). */
    public function deleteParcel(int $id): void
    {
        $this->authorizeWrite('tnb.supprimer');
        $parcel = Parcel::findOrFail($id);
        $paid = TaxDeclaration::where('module', 'TNB')->where('taxable_type', 'parcel')->where('taxable_id', $id)->where('status', 'paye')->count();
        if ($paid) {
            $this->notify("Impossible : {$paid} déclaration(s) payée(s) sur ce terrain. Archivez-le depuis sa fiche.", 'error');

            return;
        }
        $parcel->update(['is_active' => false]);
        activity('parametres')->causedBy(auth()->user())->performedOn($parcel)->log("Suppression (désactivation) du terrain {$parcel->number}");
        $this->notify("Terrain n° {$parcel->number} supprimé de la liste.");
    }

    public function render(TnbCalculator $calc)
    {
        return view('livewire.tnb.terrains', self::dossiers($calc, $this->search, $this->situation, $this->zone, $this->lotissement) + [
            'alerts' => self::exemptionAlerts(),
            'taxpayers' => $this->showForm ? $this->taxpayerOptions() : collect(),
            'statuses' => self::parcelStatuses(),
        ]);
    }
}

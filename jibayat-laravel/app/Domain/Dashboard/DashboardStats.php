<?php

namespace App\Domain\Dashboard;

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques du tableau de bord — mêmes calculs que Flask (app.py::index) :
 * émis = Σ déclarations non annulées ; recouvré = Σ bulletins payés ; reste = max(0, émis − recouvré) ;
 * taux = recouvré / émis × 100 (1 décimale) ; par module, sur l'année en cours et au total ;
 * courbe mensuelle (émis par date de déclaration, encaissé par date de paiement du bulletin).
 */
class DashboardStats
{
    /**
     * Modules affichés : code Flask ➜ libellé, espace (couleur), permission, table et filtre des dossiers actifs,
     * page de liste.
     */
    public const MODULES = [
        'TNB' => ['label' => 'TNB — Terrains', 'space' => 'tnb', 'perm' => 'tnb', 'table' => 'parcels', 'where' => ['is_active' => true, 'is_archived' => false], 'route' => 'tnb.terrains'],
        'DEBITS_BOISSONS' => ['label' => 'Débits de boissons', 'space' => 'boissons', 'perm' => 'boissons', 'table' => 'beverage_outlets', 'where' => ['is_active' => true], 'route' => 'boissons.etablissements'],
        'STATIONNEMENT' => ['label' => 'Stationnement & TPV', 'space' => 'transport', 'perm' => 'transport', 'table' => 'vehicles', 'where' => ['is_active' => true], 'route' => 'transport.vehicules'],
        'OCCUPATION_DOMAINE' => ['label' => 'Domaine public', 'space' => 'domaine-public', 'perm' => 'domaine_public', 'table' => 'public_occupations', 'where' => ['is_active' => true], 'route' => 'domaine-public.occupations'],
        'LOCATION_LOCAUX' => ['label' => 'Location locaux & boutiques', 'space' => 'locations', 'perm' => 'locations', 'table' => 'leases', 'where' => ['is_active' => true], 'route' => 'locations.locataires'],
        'AFFERMAGE_SOUKS' => ['label' => 'Affermage des souks', 'space' => 'souk', 'perm' => 'souk', 'table' => 'souk_contracts', 'where' => ['is_active' => true], 'route' => 'souk.contrats'],
        'FOURRIERE' => ['label' => 'Fourrière municipale', 'space' => 'fourriere', 'perm' => 'fourriere', 'table' => 'impound_cases', 'where' => ['is_active' => true], 'route' => 'fourriere.dossiers'],
    ];

    public const MONTHS = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

    public function __construct(protected ?int $year = null)
    {
        $this->year ??= (int) now()->year;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function totals(): array
    {
        $emitted = (float) $this->declarations()->sum('total');
        $paid = (float) DB::table('payment_slips')->where('status', 'paye')->sum('amount');

        return [
            'emitted' => round($emitted, 2),
            'paid' => round($paid, 2),
            'remaining' => max(0.0, round($emitted - $paid, 2)),
            'rate' => self::rate($paid, $emitted),
            'declarations' => $this->declarations()->count(),
            'pending_count' => DB::table('payment_slips')->where('status', 'en_attente')->count(),
            'pending_amount' => round((float) DB::table('payment_slips')->where('status', 'en_attente')->sum('amount'), 2),
            'notices' => DB::table('non_payment_notices')->where('status', 'emis')->count(),
            'taxpayers' => DB::table('taxpayers')->where('is_active', true)->count(),
            'files' => collect(self::MODULES)->sum(fn ($m) => $this->activeFiles($m)),
        ];
    }

    /** Modules visibles par l'utilisateur (module actif + permission « voir »). */
    public function modules(?User $user): array
    {
        $result = [];
        foreach (self::MODULES as $code => $m) {
            if (! Navigation::isModuleActive($m['route']) || ! $user?->can($m['perm'].'.voir')) {
                continue;
            }
            $emitted = (float) $this->declarations()->where('module', $code)->sum('total');
            $paid = (float) $this->paidSlips()->where('d.module', $code)->sum('s.amount');
            $result[$code] = $m + [
                'code' => $code,
                'colors' => config("navigation.{$m['space']}.colors"),
                'icon' => config("navigation.{$m['space']}.icon"),
                'count' => $this->activeFiles($m),
                'declarations' => $this->declarations()->where('module', $code)->count(),
                'emitted_year' => round((float) $this->declarations()->where('module', $code)->where('year', $this->year)->sum('total'), 2),
                'paid_year' => round((float) $this->paidSlips()->where('d.module', $code)->where('d.year', $this->year)->sum('s.amount'), 2),
                'emitted' => round($emitted, 2),
                'paid' => round($paid, 2),
                'remaining' => max(0.0, round($emitted - $paid, 2)),
                'rate' => self::rate($paid, $emitted),
            ];
        }

        return $result;
    }

    /** @return array{labels: array, emitted: array, paid: array} */
    public function monthly(): array
    {
        $emitted = array_fill(0, 12, 0.0);
        $paid = array_fill(0, 12, 0.0);

        $this->declarations()->whereYear('declared_on', $this->year)
            ->selectRaw('MONTH(declared_on) as m, SUM(total) as s')->groupByRaw('MONTH(declared_on)')
            ->pluck('s', 'm')->each(function ($sum, $m) use (&$emitted) {
                $emitted[$m - 1] = round((float) $sum, 2);
            });
        DB::table('payment_slips')->where('status', 'paye')->whereYear('paid_on', $this->year)
            ->selectRaw('MONTH(paid_on) as m, SUM(amount) as s')->groupByRaw('MONTH(paid_on)')
            ->pluck('s', 'm')->each(function ($sum, $m) use (&$paid) {
                $paid[$m - 1] = round((float) $sum, 2);
            });

        return ['labels' => array_map('tr', self::MONTHS), 'series' => [__('Émis'), __('Encaissé')], 'emitted' => $emitted, 'paid' => $paid];
    }

    public function latestReceipts(int $limit = 6)
    {
        return $this->slipQuery()->where('s.status', 'paye')
            ->orderByDesc('s.receipt_date')->orderByDesc('s.id')->limit($limit)->get();
    }

    public function pendingSlips(int $limit = 6)
    {
        return $this->slipQuery()->where('s.status', 'en_attente')->orderByDesc('s.id')->limit($limit)->get();
    }

    public function recentDeclarations(int $limit = 6)
    {
        return DB::table('tax_declarations as d')
            ->leftJoin('taxpayers as t', 't.id', '=', 'd.taxpayer_id')
            ->where('d.status', '!=', 'annule')
            ->orderByDesc('d.created_at')->orderByDesc('d.id')->limit($limit)
            ->get(['d.number', 'd.module', 'd.year', 'd.quarter', 'd.total', 'd.status', 'd.declared_on',
                't.last_name', 't.first_name', 't.company_name', 't.cin']);
    }

    public static function rate(float $paid, float $emitted): float
    {
        return $emitted > 0 ? round($paid / $emitted * 100, 1) : 0.0;
    }

    protected function declarations()
    {
        return DB::table('tax_declarations')->where('status', '!=', 'annule');
    }

    protected function paidSlips()
    {
        return DB::table('payment_slips as s')->join('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->where('s.status', 'paye');
    }

    protected function slipQuery()
    {
        return DB::table('payment_slips as s')
            ->join('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->leftJoin('taxpayers as t', 't.id', '=', 's.taxpayer_id')
            ->select(['s.id', 's.slip_number', 's.receipt_number', 's.receipt_date', 's.amount', 's.payment_mode', 's.created_at',
                'd.module', 'd.year', 'd.quarter', 't.last_name', 't.first_name', 't.company_name', 't.cin']);
    }

    protected function activeFiles(array $m): int
    {
        return DB::table($m['table'])->where($m['where'])->count();
    }
}

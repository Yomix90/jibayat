<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\IssueConfig;
use App\Domain\Documents\Models\IssueSlip;
use App\Domain\Documents\Models\TreasuryStatement;
use App\Domain\Documents\Models\TreasuryStatementLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bordereaux d'émission — portage de Flask modules/emission (generator.py + routes.py).
 * Un bordereau par ligne du relevé GIR ; numéros séquentiels dans l'année ; report des antérieurs par rubrique ;
 * 1ère partie (auto = cumul des mois antérieurs | manuel) et 2ème partie (auto = total du mois | manuel | vide).
 * Les bordereaux sont des documents dérivés : ils sont recalculés pour toute l'année à chaque modification.
 */
class IssueSlipService
{
    public const MONTHS = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];

    /** Code budgétaire ➜ [rubrique, intitulé officiel] (Flask emission/config.CODE_TO_RUBRIQUE). */
    public const RUBRICS = [
        '1140201016' => ['Trans-Public', 'Taxe sur le transport public des voyageurs'],
        '1140102027' => ['Affermage', "Produit d'affermage des souks communaux"],
        '1140102026' => ['Loyer', 'Produit de location des locaux à usage commercial ou professionnel'],
        '1130101015' => ['O-De Construction', 'Taxe sur les opérations de construction'],
        '1110101011' => ['Légalisation', 'Taxe de légalisation des signatures et de certification des documents'],
        '1110302023' => ['PVP', "Produit des ventes de plans, d'imprimés et de dossiers de concours"],
        '1110302024' => ['Vente Animaux', "Produit des ventes des animaux et d'objets mis en fourrière"],
        '1110403032' => ['Fourrière', 'Droits de fourrière'],
        '1120103034' => ['RR-Eau', "Raccordement au réseau d'eau"],
        '1140103043' => ['PS-Eau', "Produit de l'exploitation du service des eaux"],
        '1130101014' => ['TNB', 'Taxe sur les terrains urbains non bâtis'],
        '1110103031' => ['Etat Civil', "Droit d'état civil"],
        '1130102022' => ['ODP-Construction', 'Redevance d\'occupation temporaire du domaine public communal'],
        '1110401014' => ['Pourcentage Ventes', 'Pourcentage sur les ventes publiques effectuées par la collectivité'],
        '1140203033' => ['Stationnement', 'Droit de stationnement sur les véhicules affectés au transport public'],
        '1120103031' => ['Ambulance', "Remboursement des frais de transport effectués par l'ambulance communale"],
        '1140101011' => ['Boisson', 'Taxe sur les débits de boissons'],
        '1110203033' => ['Commodo-Incommodo', "Frais d'enquête de commodo et d'incommodo"],
        '1130102025' => ['Biens Immobiliers', 'Autres produits des biens immobiliers'],
        '1140102038' => ['ODP Meubles & Immeubles', "Redevance d'occupation temporaire du domaine public (commerce/industrie)"],
        '1140102037' => ['ODP Commercial', "Redevance d'occupation temporaire du domaine public communal"],
        '1190101011' => ['Recettes Imprévues', 'Recettes imprévues'],
    ];

    public function config(int $year): array
    {
        $c = IssueConfig::where('year', $year)->first();

        return [
            'first_part_mode' => $c->first_part_mode ?? 'auto', 'first_part_value' => (float) ($c->first_part_value ?? 0),
            'second_part_mode' => $c->second_part_mode ?? 'vide', 'second_part_value' => (float) ($c->second_part_value ?? 0),
            'notes' => $c->notes ?? '',
        ];
    }

    public function saveConfig(int $year, array $data, User $user): void
    {
        IssueConfig::updateOrCreate(['year' => $year], [
            'first_part_mode' => $data['first_part_mode'], 'first_part_value' => (float) ($data['first_part_value'] ?: 0),
            'second_part_mode' => $data['second_part_mode'], 'second_part_value' => (float) ($data['second_part_value'] ?: 0),
            'notes' => $data['notes'] ?? null,
        ]);
        activity('emission')->causedBy($user)->withProperties(['annee' => $year] + $data)->log("Configuration des bordereaux d'émission $year modifiée");
        $this->regenerateYear($year);
    }

    protected function priorSum(int $year, int $month, ?string $rubric = null): float
    {
        if ($month <= 1) {
            return 0.0;
        }

        return (float) IssueSlip::query()->join('treasury_statements as ts', 'ts.id', '=', 'issue_slips.treasury_statement_id')
            ->where('ts.year', $year)->where('ts.month', '<', $month)->when($rubric !== null, fn ($q) => $q->where('issue_slips.rubric', $rubric))
            ->sum('issue_slips.current_amount');
    }

    /** Valeurs globales du mois (en-tête des bordereaux et écran du mois). */
    public function globals(TreasuryStatement $st): array
    {
        $cfg = $this->config($st->year);
        $report = $this->priorSum($st->year, $st->month);
        $present = (float) $st->grand_total;

        return [
            'report_global' => $report, 'total_present_global' => $present,
            'premiere_partie_global' => $cfg['first_part_mode'] === 'auto' ? $report : $cfg['first_part_value'],
            'deuxieme_partie_global' => match ($cfg['second_part_mode']) { 'auto' => $present, 'manuel' => $cfg['second_part_value'], default => null },
            'premiere_partie_mode' => $cfg['first_part_mode'], 'deuxieme_partie_mode' => $cfg['second_part_mode'],
        ];
    }

    /** Flask generer_tous_bordereaux : recrée les bordereaux d'un mois. */
    public function generate(TreasuryStatement $st): array
    {
        return DB::transaction(function () use ($st) {
            IssueSlip::where('treasury_statement_id', $st->id)->delete();
            $num = (int) IssueSlip::query()->join('treasury_statements as ts', 'ts.id', '=', 'issue_slips.treasury_statement_id')
                ->where('ts.year', $st->year)->where('ts.month', '<', $st->month)->max('issue_slips.number');
            $g = $this->globals($st);
            $out = [];
            foreach (TreasuryStatementLine::where('treasury_statement_id', $st->id)->orderBy('id')->get() as $line) {
                $code = (string) $line->budget_code;
                $known = self::RUBRICS[$code] ?? null;
                $rubric = $known[0] ?? ($line->nature ?: 'Recettes Imprévues');
                $title = $known[1] ?? ($line->nature ?: 'Recettes imprévues');
                $amount = (float) $line->amount;
                $report = $this->priorSum($st->year, $st->month, $rubric);
                $slip = IssueSlip::create(['treasury_statement_id' => $st->id, 'number' => ++$num, 'rubric' => $rubric, 'budget_code' => $code,
                    'title' => $title, 'current_amount' => $amount, 'carried_forward' => $report, 'total' => $amount + $report, 'generated_at' => now()]);
                $out[] = ['numero_bordereau' => $num, 'mois' => $st->month, 'annee' => $st->year, 'rubrique' => $rubric, 'code_budgetaire' => $code,
                    'intitule' => $title, 'montant_present' => $amount, 'report_anterieurs' => $report, 'total' => $amount + $report, 'id' => $slip->id] + $g;
            }

            return $out;
        });
    }

    /** Flask regenerer_annee : tous les mois de l'année, dans l'ordre. */
    public function regenerateYear(int $year): array
    {
        $out = [];
        foreach (TreasuryStatement::where('year', $year)->orderBy('month')->get() as $st) {
            $out = array_merge($out, $this->generate($st));
        }

        return $out;
    }

    /** Données d'impression d'un bordereau. */
    public function slipData(IssueSlip $slip): array
    {
        $st = $slip->treasuryStatement;

        return ['numero_bordereau' => $slip->number, 'mois' => $st->month, 'annee' => $st->year, 'rubrique' => $slip->rubric, 'code_budgetaire' => $slip->budget_code,
            'intitule' => $slip->title, 'montant_present' => (float) $slip->current_amount, 'report_anterieurs' => (float) $slip->carried_forward,
            'total' => (float) $slip->total] + $this->globals($st);
    }

    /** Import d'un bordereau GIR (Flask upload). */
    public function import(array $parsed, int $month, int $year, string $sourceFile, bool $replace, ?float $collective, User $user): TreasuryStatement
    {
        $st = DB::transaction(function () use ($parsed, $month, $year, $sourceFile, $replace, $collective) {
            $existing = TreasuryStatement::where('year', $year)->where('month', $month)->lockForUpdate()->first();
            if ($existing && ! $replace) {
                throw ValidationException::withMessages(['file' => 'Le bordereau de '.self::MONTHS[$month]." $year existe déjà. Cochez « Remplacer l'import existant » pour le mettre à jour."]);
            }
            $existing?->delete();
            $st = TreasuryStatement::create(['month' => $month, 'year' => $year, 'source_file' => $sourceFile, 'imported_at' => now(),
                'grand_total' => $collective !== null && $collective > 0 ? $collective : $parsed['total_general']]);
            foreach ($parsed['lignes'] as $l) {
                $st->lines()->create(['budget_code' => $l['code_budgetaire'], 'nature' => $l['nature_recette'], 'amount' => $l['montant']]);
            }

            return $st;
        });
        activity('emission')->causedBy($user)->performedOn($st)->withProperties(['fichier' => $sourceFile, 'lignes' => count($parsed['lignes'])])
            ->log('Import du bordereau GIR '.self::MONTHS[$month]." $year");
        $this->regenerateYear($year);

        return $st;
    }

    protected function syncTotal(TreasuryStatement $st): void
    {
        $st->update(['grand_total' => (float) $st->lines()->sum('amount')]);
    }

    public function updateAmount(TreasuryStatement $st, string $code, float $amount, User $user): void
    {
        DB::transaction(function () use ($st, $code, $amount) {
            $st->lines()->where('budget_code', $code)->update(['amount' => $amount]);
            $this->syncTotal($st);
        });
        activity('emission')->causedBy($user)->performedOn($st)->withProperties(['code' => $code, 'montant' => $amount])->log("Montant de la rubrique $code modifié");
        $this->regenerateYear($st->year);
    }

    public function deleteLine(TreasuryStatement $st, string $code, User $user): void
    {
        DB::transaction(function () use ($st, $code) {
            $st->lines()->where('budget_code', $code)->delete();
            $this->syncTotal($st);
        });
        activity('emission')->causedBy($user)->performedOn($st)->withProperties(['code' => $code])->log("Rubrique $code retirée du bordereau GIR");
        $this->regenerateYear($st->year);
    }

    public function addLine(int $year, int $month, string $code, string $nature, float $amount, User $user): TreasuryStatement
    {
        $st = DB::transaction(function () use ($year, $month, $code, $nature, $amount) {
            $st = TreasuryStatement::firstOrCreate(['year' => $year, 'month' => $month], ['source_file' => 'Saisie Manuelle', 'imported_at' => now(), 'grand_total' => 0]);
            if ($st->lines()->where('budget_code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => "La rubrique $code existe déjà pour ce mois : modifiez directement son montant."]);
            }
            $st->lines()->create(['budget_code' => $code, 'nature' => self::RUBRICS[$code][1] ?? ($nature ?: 'Recette ajoutée manuellement'), 'amount' => $amount]);
            $this->syncTotal($st);

            return $st;
        });
        activity('emission')->causedBy($user)->performedOn($st)->withProperties(['code' => $code, 'montant' => $amount])->log("Rubrique $code ajoutée au bordereau GIR");
        $this->regenerateYear($year);

        return $st;
    }

    public function deleteMonth(TreasuryStatement $st, User $user): void
    {
        $year = $st->year;
        activity('emission')->causedBy($user)->withProperties(['annee' => $year, 'mois' => $st->month, 'total' => (float) $st->grand_total])
            ->log('Bordereau GIR '.self::MONTHS[$st->month]." $year supprimé");
        $st->delete();
        $this->regenerateYear($year);
    }

    /** Tableau de bord de l'année (Flask emission.dashboard). */
    public function yearSummary(int $year): array
    {
        $sts = TreasuryStatement::where('year', $year)->withCount('issueSlips')->get()->keyBy('month');
        $months = [];
        foreach (self::MONTHS as $m => $name) {
            $st = $sts->get($m);
            $months[$m] = ['mois' => $m, 'nom' => $name, 'statut' => $st ? 'importe' : 'vide', 'total' => (float) ($st->grand_total ?? 0),
                'nb_rubriques' => (int) ($st->issue_slips_count ?? 0), 'fichier_source' => $st?->source_file, 'date_import' => $st?->imported_at];
        }
        $imported = collect($months)->where('statut', 'importe');
        $total = $imported->sum('total');

        return ['mois' => $months, 'total_annee' => $total, 'nb_mois_importes' => $imported->count(), 'nb_bordereaux' => $imported->sum('nb_rubriques'),
            'moyenne_mensuelle' => $imported->count() ? $total / $imported->count() : 0.0];
    }

    /** Flask _compute_cumul_annee : cumul des lignes de l'année jusqu'au mois inclus. */
    public function cumulative(int $year, int $month): float
    {
        return (float) TreasuryStatementLine::query()->join('treasury_statements as ts', 'ts.id', '=', 'treasury_statement_lines.treasury_statement_id')
            ->where('ts.year', $year)->where('ts.month', '<=', $month)->sum('treasury_statement_lines.amount');
    }
}

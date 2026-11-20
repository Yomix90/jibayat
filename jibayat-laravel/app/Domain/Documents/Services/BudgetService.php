<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\BudgetLine;
use App\Domain\Documents\Support\BudgetNomenclature;
use App\Models\User;
use App\Support\PyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Budget communal des recettes (projet et réalisations) — portage de Flask modules/regie.py (regie_budget*).
 * Ligne = chapitre ; regroupement par domaine ; taux = réalisé / proposé ; écart = réalisé − proposé.
 * Synchronisation : le réalisé des chapitres liés à un module Jibayat = bulletins payés de l'année (date de paiement).
 */
class BudgetService
{
    /** Flask init_budget_nomenclature : crée la nomenclature officielle de l'année si elle est vide. */
    public function ensureYear(int $year): void
    {
        if (BudgetLine::where('year', $year)->exists()) {
            return;
        }
        $now = now();
        $rows = [];
        foreach (BudgetNomenclature::ITEMS as [$chap, $gir, $fr, $ar, $dom, $domFr, $domAr, $module, $order, $n1, $prop]) {
            $rows[] = ['year' => $year, 'chapter' => $chap, 'gir_code' => $gir, 'label' => $fr, 'label_fr' => $fr, 'label_ar' => $ar, 'domain' => $dom,
                'domain_fr' => $domFr, 'domain_ar' => $domAr, 'module' => $module, 'sort_order' => $order, 'previous_actual' => $year === 2022 ? $n1 : 0,
                'proposed' => $year === 2022 ? $prop : 0, 'actual' => 0, 'created_at' => $now, 'updated_at' => $now];
        }
        BudgetLine::insertOrIgnore($rows);
    }

    public function years(int $current): array
    {
        return BudgetLine::distinct()->pluck('year')->push($current)->unique()->sortDesc()->values()->all();
    }

    public static function rate(float $actual, float $proposed, bool $line = true): float
    {
        return $proposed > 0 ? PyMath::round($actual / $proposed * 100, 1) : ($line && $actual > 0 ? 100.0 : 0.0);
    }

    /** Flask regie_budget : domaines ordonnés, lignes (taux, écart), sous-totaux et total général. */
    public function overview(int $year): array
    {
        $domains = [];
        foreach (BudgetNomenclature::DOMAINS as $code => [$ar, $fr]) {
            $domains[$code] = ['code' => $code, 'nom_ar' => $ar, 'nom_fr' => $fr, 'lignes' => [], 'total_n1' => 0.0, 'total_propose' => 0.0, 'total_realise' => 0.0];
        }
        foreach (BudgetLine::where('year', $year)->orderBy('sort_order')->orderBy('id')->get() as $l) {
            $code = $l->domain ?: 'ADMINISTRATION';
            $domains[$code] ??= ['code' => $code, 'nom_ar' => $l->domain_ar ?: 'مجال آخر', 'nom_fr' => $l->domain_fr ?: $code, 'lignes' => [],
                'total_n1' => 0.0, 'total_propose' => 0.0, 'total_realise' => 0.0];
            $p = (float) $l->proposed;
            $r = (float) $l->actual;
            $l->setAttribute('rate', self::rate($r, $p));
            $l->setAttribute('gap', $r - $p);
            $domains[$code]['lignes'][] = $l;
            $domains[$code]['total_n1'] += (float) $l->previous_actual;
            $domains[$code]['total_propose'] += $p;
            $domains[$code]['total_realise'] += $r;
        }
        $total = ['total_n1' => 0.0, 'total_propose' => 0.0, 'total_realise' => 0.0];
        $list = [];
        foreach ($domains as $d) {
            if (! $d['lignes']) {
                continue;
            }
            $d['total_ecart'] = $d['total_realise'] - $d['total_propose'];
            $d['total_taux'] = self::rate($d['total_realise'], $d['total_propose'], false);
            foreach (['total_n1', 'total_propose', 'total_realise'] as $k) {
                $total[$k] += $d[$k];
            }
            $list[] = $d;
        }
        $total['total_ecart'] = $total['total_realise'] - $total['total_propose'];
        $total['total_taux'] = self::rate($total['total_realise'], $total['total_propose'], false);

        return ['domaines' => $list, 'grand_total' => $total, 'annee_n1' => $year === 2022 ? 2020 : $year - 1];
    }

    /** Flask regie_budget_sauvegarder. @param array<int, array{proposed?: mixed, actual?: mixed, previous_actual?: mixed, notes?: ?string}> $values */
    public function save(int $year, array $values, User $user): int
    {
        $n = 0;
        DB::transaction(function () use ($year, $values, &$n) {
            foreach (BudgetLine::where('year', $year)->get() as $l) {
                if (! isset($values[$l->id])) {
                    continue;
                }
                $v = $values[$l->id];
                $num = fn ($x) => is_numeric($s = str_replace([' ', ','], ['', '.'], (string) ($x ?? ''))) ? (float) $s : 0.0;
                $l->update(['proposed' => $num($v['proposed'] ?? null), 'actual' => $num($v['actual'] ?? null), 'previous_actual' => $num($v['previous_actual'] ?? null),
                    'notes' => trim((string) ($v['notes'] ?? '')) ?: null]);
                $n++;
            }
        });
        activity('budget')->causedBy($user)->withProperties(['annee' => $year, 'lignes' => $n])->log("Budget $year enregistré");

        return $n;
    }

    /** Montants réalisés des modules Jibayat pour l'année (Flask regie_budget_synchroniser). */
    public function realised(int $year): array
    {
        $byModule = DB::table('payment_slips as s')->join('tax_declarations as d', 'd.id', '=', 's.tax_declaration_id')
            ->whereIn('s.status', ['valide', 'encaisse', 'paye'])->whereNull('s.cancelled_at')->whereYear('s.paid_on', $year)
            ->groupBy('d.module')->selectRaw('d.module, COALESCE(SUM(s.amount), 0) total')->pluck('total', 'module');
        $out = [];
        foreach (['FOURRIERE', 'TNB', 'DEBITS_BOISSONS', 'LOCATION_LOCAUX', 'AFFERMAGE_SOUKS', 'OCCUPATION_DOMAINE', 'STATIONNEMENT'] as $m) {
            $out[$m] = (float) ($byModule[$m] ?? 0);
        }
        $out['FOURRIERE_ENCHERES'] = (float) DB::table('auction_sales')->whereYear('sold_on', $year)->sum('hammer_price');

        return $out;
    }

    public function sync(int $year, User $user): int
    {
        $n = 0;
        $amounts = $this->realised($year);
        DB::transaction(function () use ($year, $amounts, &$n) {
            foreach ($amounts as $module => $amount) {
                $n += BudgetLine::where('year', $year)->where('module', $module)->update(['actual' => $amount, 'updated_at' => now()]);
            }
        });
        activity('budget')->causedBy($user)->withProperties(['annee' => $year, 'montants' => $amounts])->log("Budget $year synchronisé avec les encaissements");

        return $n;
    }

    /** Flask _normalize_budget_chapitre. */
    public static function normalizeChapter(string $raw): string
    {
        $s = str_replace(',', '.', trim($raw));
        if (str_ends_with($s, '.0') && strlen($s) > 4) {
            $s = substr($s, 0, -2);
        }
        $digits = preg_replace('/\D/', '', $s);
        if (strlen($digits) === 8 && ! str_contains($s, '.')) {
            $s = substr($digits, 0, 2).'.'.substr($digits, 2, 2).'.'.substr($digits, 4, 2).'.'.substr($digits, 6, 2);
        } elseif (strlen($digits) === 7 && ! str_contains($s, '.')) {
            $s = substr($digits, 0, 2).'.'.substr($digits, 2, 2).'.'.substr($digits, 4, 2).'.'.substr($digits, 6, 1).'0';
        }
        $s = match ($s) {
            '10.4010.14', '10.40.1014' => '10.40.10.14',
            '20.1033.34', '20.10.3334' => '20.10.33.34',
            '40.10.1016', '40.1010.16' => '40.10.10.16',
            '40.1010.25' => '40.10.10.25',
            default => $s,
        };

        return ['10.10.10.12' => '10.10.30.31', '30.10.10.18' => '30.10.10.11', '30.10.10.20' => '30.10.10.19', '40.20.30.33' => '40.20.10.11',
            '60.20.10' => '23.50.10.10.11'][$s] ?? $s;
    }

    /** Lignes de la feuille d'import : la feuille dont le nom contient l'année, sinon la première. */
    protected function sheetRows(string $path, int $year): array
    {
        $book = IOFactory::load($path);
        $sheet = $book->getSheet(0);
        foreach ($book->getWorksheetIterator() as $ws) {
            if (str_contains($ws->getTitle(), (string) $year)) {
                $sheet = $ws;
                break;
            }
        }
        $rows = [];
        foreach ($sheet->toArray(null, true, false, false) as $r) {
            $rows[] = array_map(fn ($v) => $v === '' ? null : $v, $r);
        }

        return $rows;
    }

    /** Flask regie_budget_importer : met à jour (ou crée) les chapitres à partir d'un classeur budgétaire. */
    public function import(string $path, int $year, User $user): int
    {
        try {
            $rows = $this->sheetRows($path, $year);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file' => 'Erreur lors de la lecture du fichier : '.$e->getMessage()]);
        }
        $byChap = $byGir = [];
        foreach (BudgetNomenclature::ITEMS as $item) {
            $byChap[$item[0]] = $item;
            $byGir[$item[1]] = $item;
        }
        $dom = ['ADMINISTRATION', 'مجال الإدارة العامة', 'Administration Générale'];
        $n = 0;
        DB::transaction(function () use ($rows, $year, $byChap, $byGir, &$dom, &$n) {
            foreach ($rows as $row) {
                if (! collect($row)->contains(fn ($v) => $v !== null && trim(SpreadsheetReader::str($v)) !== '')) {
                    continue;
                }
                $c0 = str_replace(',', '.', trim($row[0] === null ? '' : SpreadsheetReader::str($row[0])));
                $c1 = count($row) > 1 && $row[1] !== null ? trim(SpreadsheetReader::str($row[1])) : '';
                $total = 'المجموع';
                if (str_contains($c0, $total) || str_contains($c1, $total) || str_contains($c0, 'المـــــجــموع')) {
                    $has = fn (string $w) => str_contains($c0, $w) || str_contains($c1, $w);
                    $dom = match (true) {
                        $has('الإدارة') => ['SOCIAL', 'مجال الشؤون الاجتماعية', 'Affaires Sociales'],
                        $has('الاجتماعية') => ['TECHNIQUE', 'مجال الشؤون التقنية', 'Affaires Techniques & Urbanisme'],
                        $has('التقنية') => ['ECONOMIQUE', 'مجال الشؤون الاقتصادية', 'Affaires Économiques'],
                        $has('الاقتصادية') => ['SOUTIEN', 'مجال الدعم والمالية', 'Finances & Soutien'],
                        default => $dom,
                    };

                    continue;
                }
                if (! preg_match('/\d/', $c0) || mb_strlen($c0) < 3) {
                    continue;
                }
                $chap = self::normalizeChapter($c0);
                $num = fn (int $i) => isset($row[$i]) && (is_int($row[$i]) || is_float($row[$i])) ? (float) $row[$i] : 0.0;
                [$n1, $prop, $real] = [$num(2), $num(3), $num(4)];
                $m = $byChap[$chap] ?? $byGir[$chap] ?? null;
                $labelFr = $m[2] ?? $c1;
                $labelAr = $m[3] ?? $c1;
                $line = BudgetLine::where('year', $year)->where('chapter', $chap)->lockForUpdate()->first();
                if ($line) {
                    $line->update(['gir_code' => $m[1] ?? $line->gir_code, 'label' => $labelFr, 'label_fr' => $labelFr ?? $line->label_fr, 'label_ar' => $labelAr ?? $line->label_ar,
                        'previous_actual' => $n1, 'proposed' => $prop, 'actual' => $real > 0 ? $real : $line->actual]);
                } else {
                    BudgetLine::create(['year' => $year, 'chapter' => $chap, 'gir_code' => $m[1] ?? null, 'label' => $labelFr, 'label_fr' => $labelFr, 'label_ar' => $labelAr,
                        'domain' => $m[4] ?? $dom[0], 'domain_fr' => $m[5] ?? $dom[2], 'domain_ar' => $m[6] ?? $dom[1], 'module' => $m[7] ?? null,
                        'previous_actual' => $n1, 'proposed' => $prop, 'actual' => $real, 'sort_order' => 0]);
                }
                $n++;
            }
        });
        activity('budget')->causedBy($user)->withProperties(['annee' => $year, 'chapitres' => $n])->log("Import du budget $year");

        return $n;
    }

    /** Flask regie_budget_ligne_ajouter. */
    public function addLine(int $year, array $data, User $user): BudgetLine
    {
        if (BudgetLine::where('year', $year)->where('chapter', $data['chapter'])->exists()) {
            throw ValidationException::withMessages(['line.chapter' => "Le chapitre {$data['chapter']} existe déjà dans le budget $year."]);
        }
        [$domAr, $domFr] = BudgetNomenclature::DOMAINS[$data['domain']] ?? BudgetNomenclature::DOMAINS['ADMINISTRATION'];
        $line = BudgetLine::create(['year' => $year, 'chapter' => $data['chapter'], 'gir_code' => $data['gir_code'] ?? '', 'label' => ($data['label_fr'] ?? '') ?: $data['label_ar'],
            'label_fr' => $data['label_fr'] ?? '', 'label_ar' => $data['label_ar'] ?? '', 'domain' => $data['domain'], 'domain_fr' => $domFr, 'domain_ar' => $domAr,
            'previous_actual' => (float) ($data['previous_actual'] ?: 0), 'proposed' => (float) ($data['proposed'] ?: 0), 'actual' => (float) ($data['actual'] ?: 0),
            'sort_order' => (int) BudgetLine::where('year', $year)->max('sort_order') + 1]);
        activity('budget')->causedBy($user)->performedOn($line)->log("Chapitre {$line->chapter} ajouté au budget $year");

        return $line;
    }

    public function deleteLine(BudgetLine $line, User $user): void
    {
        activity('budget')->causedBy($user)->withProperties($line->only('year', 'chapter', 'label', 'proposed', 'actual'))->log("Chapitre {$line->chapter} retiré du budget {$line->year}");
        $line->delete();
    }
}

<?php

namespace App\Domain\Documents\Services;

use Carbon\CarbonImmutable;

/**
 * Relevé d'activité TGR (export Excel « Activité mensuelle ») — portage de Flask modules/tgr_parser.parse_releve.
 * Flask échouait sur tout fichier (bug de découpage de ligne) : ce portage applique la logique que le code voulait.
 * Écart : un libellé « Régie » vide n'est plus lu comme « None ».
 *
 * @phpstan-type Operation array{num:int, ref_paiement:int, date:?string, partie_versante:string, mode_paiement:string, ref_creance:string,
 *     code_rubrique:string, libelle_rubrique:string, principal:float, majoration:float, penalites:float, astreintes:float, total:float}
 */
class TgrStatementParser
{
    /** @return array{meta: array<string, mixed>, operations: list<array<string, mixed>>} */
    public function parse(string $path): array
    {
        return $this->parseRows(SpreadsheetReader::rows($path));
    }

    public function parseRows(array $rows): array
    {
        $meta = $this->meta($rows);
        $ops = $this->operations($rows, $this->headerRow($rows) + 1);
        $t = ['total_principal' => 0.0, 'total_majoration' => 0.0, 'total_penalites' => 0.0, 'total_astreintes' => 0.0, 'total_general' => 0.0];
        foreach ($ops as $i => &$op) {
            $op['num'] = $i + 1;
            $t['total_principal'] += $op['principal'];
            $t['total_majoration'] += $op['majoration'];
            $t['total_penalites'] += $op['penalites'];
            $t['total_astreintes'] += $op['astreintes'];
            $t['total_general'] += $op['total'];
        }
        unset($op);
        $dates = array_filter(array_column($ops, 'date'));
        if ($dates) {
            $meta['periode_debut'] = min($dates);
            $meta['periode_fin'] = max($dates);
        }

        return ['meta' => $meta + $t, 'operations' => $ops];
    }

    protected static function cell(array $row, int $i): mixed
    {
        return $row[$i] ?? null;
    }

    /** Valeur à droite d'un libellé : cellule suivante, sinon première cellule non vide. */
    protected static function valueAfter(array $row, int $j): string
    {
        for ($k = $j + 1; $k < count($row); $k++) {
            if (! SpreadsheetReader::isNa($row[$k])) {
                return trim(SpreadsheetReader::str($row[$k]));
            }
        }

        return '';
    }

    protected function meta(array $rows): array
    {
        $commune = $regie = $periode = '';
        for ($i = 0; $i < min(10, count($rows)); $i++) {
            foreach ($rows[$i] as $j => $val) {
                $v = mb_strtoupper(trim(SpreadsheetReader::str($val)));
                if (str_contains($v, 'COMMUNE/PROVINCE')) {
                    $commune = self::valueAfter($rows[$i], $j);
                } elseif (str_contains($v, 'RÉGIE') || str_contains($v, 'REGIE')) {
                    $regie = self::valueAfter($rows[$i], $j) ?: $regie;
                } elseif (str_contains($v, 'JOURNÉE') || str_contains($v, 'JOURNEE')) {
                    $periode = self::valueAfter($rows[$i], $j);
                }
            }
        }
        [$start, $end] = [null, null];
        if (preg_match_all('#(\d{2}/\d{2}/\d{4})#', $periode, $m) && count($m[1]) >= 2) {
            $start = CarbonImmutable::createFromFormat('d/m/Y', $m[1][0])->toDateString();
            $end = CarbonImmutable::createFromFormat('d/m/Y', $m[1][1])->toDateString();
        }

        return ['commune' => $commune, 'regie' => $regie, 'periode_debut' => $start, 'periode_fin' => $end, 'nb_pages' => 0];
    }

    protected function headerRow(array $rows): int
    {
        for ($i = 0; $i < min(15, count($rows)); $i++) {
            foreach ($rows[$i] as $v) {
                if (str_contains(mb_strtoupper(SpreadsheetReader::str($v)), 'MODE DE PAIEMENT')) {
                    return $i;
                }
            }
        }

        return 5;
    }

    protected static function float(mixed $v): float
    {
        if (SpreadsheetReader::isNa($v)) {
            return 0.0;
        }

        return is_numeric($v) ? (float) $v : 0.0;
    }

    protected static function date(mixed $v): ?string
    {
        if (SpreadsheetReader::isNa($v)) {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        $s = explode(' ', trim((string) $v))[0];
        foreach (['Y-m-d', 'd/m/Y', 'Y/m/d', 'd-m-Y'] as $f) {
            $d = \DateTimeImmutable::createFromFormat('!'.$f, $s);
            if ($d && $d->format($f) === $s) {
                return $d->format('Y-m-d');
            }
        }
        try {
            return CarbonImmutable::parse((string) $v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** « 1140102026\nLibellé » ou « 1140102026 Libellé » ➜ [code, libellé]. */
    public static function splitLabel(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $parts = explode("\n", $raw, 2);
        if (count($parts) === 2) {
            return [trim($parts[0]), trim($parts[1])];
        }
        $label = trim(preg_replace('/^\s*\d+\s*/', '', $raw));
        preg_match('/^\s*(\d+)/', $raw, $m);

        return [$m[1] ?? '', $label];
    }

    protected function operations(array $rows, int $start): array
    {
        $ops = [];
        for ($i = $start; $i < count($rows); $i++) {
            $row = $rows[$i];
            $strings = array_map(fn ($v) => mb_strtoupper(trim(SpreadsheetReader::str($v))), $row);
            if (collect($strings)->contains(fn ($s) => str_contains($s, 'SOUS TOTAL'))) {
                continue;
            }
            if (collect($strings)->contains(fn ($s) => str_starts_with($s, 'TOTAL'))) {
                break;
            }
            $ref = self::cell($row, 3);
            if (SpreadsheetReader::isNa($ref) || ! ctype_digit(trim(str_replace('.0', '', SpreadsheetReader::str($ref))))) {
                continue;
            }
            $labelRaw = count($row) > 12 ? SpreadsheetReader::str(self::cell($row, 12)) : '';
            if (in_array($labelRaw, ['nan', 'None'], true) && count($row) > 13) {
                $labelRaw = SpreadsheetReader::str(self::cell($row, 13));
            }
            [$code, $label] = self::splitLabel($labelRaw);

            $date = self::date(self::cell($row, 5)) ?? self::date(self::cell($row, 6));
            $party = trim(SpreadsheetReader::str(self::cell($row, 7)));
            if (in_array(mb_strtolower($party), ['nan', 'none', ''], true)) {
                $party = trim(SpreadsheetReader::str(self::cell($row, 8)));
            }
            $party = in_array(mb_strtolower($party), ['nan', 'none'], true) ? '' : $party;
            $mode = trim(SpreadsheetReader::str(self::cell($row, 1)));
            if (in_array(mb_strtolower($mode), ['nan', 'none', ''], true)) {
                $mode = trim(SpreadsheetReader::str(self::cell($row, 2)));
            }
            $mode = in_array(mb_strtolower($mode), ['nan', 'none'], true) ? '' : $mode;
            $refC = '';
            if (! SpreadsheetReader::isNa(self::cell($row, 10))) {
                $refC = (string) (int) (float) SpreadsheetReader::str(self::cell($row, 10));
            } elseif (! SpreadsheetReader::isNa(self::cell($row, 11))) {
                $v = self::cell($row, 11);
                $refC = is_numeric($v) ? (string) (int) (float) $v : SpreadsheetReader::str($v);
            }

            $pick = fn (int $a, int $b) => ($x = self::float(self::cell($row, $a))) == 0.0 ? self::float(self::cell($row, $b)) : $x;
            $principal = $pick(14, 15);
            $surcharge = $pick(16, 17);
            $penalty = $pick(18, 19);
            $fine = $pick(21, 22);
            $total = $pick(24, 25);
            if ($total == 0.0) {
                $nums = [];
                foreach (array_reverse($row) as $v) {
                    if (is_numeric($v) && ! (is_float($v) && is_nan($v))) {
                        $nums[] = (float) $v;
                    }
                }
                if ($nums) {
                    $total = $nums[0];
                    $fine = $nums[1] ?? $fine;
                    $penalty = $nums[2] ?? $penalty;
                    $surcharge = $nums[3] ?? $surcharge;
                    $principal = $nums[4] ?? $principal;
                }
            }
            $ops[] = ['num' => 0, 'ref_paiement' => (int) (float) $ref, 'date' => $date, 'partie_versante' => $party, 'mode_paiement' => $mode,
                'ref_creance' => $refC, 'code_rubrique' => $code, 'libelle_rubrique' => $label, 'principal' => $principal, 'majoration' => $surcharge,
                'penalites' => $penalty, 'astreintes' => $fine, 'total' => $total];
        }

        return $ops;
    }

    /** Filtres de l'écran (Flask tgr_export / tgr_pdf) : rubrique exacte, période, recherche. */
    public static function filter(array $ops, ?string $rubric, ?string $from, ?string $to, string $search = ''): array
    {
        $search = mb_strtolower(trim($search));

        return array_values(array_filter($ops, fn ($op) => (! $rubric || $op['libelle_rubrique'] === $rubric)
            && (! $from || ! $op['date'] || $op['date'] >= $from) && (! $to || ! $op['date'] || $op['date'] <= $to)
            && ($search === '' || str_contains(mb_strtolower("{$op['partie_versante']} {$op['libelle_rubrique']} {$op['ref_paiement']}"), $search))));
    }
}

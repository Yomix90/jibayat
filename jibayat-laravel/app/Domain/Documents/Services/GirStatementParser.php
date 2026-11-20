<?php

namespace App\Domain\Documents\Services;

/**
 * Bordereau de versement GIR (Excel .xls/.xlsx) — portage de Flask modules/emission/parser.parse_bordereau_versement.
 * Écart volontaire : un code budgétaire lu comme nombre entier (fichiers .xls, « 1140102026.0 ») est reconnu ;
 * Flask l'ignorait et rangeait la ligne en « Recettes imprévues ».
 */
class GirStatementParser
{
    public const MONTHS_FR = ['JANVIER' => 1, 'FEVRIER' => 2, 'FÉVRIER' => 2, 'MARS' => 3, 'AVRIL' => 4, 'MAI' => 5, 'JUIN' => 6, 'JUILLET' => 7,
        'AOUT' => 8, 'AOÛT' => 8, 'SEPTEMBRE' => 9, 'OCTOBRE' => 10, 'NOVEMBRE' => 11, 'DECEMBRE' => 12, 'DÉCEMBRE' => 12];

    public const MONTHS_AR = ['يناير' => 1, 'فبراير' => 2, 'مارس' => 3, 'أبريل' => 4, 'ابريل' => 4, 'ماي' => 5, 'يونيو' => 6, 'يوليوز' => 7,
        'غشت' => 8, 'شتنبر' => 9, 'أكتوبر' => 10, 'اكتوبر' => 10, 'نونبر' => 11, 'دجنبر' => 12];

    public const UNFORESEEN_CODE = '1190101011';

    /** @return array{mois: ?int, annee: ?int, total_general: float, lignes: list<array{code_budgetaire: string, nature_recette: string, montant: float}>} */
    public function parse(string $path): array
    {
        try {
            $rows = SpreadsheetReader::rows($path);
        } catch (\Throwable) {
            return ['mois' => null, 'annee' => null, 'total_general' => 0.0, 'lignes' => []];
        }

        return $this->parseRows($rows);
    }

    protected static function cellText(mixed $v): string
    {
        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : (is_float($v) ? SpreadsheetReader::str($v) : (string) $v);
    }

    protected static function rowString(array $row): string
    {
        $parts = [];
        foreach ($row as $v) {
            if ($v !== null && trim(self::cellText($v)) !== '') {
                $parts[] = self::cellText($v);
            }
        }

        return mb_strtoupper(implode(' ', $parts));
    }

    protected static function positiveNumber(mixed $v): ?float
    {
        if ($v === null) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return $v > 0 ? (float) $v : null;
        }
        $s = str_replace([' ', ','], ['', '.'], (string) $v);

        return is_numeric($s) && (float) $s > 0 ? (float) $s : null;
    }

    protected static function lastPositive(array $row): ?float
    {
        foreach (array_reverse($row) as $v) {
            if (($n = self::positiveNumber($v)) !== null) {
                return $n;
            }
        }

        return null;
    }

    public function parseRows(array $rows): array
    {
        $result = ['mois' => null, 'annee' => null, 'total_general' => 0.0, 'lignes' => []];
        $fileTotal = 0.0;
        foreach ($rows as $row) {
            $s = self::rowString($row);
            if (! $result['annee']) {
                if (preg_match('/EXERCICE\s*[:\s]\s*(\d{4})/u', $s, $m) || preg_match('/ANN[EÉ]E\s*[:\s]\s*(\d{4})/u', $s, $m) || preg_match('/\b(20\d{2})\b/', $s, $m)) {
                    $result['annee'] = (int) $m[1];
                }
            }
            if (! $result['mois']) {
                if (preg_match('/MOIS\s*(?:DE)?\s*[:\s]\s*(\d{1,2})/u', $s, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
                    $result['mois'] = (int) $m[1];
                } else {
                    foreach (self::MONTHS_FR + self::MONTHS_AR as $name => $n) {
                        if (str_contains($s, $name)) {
                            $result['mois'] = $n;
                            break;
                        }
                    }
                }
            }
            if (str_contains($s, 'TOTAL GENERAL') || str_contains($s, 'TOTAL GÉNÉRAL')) {
                $fileTotal = self::lastPositive($row) ?? $fileTotal;
            }
        }

        $inTable = false;
        foreach ($rows as $row) {
            $s = self::rowString($row);
            if ($s === '') {
                continue;
            }
            if (str_contains($s, 'BUDGETAIRE') || str_contains($s, 'BUDGÉTAIRE')) {
                $inTable = true;

                continue;
            }
            if (! $inTable) {
                continue;
            }
            foreach (['TOTAL GENERAL', 'TOTAL GÉNÉRAL', 'ARRÊTÉ', 'ARRETE', 'COMPTABLE', 'REGISSEUR', 'RÉGISSEUR', 'LE PRÉSIDENT', 'LE PRESIDENT'] as $stop) {
                if (str_contains($s, $stop)) {
                    $inTable = false;

                    continue 2;
                }
            }
            foreach (['ROYAUME DU MAROC', 'MINISTERE', 'MINISTÈRE', 'BORDEREAU DE VERSEMENT', 'COMMUNE', 'REGIE DE RECETTES', 'MOIS :'] as $skip) {
                if (str_contains($s, $skip)) {
                    continue 2;
                }
            }
            $code = null;
            $nature = '';
            foreach ($row as $c => $v) {
                if ($v === null) {
                    continue;
                }
                $sv = trim(self::cellText($v));
                if (str_ends_with($sv, '.0')) {
                    $sv = substr($sv, 0, -2);
                }
                if (ctype_digit($sv) && strlen($sv) >= 6) {
                    $code = $sv;
                    for ($c2 = $c + 1; $c2 < count($row); $c2++) {
                        if ($row[$c2] === null) {
                            continue;
                        }
                        $s2 = trim(self::cellText($row[$c2]));
                        if ($s2 !== '' && ! ctype_digit(str_replace('.0', '', $s2)) && mb_strtolower($s2) !== 'none') {
                            $nature = $s2;
                            break;
                        }
                    }
                    break;
                }
            }
            $amount = self::lastPositive($row) ?? 0.0;
            if ($amount > 0) {
                if (! $code) {
                    $code = self::UNFORESEEN_CODE;
                    $nature = $nature ?: 'Recettes imprévues';
                }
                $result['lignes'][] = ['code_budgetaire' => $code, 'nature_recette' => $nature, 'montant' => $amount];
            }
        }
        $sum = \App\Support\PyMath::round(array_sum(array_column($result['lignes'], 'montant')), 2);
        $result['total_general'] = $fileTotal > 0 ? $fileTotal : $sum;

        return $result;
    }
}

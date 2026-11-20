<?php

namespace App\Domain\Documents\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Lecture brute de la première feuille d'un classeur .xls / .xlsx (équivalent openpyxl / xlrd « values_only ») :
 * chaque cellule vaut null, un nombre, une chaîne ou un DateTimeImmutable (cellule au format date).
 */
class SpreadsheetReader
{
    /** @return list<list<mixed>> */
    public static function rows(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $sheet = $reader->load($path)->getSheet(0);
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            $it = $row->getCellIterator();
            $it->setIterateOnlyExistingCells(false);
            foreach ($it as $cell) {
                $v = $cell->getCalculatedValue();
                if ($v !== null && $v !== '' && is_numeric($v) && Date::isDateTime($cell)) {
                    $v = \DateTimeImmutable::createFromMutable(Date::excelToDateTimeObject((float) $v));
                }
                $cells[] = $v === '' ? null : $v;
            }
            while ($cells && end($cells) === null) {
                array_pop($cells);
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /** Représentation texte d'une cellule à la manière de Python str() (1001.0 ➜ « 1001.0 », None ➜ « None »). */
    public static function str(mixed $v): string
    {
        if ($v === null) {
            return 'None';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }
        if (is_float($v)) {
            return floor($v) == $v && abs($v) < 1e15 ? number_format($v, 1, '.', '') : (string) $v;
        }

        return (string) $v;
    }

    public static function isNa(mixed $v): bool
    {
        if ($v === null) {
            return true;
        }
        if (is_float($v) && is_nan($v)) {
            return true;
        }

        return in_array(mb_strtolower(trim(self::str($v))), ['', 'nan', 'nat', 'none'], true);
    }
}

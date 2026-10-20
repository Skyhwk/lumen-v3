<?php

namespace App\Services\GaAsset;

use PhpOffice\PhpSpreadsheet\IOFactory;

class GaAssetExcelTotalParser
{
    public const TOTAL_SHEET = 'Total ';

    /** @return array<int, array{year:int, rows: array<int, array<string, mixed>>, footer: array<string, mixed>|null}> */
    public function parseSections(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName(self::TOTAL_SHEET);
        if (!$sheet) {
            throw new \RuntimeException('Sheet "' . self::TOTAL_SHEET . '" tidak ditemukan.');
        }

        $maxRow = (int) $sheet->getHighestDataRow();
        $sections = [];
        $currentYear = null;
        $rows = [];
        $footer = null;

        for ($row = 1; $row <= $maxRow; $row++) {
            $colA = $this->cellText($sheet, $row, 1);
            $colB = $this->cellText($sheet, $row, 2);

            if ($this->isYearHeader($colA, $sheet, $row)) {
                if ($currentYear !== null) {
                    $sections[] = [
                        'year' => $currentYear,
                        'rows' => $rows,
                        'footer' => $footer,
                    ];
                }
                $currentYear = (int) $colA;
                $rows = [];
                $footer = null;
                continue;
            }

            if ($currentYear === null) {
                continue;
            }

            if (strcasecmp($colA, 'Total') === 0) {
                $footer = $this->readTotalRow($sheet, $row);
                continue;
            }

            if ($colA === '' || $this->isYearHeader($colA, $sheet, $row)) {
                continue;
            }

            $rows[] = $this->readCategoryRow($sheet, $row, $colA, $colB);
        }

        if ($currentYear !== null) {
            $sections[] = [
                'year' => $currentYear,
                'rows' => $rows,
                'footer' => $footer,
            ];
        }

        return $sections;
    }

    public function sectionForYear(string $path, int $year): ?array
    {
        foreach ($this->parseSections($path) as $section) {
            if ((int) $section['year'] === $year) {
                return $section;
            }
        }

        return null;
    }

    private function isYearHeader(string $colA, $sheet, int $row): bool
    {
        if (!preg_match('/^\d{4}$/', $colA)) {
            return false;
        }
        $colC = $this->cellText($sheet, $row, 3);

        return stripos($colC, 'NILAI AWAL') !== false || stripos($colC, 'nilai awal') !== false;
    }

    private function readCategoryRow($sheet, int $row, string $category, string $subCategory): array
    {
        return [
            'category' => strtoupper(trim($category)),
            'sub_category' => strtoupper(trim($subCategory)),
            'nilai_awal' => $this->cellNumber($sheet, $row, 3),
            'pembelian' => $this->cellNumber($sheet, $row, 4),
            'penjualan' => $this->cellNumber($sheet, $row, 5),
            'rusak_tak_terpakai' => $this->cellNumber($sheet, $row, 6),
            'hilang' => $this->cellNumber($sheet, $row, 7),
            'transfer' => $this->cellNumber($sheet, $row, 8),
            'nilai_perolehan' => $this->cellNumber($sheet, $row, 9),
            'depresiasi' => $this->cellNumber($sheet, $row, 10),
            'nilai_buku' => $this->cellNumber($sheet, $row, 11),
        ];
    }

    private function readTotalRow($sheet, int $row): array
    {
        return [
            'nilai_awal' => $this->cellNumber($sheet, $row, 3),
            'pembelian' => $this->cellNumber($sheet, $row, 4),
            'penjualan' => $this->cellNumber($sheet, $row, 5),
            'rusak_tak_terpakai' => $this->cellNumber($sheet, $row, 6),
            'hilang' => $this->cellNumber($sheet, $row, 7),
            'transfer' => $this->cellNumber($sheet, $row, 8),
            'nilai_perolehan' => $this->cellNumber($sheet, $row, 9),
        ];
    }

    private function cellText($sheet, int $row, int $col): string
    {
        $value = $sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();

        return trim((string) $value);
    }

    private function cellNumber($sheet, int $row, int $col): string
    {
        $value = $sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }
}

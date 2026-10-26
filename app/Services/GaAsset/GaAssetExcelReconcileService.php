<?php

namespace App\Services\GaAsset;

class GaAssetExcelReconcileService
{
    private const MONEY_FIELDS = [
        'nilai_awal',
        'pembelian',
        'penjualan',
        'rusak_tak_terpakai',
        'hilang',
        'transfer',
        'nilai_perolehan',
        'depresiasi',
        'book_value',
        'nilai_buku',
        'annual_depreciation',
        'accumulated_depreciation',
    ];

    public function compareYear(
        string $excelPath,
        GaAssetDepreciationService $depreciation,
        int $year,
        ?string $category = null,
        ?string $subCategory = null,
        string $tolerance = '1.00'
    ): array {
        $parser = new GaAssetExcelTotalParser();
        $section = $parser->sectionForYear($excelPath, $year);
        if (!$section) {
            throw new \RuntimeException("Tahun {$year} tidak ditemukan di sheet Total.");
        }

        $system = $depreciation->report($year);
        $excelRows = $this->indexExcelRows($section['rows']);
        $systemRows = $this->indexSystemRows($system['rows']);

        $keys = array_unique(array_merge(array_keys($excelRows), array_keys($systemRows)));
        sort($keys);

        $comparisons = [];
        foreach ($keys as $key) {
            [$cat, $sub] = explode("\n", $key . "\n");
            if ($category !== null && strcasecmp($this->normalize($category), $cat) !== 0) {
                continue;
            }
            if ($subCategory !== null && strcasecmp($this->normalize($subCategory), $sub) !== 0) {
                continue;
            }

            $excel = $excelRows[$key] ?? null;
            $sys = $systemRows[$key] ?? null;
            $fieldDiffs = $this->diffFields($excel, $sys, $tolerance);
            if ($fieldDiffs === []) {
                continue;
            }

            $comparisons[] = [
                'category' => $cat,
                'sub_category' => $sub,
                'excel' => $excel,
                'system' => $sys,
                'diffs' => $fieldDiffs,
            ];
        }

        $footerDiffs = $this->diffFooter($section['footer'] ?? [], $system['totals'] ?? [], $tolerance);

        return [
            'year' => $year,
            'excel_path' => $excelPath,
            'matched_rows' => count($keys) - count($comparisons),
            'mismatch_rows' => count($comparisons),
            'row_diffs' => $comparisons,
            'footer_diffs' => $footerDiffs,
            'ok' => count($comparisons) === 0 && $footerDiffs === [],
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function indexExcelRows(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $cat = $this->normalize($row['category'] ?? '');
            $sub = $this->normalize($row['sub_category'] ?? '-');
            if ($sub === '') {
                $sub = '-';
            }
            $indexed[$cat . "\n" . $sub] = [
                'nilai_awal' => $row['nilai_awal'] ?? '0.00',
                'pembelian' => $row['pembelian'] ?? '0.00',
                'penjualan' => $row['penjualan'] ?? '0.00',
                'rusak_tak_terpakai' => $row['rusak_tak_terpakai'] ?? '0.00',
                'hilang' => $row['hilang'] ?? '0.00',
                'transfer' => $row['transfer'] ?? '0.00',
                'nilai_perolehan' => $row['nilai_perolehan'] ?? '0.00',
                'depresiasi' => $row['depresiasi'] ?? '0.00',
                'nilai_buku' => $row['nilai_buku'] ?? '0.00',
            ];
        }

        return $indexed;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function indexSystemRows(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $cat = $this->normalize($row['category'] ?? '');
            $sub = $this->normalize($row['sub_category'] ?? '-');
            if ($sub === '') {
                $sub = '-';
            }
            $indexed[$cat . "\n" . $sub] = [
                'nilai_awal' => $row['nilai_awal'] ?? '0.00',
                'pembelian' => $row['pembelian'] ?? '0.00',
                'penjualan' => $row['penjualan'] ?? '0.00',
                'rusak_tak_terpakai' => $row['rusak_tak_terpakai'] ?? '0.00',
                'hilang' => $row['hilang'] ?? '0.00',
                'transfer' => $row['transfer'] ?? '0.00',
                'nilai_perolehan' => $row['nilai_perolehan'] ?? '0.00',
                'depresiasi' => $row['annual_depreciation'] ?? '0.00',
                'nilai_buku' => $row['book_value'] ?? '0.00',
            ];
        }

        return $indexed;
    }

    private function diffFields(?array $excel, ?array $system, string $tolerance): array
    {
        $excel = $excel ?? [];
        $system = $system ?? [];
        $diffs = [];

        $fields = ['nilai_awal', 'pembelian', 'penjualan', 'rusak_tak_terpakai', 'hilang', 'transfer', 'nilai_perolehan', 'depresiasi', 'nilai_buku'];
        foreach ($fields as $field) {
            $a = $excel[$field] ?? '0.00';
            $b = $system[$field] ?? '0.00';
            if (!$this->moneyClose($a, $b, $tolerance)) {
                $diffs[] = [
                    'field' => $field,
                    'excel' => $a,
                    'system' => $b,
                    'delta' => number_format((float) $b - (float) $a, 2, '.', ''),
                ];
            }
        }

        if ($excel === [] && $system !== []) {
            return [['field' => '_row', 'excel' => null, 'system' => 'hanya di sistem', 'delta' => '-']];
        }
        if ($system === [] && $excel !== []) {
            return [['field' => '_row', 'excel' => 'hanya di Excel', 'system' => null, 'delta' => '-']];
        }

        return $diffs;
    }

    private function diffFooter(?array $excelFooter, array $systemTotals, string $tolerance): array
    {
        if ($excelFooter === null || $excelFooter === []) {
            return [];
        }

        $map = [
            'nilai_awal' => 'nilai_awal',
            'pembelian' => 'pembelian',
            'penjualan' => 'penjualan',
            'rusak_tak_terpakai' => 'rusak_tak_terpakai',
            'hilang' => 'hilang',
            'transfer' => 'transfer',
            'nilai_perolehan' => 'nilai_perolehan',
        ];

        $diffs = [];
        foreach ($map as $excelKey => $systemKey) {
            $a = $excelFooter[$excelKey] ?? '0.00';
            $b = $systemTotals[$systemKey] ?? '0.00';
            if (!$this->moneyClose($a, $b, $tolerance)) {
                $diffs[] = [
                    'field' => $excelKey,
                    'excel' => $a,
                    'system' => $b,
                    'delta' => number_format((float) $b - (float) $a, 2, '.', ''),
                ];
            }
        }

        return $diffs;
    }

    private function moneyClose(string $a, string $b, string $tolerance): bool
    {
        return abs((float) $a - (float) $b) <= (float) $tolerance;
    }

    private function normalize(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $value)));
    }
}

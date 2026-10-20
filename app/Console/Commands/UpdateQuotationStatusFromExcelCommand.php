<?php

namespace App\Console\Commands;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UpdateQuotationStatusFromExcelCommand extends Command
{
    protected $signature = 'quotation:update-status-from-excel
        {--file= : Path Excel. Default: storage/app/UPDATE LIST QT 15 OKT 2026.xlsx}
        {--dry-run : Baca Excel dan cek DB tanpa update}';

    protected $description = 'Update status_quotation kontrak/non-kontrak dari Excel (QTC=kontrak, QT=non kontrak). Hasil ditulis ke kolom RESULT.';

    public function handle(): int
    {
        $path = $this->option('file') ?: storage_path('app/UPDATE LIST QT 15 OKT 2026.xlsx');
        $dryRun = (bool) $this->option('dry-run');

        if (!is_file($path)) {
            $this->error('File tidak ditemukan: ' . $path);
            return 1;
        }

        $this->info($dryRun ? 'Mode: DRY-RUN' : 'Mode: EXECUTE');
        $this->info('File: ' . $path);

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Sheet1') ?: $spreadsheet->getActiveSheet();
        $highestRow = (int) $sheet->getHighestRow();

        $headerRow = $this->detectHeaderRow($sheet);
        $dataStart = $headerRow + 1;
        $resultCol = $this->resultColumn($sheet, $headerRow);
        $sheet->setCellValueByColumnAndRow($resultCol, $headerRow, 'RESULT');

        $this->info("Header row {$headerRow}, data {$dataStart}-{$highestRow}, RESULT col {$resultCol}");

        $rows = [];
        for ($row = $dataStart; $row <= $highestRow; $row++) {
            $noDocument = $this->normalizeDocument((string) $sheet->getCellByColumnAndRow(3, $row)->getFormattedValue());
            $statusRaw = trim((string) $sheet->getCellByColumnAndRow(4, $row)->getFormattedValue());
            $sales = trim((string) $sheet->getCellByColumnAndRow(1, $row)->getFormattedValue());
            $company = trim((string) $sheet->getCellByColumnAndRow(2, $row)->getFormattedValue());

            if ($noDocument === '' && $statusRaw === '' && $sales === '' && $company === '') {
                continue;
            }

            $rows[] = [
                'row' => $row,
                'no_document' => $noDocument,
                'status' => strtolower(trim($statusRaw)),
                'type' => $this->resolveType($noDocument),
            ];
        }

        $kontrakDocs = [];
        $nonKontrakDocs = [];
        foreach ($rows as $item) {
            if ($item['no_document'] === '' || $item['type'] === null) {
                continue;
            }
            if ($item['type'] === 'kontrak') {
                $kontrakDocs[$item['no_document']] = true;
            } else {
                $nonKontrakDocs[$item['no_document']] = true;
            }
        }

        $foundKontrak = $this->existingDocuments(QuotationKontrakH::class, array_keys($kontrakDocs));
        $foundNonKontrak = $this->existingDocuments(QuotationNonKontrak::class, array_keys($nonKontrakDocs));

        $updates = [
            'kontrak' => [],
            'non_kontrak' => [],
        ];
        $counts = [
            'success' => 0,
            'not Found' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $item) {
            $result = $this->rowResult($item, $foundKontrak, $foundNonKontrak);
            if ($result === 'success') {
                $updates[$item['type']][$item['status']][] = $item['no_document'];
                $counts['success']++;
            } elseif ($result === 'not Found') {
                $counts['not Found']++;
            } else {
                $counts['skipped']++;
            }
            $sheet->setCellValueByColumnAndRow($resultCol, $item['row'], $result);
        }

        if (!$dryRun) {
            $this->applyUpdates($updates);
        } else {
            $this->warn('DRY-RUN: database tidak diubah.');
        }

        $outputPath = $dryRun
            ? preg_replace('/\.xlsx$/i', '_dryrun.xlsx', $path)
            : $path;
        try {
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($outputPath);
            $this->info('Excel hasil: ' . $outputPath);
        } catch (\Throwable $exception) {
            $fallback = preg_replace('/\.xlsx$/i', '_result.xlsx', $path);
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($fallback);
            $this->warn('File asal terkunci. Hasil disimpan ke: ' . $fallback);
            $this->warn($exception->getMessage());
        }

        $this->table(
            ['Result', 'Jumlah'],
            [
                ['success', $counts['success']],
                ['not Found', $counts['not Found']],
                ['skipped', $counts['skipped']],
                ['total baris', count($rows)],
            ]
        );

        return 0;
    }

    private function rowResult(array $item, array $foundKontrak, array $foundNonKontrak): string
    {
        if ($item['no_document'] === '') {
            return 'skipped: empty no document';
        }
        if ($item['status'] === '') {
            return 'skipped: empty status';
        }
        if ($item['type'] === null) {
            return 'skipped: unknown type';
        }

        $found = $item['type'] === 'kontrak' ? $foundKontrak : $foundNonKontrak;
        if (!isset($found[$item['no_document']])) {
            return 'not Found';
        }

        return 'success';
    }

    private function applyUpdates(array $updates): void
    {
        foreach ($updates['kontrak'] as $status => $documents) {
            foreach (array_chunk(array_values(array_unique($documents)), 500) as $chunk) {
                try {
                    QuotationKontrakH::whereIn('no_document', $chunk)->update([
                        'status_quotation' => $status,
                    ]);
                } catch (\Throwable $exception) {
                    $this->warn('Skip update kontrak status ' . $status . ': ' . $exception->getMessage());
                }
            }
        }

        foreach ($updates['non_kontrak'] as $status => $documents) {
            foreach (array_chunk(array_values(array_unique($documents)), 500) as $chunk) {
                try {
                    QuotationNonKontrak::whereIn('no_document', $chunk)->update([
                        'status_quotation' => $status,
                    ]);
                } catch (\Throwable $exception) {
                    $this->warn('Skip update non kontrak status ' . $status . ': ' . $exception->getMessage());
                }
            }
        }
    }

    private function existingDocuments(string $model, array $documents): array
    {
        $found = [];
        foreach (array_chunk($documents, 500) as $chunk) {
            try {
                $rows = $model::whereIn('no_document', $chunk)->pluck('no_document');
                foreach ($rows as $document) {
                    $found[$document] = true;
                }
            } catch (\Throwable $exception) {
                $this->warn('Skip lookup ' . $model . ': ' . $exception->getMessage());
            }
        }

        return $found;
    }

    private function resolveType(string $noDocument): ?string
    {
        if ($noDocument === '') {
            return null;
        }
        if (preg_match('#(^|/)QTC(/|$)#i', $noDocument)) {
            return 'kontrak';
        }
        if (preg_match('#(^|/)QT(/|$)#i', $noDocument)) {
            return 'non_kontrak';
        }

        return null;
    }

    private function normalizeDocument(string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', $value);
        return strtoupper(trim(preg_replace('/\s+/', '', $value)));
    }

    private function detectHeaderRow(Worksheet $sheet): int
    {
        for ($row = 1; $row <= 5; $row++) {
            $colC = strtolower((string) $sheet->getCellByColumnAndRow(3, $row)->getFormattedValue());
            if (strpos($colC, 'no qt') !== false || strpos($colC, 'qt sistem') !== false) {
                return $row;
            }
        }

        return 2;
    }

    private function resultColumn(Worksheet $sheet, int $headerRow): int
    {
        for ($col = 1; $col <= 20; $col++) {
            $header = strtolower(trim((string) $sheet->getCellByColumnAndRow($col, $headerRow)->getFormattedValue()));
            if ($header === 'result') {
                return $col;
            }
        }

        return 7;
    }
}

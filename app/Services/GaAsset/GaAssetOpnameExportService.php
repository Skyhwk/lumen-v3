<?php

namespace App\Services\GaAsset;

use App\Models\GaAsset;
use App\Models\GaAssetOpname;
use App\Models\GaAssetOpnameLine;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GaAssetOpnameExportService
{
    public function exportOpnamePeriod(GaAssetOpname $opname): Spreadsheet
    {
        $opname->loadMissing('cabang:id,nama_cabang');

        $spreadsheet = new Spreadsheet();
        $recap = $spreadsheet->getActiveSheet();
        $recap->setTitle('Rekap');
        $recap->fromArray([
            ['Cabang', $opname->cabang->nama_cabang ?? $opname->branch_id],
            ['Periode', sprintf('%02d/%d', $opname->period_month, $opname->period_year)],
            ['Status', $opname->status],
            [],
            ['Hasil', 'Jumlah'],
        ], null, 'A1');

        $counts = GaAssetOpnameLine::query()
            ->where('opname_id', $opname->id)
            ->selectRaw('result, COUNT(*) as total')
            ->groupBy('result')
            ->pluck('total', 'result');

        $row = 6;
        foreach (['found' => 'Cocok', 'wrong_room' => 'Salah ruang', 'unknown' => 'Tidak dikenal'] as $key => $label) {
            $recap->setCellValue('A' . $row, $label);
            $recap->setCellValue('B' . $row, (int) ($counts[$key] ?? 0));
            $row++;
        }

        $linesSheet = $spreadsheet->createSheet();
        $linesSheet->setTitle('Scan');
        $linesSheet->fromArray([
            ['No CS', 'Nama Aset', 'Ruang', 'Hasil', 'Waktu Scan', 'Petugas'],
        ], null, 'A1');

        $lines = GaAssetOpnameLine::query()
            ->with(['room:id,name', 'asset:id,name,cs_code'])
            ->where('opname_id', $opname->id)
            ->orderByDesc('scanned_at')
            ->get();

        $lineRow = 2;
        foreach ($lines as $line) {
            $linesSheet->fromArray([
                [
                    $line->asset->cs_code ?? $line->scanned_code,
                    $line->asset->name ?? '-',
                    $line->room->name ?? '-',
                    $line->result,
                    $line->scanned_at ? substr((string) $line->scanned_at, 0, 16) : '',
                    $line->scanned_by,
                ],
            ], null, 'A' . $lineRow);
            $lineRow++;
        }

        return $spreadsheet;
    }

    public function exportSoMatrix(int $branchId, int $year): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SO');

        $headers = ['NO CS', 'Jenis Aset', 'Merk'];
        for ($month = 1; $month <= 12; $month++) {
            $headers[] = sprintf('%d-%02d', $year, $month);
        }
        $sheet->fromArray([$headers], null, 'A1');

        $assets = GaAsset::query()
            ->where('is_active', 1)
            ->where('branch_id', $branchId)
            ->whereNotNull('cs_code')
            ->where('cs_code', '!=', '')
            ->orderBy('cs_code')
            ->get(['id', 'cs_code', 'name', 'brand']);

        $foundByAssetMonth = [];
        $opnames = GaAssetOpname::query()
            ->where('branch_id', $branchId)
            ->where('period_year', $year)
            ->where('status', 'closed')
            ->pluck('id', 'period_month');

        if ($opnames->isNotEmpty()) {
            $lines = GaAssetOpnameLine::query()
                ->whereIn('opname_id', $opnames->values())
                ->where('result', 'found')
                ->whereNotNull('asset_id')
                ->get(['asset_id', 'opname_id']);

            $monthByOpname = $opnames->flip();
            foreach ($lines as $line) {
                $month = (int) ($monthByOpname[$line->opname_id] ?? 0);
                if ($month > 0) {
                    $foundByAssetMonth[$line->asset_id][$month] = true;
                }
            }
        }

        $row = 2;
        foreach ($assets as $asset) {
            $line = [$asset->cs_code, $asset->name, $asset->brand];
            for ($month = 1; $month <= 12; $month++) {
                $line[] = isset($foundByAssetMonth[$asset->id][$month]) ? 'D' : '-';
            }
            $sheet->fromArray([$line], null, 'A' . $row);
            $row++;
        }

        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
        if ($row > 2) {
            $sheet->getStyle('A1:' . $lastCol . ($row - 1))->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ]);
        }

        return $spreadsheet;
    }

    public function stream(Spreadsheet $spreadsheet, string $filename): void
    {
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

use App\Models\MasterPelanggan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

class ExportCustomerService
{
    public function __construct()
    {
        ini_set('memory_limit', '4096M');
        ini_set('max_execution_time', '300');
    }
    public function export($id, $type, $status, $typeQt, $category, $duration)
    {
        Log::info('[ExportCustomer] START', [
            'id' => $id,
            'status' => $status,
            'type' => $type,
            'time' => date('Y-m-d H:i:s')
        ]);

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->mergeCells('A1:H1');
            $sheet->setCellValue('A1', 'MASTER PELANGGAN PT INTI SURYA LABORATORIUM');
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(50);

            $sheet->fromArray(['No.', 'ID Pelanggan', 'NPWP', 'Nama Pelanggan', 'Kontak Pelanggan', 'Status', 'Wilayah Pelanggan', 'Sales Penanggung Jawab'], NULL, 'A2');
            $sheet->getStyle('A2:H2')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A4A4A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension(2)->setRowHeight(25);

            $query = MasterPelanggan::select(['id', 'id_pelanggan', 'npwp', 'nama_pelanggan', 'wilayah', 'sales_penanggung_jawab', 'sales_id'])
                ->with(['kontak_pelanggan:pelanggan_id,no_tlp_perusahaan'])
                ->where('sales_id', '!=', 127)
                ->whereNotNull('sales_id')
                ->where('is_active', true);

            if ($status === 'new') {
                if ($typeQt === 'has-qt') {
                    $activeCustomerIds = collect([QuotationKontrakH::class, QuotationNonKontrak::class])->flatMap(fn($model) => $model::select('pelanggan_ID')->whereNotNull('pelanggan_ID')->whereNotIn('flag_status', ['rejected', 'void'])->where('is_active', true)->distinct()->pluck('pelanggan_ID'));

                    $query->whereIn('id_pelanggan', $activeCustomerIds)->whereDoesntHave('order_customer');
                } else {
                    $query->where(
                        fn($q) => $q->whereDoesntHave('quotasiKontrak', fn($q) => $q->whereNotIn('flag_status', ['rejected', 'void'])->where('is_active', true))
                            ->whereDoesntHave('quotasiNonKontrak', fn($q) => $q->whereNotIn('flag_status', ['rejected', 'void'])->where('is_active', true))
                    );
                }
            }

            if ($status === 'ordered') {
                $relation = $category === 'non-contract' ? 'quotasiNonKontrak' : 'quotasiKontrak';

                preg_match('/>=(\d+)/', $type, $matches);
                $duration = $matches[1] ?? null;

                $query->whereHas($relation, function ($q) use ($duration, $relation, $category) {
                    $q->whereHas('orderHeader', function ($oh) use ($duration, $category) {
                        $oh->where('is_active', true);

                        if ($duration) {
                            $oh->where('tanggal_order', $category === 'contract' ? '<=' : '>=', Carbon::now()->subMonths((int) $duration));
                        }
                    });

                    if ($relation === 'quotasiKontrak') {
                        $q->whereHas('latestDetail', fn($qd) => $qd->where('periode_kontrak', '<', date('Y-m')));
                    }
                });
            }

            $row = 3;
            $no = 1;

            Log::info('[ExportCustomer] Start chunking');

            $query->chunkById(5000, function ($customers) use ($status, $sheet, &$row, &$no) {
                foreach ($customers as $customer) {
                    $contacts = $customer->kontak_pelanggan->pluck('no_tlp_perusahaan')
                        ->map(function ($item) {
                            $tel = preg_replace('/[^\d]/', '', $item);

                            if (strpos($tel, '62') === 0) return '0' . substr($tel, 2);
                            if ($tel !== '' && strpos($tel, '0') !== 0) return '0' . $tel;

                            return $tel;
                        })
                        ->filter()
                        ->implode(', ');

                    $sheet->setCellValue("A$row", $no++);
                    $sheet->setCellValue("B$row", $customer->id_pelanggan);
                    $sheet->setCellValue("C$row", $customer->npwp);
                    $sheet->setCellValue("D$row", trim($customer->nama_pelanggan));
                    $sheet->setCellValueExplicit("E$row", $contacts, DataType::TYPE_STRING);
                    $sheet->setCellValue("F$row", $status === 'ordered' ? 'ORDERED' : 'NEW');
                    $sheet->setCellValue("G$row", $customer->wilayah);
                    $sheet->setCellValue("H$row", $customer->sales_penanggung_jawab);
                    $row++;
                }
            });

            Log::info('[ExportCustomer] Finished processing data', ['total_processed' => $no - 1]);

            $lastRow = $row - 1;
            if ($lastRow >= 3) {
                $sheet->getStyle("A2:H$lastRow")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]]]);
                $sheet->getStyle("A3:B$lastRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F3:F$lastRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A2:H$lastRow")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("E3:E$lastRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                foreach (range('A', 'H') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(60);
                $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(40);
                $sheet->getColumnDimension('G')->setAutoSize(false)->setWidth(30);
            }

            $fileName = 'export_pelanggan_' . str_replace('>=', '', $type)   . '_' . date('Y-m-d_H-i-s') . '.xlsx';
            $folderPath = public_path('master_pelanggan');

            if (!File::exists($folderPath)) {
                File::makeDirectory($folderPath, 0777, true);
            }

            (new Xlsx($spreadsheet))->save("$folderPath/$fileName");

            DB::table('export_customers')->where('id', $id)->update([
                'status' => 'completed',
                'filename' => $fileName
            ]);

            Log::info('[ExportCustomer] SUCCESS', ['id' => $id]);
        } catch (\Exception $e) {
            Log::error('[ExportCustomer] ERROR', [
                'id' => $id,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            DB::table('export_customers')->where('id', $id)->update(['status' => 'error']);
        }
    }
}

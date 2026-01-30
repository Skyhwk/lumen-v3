<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapBillingListController extends Controller
{
    public function index(Request $request)
    {
        $data = DB::table('billing_list_header')
            ->select(
                'id',
                'id_pelanggan',
                'nama_pelanggan',
                'nilai_tagihan',
                'terbayar',
                DB::raw('nilai_tagihan - terbayar as nilai_piutang'),
                'is_complete',
                DB::raw("
                    CASE
                        WHEN sales_penanggung_jawab = 'Dedi Wibowo'
                        THEN '-'
                        ELSE sales_penanggung_jawab
                    END as sales_penanggung_jawab
                ")
            )
            ->where('is_complete', $request->is_complete);

        $page = $request->start > 29 ? "lanjut" : "awal";

        return DataTables::of($data)
            ->with([
                'sum_nilai_tagihan'  => function ($query) {
                    return $query->sum('nilai_tagihan');
                },
                'sum_nilai_terbayar' => function ($query) {
                    return $query->sum('terbayar');
                },
                'sum_nilai_piutang'  => function ($query) {
                    $terbayar = $query->sum('terbayar');
                    $tagihan  = $query->sum('nilai_tagihan');
                    $piutang  = $tagihan - $terbayar;

                    return max(0, $piutang);
                },
                'page'               => function () use ($page) {
                    return $page;
                },
            ])
            ->make(true);
    }

    public function getDetail(Request $request)
    {
        $data = DB::table('billing_list_detail')
            ->select(
                'billing_list_detail.id',
                'billing_list_detail.billing_header_id',
                'billing_list_detail.no_invoice',
                'billing_list_detail.no_quotation',
                'billing_list_detail.no_order',
                'billing_list_detail.periode',
                'billing_list_detail.tgl_sampling',
                'billing_list_detail.tgl_invoice',
                'billing_list_detail.tgl_jatuh_tempo',
                'billing_list_detail.nilai_tagihan',
                'billing_list_detail.terbayar',
                DB::raw('billing_list_detail.nilai_tagihan - billing_list_detail.terbayar as nilai_piutang') ,
                'billing_list_detail.is_complete',
                'master_karyawan.nama_lengkap as sales_penanggung_jawab'
            )
            ->leftJoin('master_karyawan', 'master_karyawan.id', '=', 'billing_list_detail.sales_id')
            ->where('billing_header_id', $request->id_header);
        $page = $request->start > 29 ? "lanjut" : "awal";

        return DataTables::of($data)
            ->with([
                'sum_nilai_tagihan'  => function ($query) {
                    return $query->sum('nilai_tagihan');
                },
                'sum_nilai_terbayar' => function ($query) {
                    return $query->sum('terbayar');
                },
                'sum_nilai_piutang'  => function ($query) {
                    $terbayar = $query->sum('terbayar');
                    $tagihan  = $query->sum('nilai_tagihan');
                    $piutang  = $tagihan - $terbayar;

                    return max(0, $piutang);
                },
                'page'               => function () use ($page) {
                    return $page;
                },
            ])->make(true);

    }

    public function export(Request $request) 
    {
        // Validate password
        if ($request->password !== env('EXPORT_DAILYQSD_PW')) {
            return response()->json(['message' => 'Password salah! Akses ditolak.'], 403);
        }

        // 1. Query data
        $query = DB::table('billing_list_header')
                ->select(
                    'id',
                    'id_pelanggan',
                    'nama_pelanggan',
                    'nilai_tagihan',
                    'terbayar',
                    DB::raw('nilai_tagihan - terbayar as nilai_piutang'),
                    'is_complete'
                )
                ->where('is_complete', $request->is_complete);

        // 2. Hitung Summary
        $isComplete    = $request->is_complete;
        $totalTagihan  = (clone $query)->sum('nilai_tagihan');
        $totalTerbayar = (clone $query)->sum('terbayar');
        $totalPiutang  = ($isComplete == 0) ? ($totalTagihan - $totalTerbayar) : 0;

        // 3. Eksekusi Get Data
        $data = $query->get();

        // 4. Proses Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // --- Tentukan Header & Kolom Terakhir ---
        $headers = ['No', 'ID Pelanggan', 'Nama Pelanggan', 'Nilai Tagihan', 'Terbayar'];
        if ($isComplete == 0) {
            $headers[] = 'Sisa Piutang';
        }

        $lastCol = ($isComplete == 0) ? 'F' : 'E';

        // Judul
        $sheet->setCellValue('A1', 'LAPORAN BILLING PELANGGAN');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set Header ke Baris 3
        $sheet->fromArray($headers, null, 'A3');

        // Baris 4: Summary (Total)
        $sheet->setCellValue('D4', $totalTagihan);
        $sheet->setCellValue('E4', $totalTerbayar);
        if ($isComplete == 0) {
            $sheet->setCellValue('F4', $totalPiutang);
        }

        // --- Logika Merge Header (Kolom No, ID, Nama) ---
        $colsToMerge = ['A', 'B', 'C'];
        foreach ($colsToMerge as $col) {
            $sheet->mergeCells("{$col}3:{$col}4");
        }

        // Styling Header & Baris Total
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '343A40'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];
        $sheet->getStyle("A3:{$lastCol}4")->applyFromArray($headerStyle);

        // --- 5. Looping Isi Data ---
        $row = 5;
        foreach ($data as $index => $item) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $item->id_pelanggan);
            $sheet->setCellValue('C' . $row, $item->nama_pelanggan);
            $sheet->setCellValue('D' . $row, $item->nilai_tagihan);
            $sheet->setCellValue('E' . $row, $item->terbayar);
            
            if ($isComplete == 0) {
                $sheet->setCellValue('F' . $row, $item->nilai_piutang);
                if ($item->nilai_piutang > 0) {
                    $sheet->getStyle('F' . $row)->getFont()->getColor()->setRGB('FF0000');
                }
            }
            
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
        }

        // --- 6. Final Formatting ---
        $lastDataRow = $row - 1;

        // Auto-size kolom
        foreach (range('A', $lastCol) as $colID) {
            $sheet->getColumnDimension($colID)->setAutoSize(true);
        }

        // Format Angka Ribuan
        $sheet->getStyle("D4:{$lastCol}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');

        // Freeze Panes
        $sheet->freezePane('A5');

        // --- Output File ---
        $writer = new Xlsx($spreadsheet);
        $statusText = $isComplete == 1 ? 'Selesai' : 'Belum_Selesai';
        $fileName = "Rekapitulasi_Billing_{$statusText}_" . date('d-m-Y_His') . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

}

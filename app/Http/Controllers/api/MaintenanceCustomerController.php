<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\OrderHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\DataTables\DataTables;

class MaintenanceCustomerController extends Controller
{
    public function index(Request $request)
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6)->format('Y-m-d');

        $periodeSix = Carbon::now()->subMonths(6)->format('m-Y');

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $lastOrder = DB::table('order_header')
            ->select(
                DB::raw('MAX(id) as id'),
                'id_pelanggan',
                DB::raw('MAX(tanggal_order) as tanggal_order')
            )
            ->where('is_active', true)
            ->groupBy('id_pelanggan');

        $orderHeaderNonKontrak = OrderHeader::joinSub($lastOrder, 'last_order', function ($join) {
            $join->on('order_header.id_pelanggan', '=', 'last_order.id_pelanggan')
                ->on('order_header.tanggal_order', '=', 'last_order.tanggal_order')
                ->on('order_header.id', '=', 'last_order.id');
        })
            ->join('master_karyawan', 'order_header.sales_id', '=', 'master_karyawan.id')
            ->select(
                'order_header.id',
                'order_header.id_pelanggan',
                'order_header.tanggal_order',
                'order_header.nama_perusahaan',
                'order_header.konsultan',
                'order_header.no_tlp_perusahaan',
                'order_header.nama_pic_order',
                'order_header.no_pic_order',
                'order_header.sales_id',
                'order_header.no_document',
                'master_karyawan.nama_lengkap',
                DB::raw("'non_kontrak' as tipe_order"),
                DB::raw("order_header.tanggal_order as tanggal_sort") // Kolom untuk sorting
            )
            ->where('order_header.no_document', 'LIKE', '%QT/%')
            ->where('order_header.tanggal_order', '<=', $sixMonthsAgo);

        $orderHeaderKontrak = OrderHeader::joinSub($lastOrder, 'last_order', function ($join) {
            $join->on('order_header.id_pelanggan', '=', 'last_order.id_pelanggan')
                ->on('order_header.tanggal_order', '=', 'last_order.tanggal_order')
                ->on('order_header.id', '=', 'last_order.id');
        })
            ->join('master_karyawan', 'order_header.sales_id', '=', 'master_karyawan.id')
            ->join('request_quotation_kontrak_H', 'order_header.no_document', '=', 'request_quotation_kontrak_H.no_document')
            ->select(
                'order_header.id',
                'order_header.id_pelanggan',
                'request_quotation_kontrak_H.periode_kontrak_akhir as tanggal_order',
                'order_header.nama_perusahaan',
                'order_header.konsultan',
                'order_header.no_tlp_perusahaan',
                'order_header.nama_pic_order',
                'order_header.no_pic_order',
                'order_header.sales_id',
                'order_header.no_document',
                'master_karyawan.nama_lengkap',
                DB::raw("'kontrak' as tipe_order"),
                // Konversi MM-YYYY ke YYYY-MM-DD untuk sorting
                DB::raw("STR_TO_DATE(CONCAT('01-', request_quotation_kontrak_H.periode_kontrak_akhir), '%d-%m-%Y') as tanggal_sort")
            )
            ->where('order_header.no_document', 'LIKE', '%QTC/%')
            ->whereRaw("STR_TO_DATE(CONCAT('01-', request_quotation_kontrak_H.periode_kontrak_akhir), '%d-%m-%Y') <= ?", 
                [Carbon::now()->subMonths(6)->endOfMonth()->format('Y-m-d')]);

        $orderHeader = $orderHeaderNonKontrak->union($orderHeaderKontrak);



        switch ($jabatan) {
            case 24:
            case 148:
                $orderHeader->where('sales_id', $this->user_id);
                break;

            case 21:
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('id')
                    ->toArray();
                array_push($bawahan, $this->user_id);

                $orderHeader->whereIn('sales_id', $bawahan);
                break;
        }

        $orderHeader = $orderHeader->orderBy('tanggal_sort', 'desc');


        return DataTables::of($orderHeader)->make(true);
    }

    public function getDetail(Request $request)
    {
        $data = OrderHeader::where('id_pelanggan', $request->id_pelanggan)
            ->join('master_karyawan', 'sales_id', '=', 'master_karyawan.id')
            ->where('nama_perusahaan', $request->nama_perusahaan)
            ->where('order_header.is_active', true)
            ->orderBy('tanggal_order', 'desc')
            ->get();

        return DataTables::of($data)->make(true);

    }

   public function export(Request $request)
    {
        // 2. Query Data (sama seperti di method index)
        $sixMonthsAgo = Carbon::now()->subMonths(6)->format('Y-m-d');
        $jabatan      = $request->attributes->get('user')->karyawan->id_jabatan;

        $lastOrder = DB::table('order_header')
            ->select(
                DB::raw('MAX(id) as id'),
                'id_pelanggan',
                DB::raw('MAX(tanggal_order) as tanggal_order')
            )
            ->where('is_active', true)
            ->groupBy('id_pelanggan');

        $orderHeaderNonKontrak = OrderHeader::joinSub($lastOrder, 'last_order', function ($join) {
            $join->on('order_header.id_pelanggan', '=', 'last_order.id_pelanggan')
                ->on('order_header.tanggal_order', '=', 'last_order.tanggal_order')
                ->on('order_header.id', '=', 'last_order.id');
        })
            ->join('master_karyawan', 'order_header.sales_id', '=', 'master_karyawan.id')
            ->select(
                'order_header.id',
                'order_header.id_pelanggan',
                'order_header.tanggal_order',
                'order_header.nama_perusahaan',
                'order_header.konsultan',
                'order_header.no_tlp_perusahaan',
                'order_header.nama_pic_order',
                'order_header.no_pic_order',
                'order_header.sales_id',
                'order_header.no_document',
                'master_karyawan.nama_lengkap',
                DB::raw("'non_kontrak' as tipe_order"),
                DB::raw("order_header.tanggal_order as tanggal_sort") // Kolom untuk sorting
            )
            ->where('order_header.no_document', 'LIKE', '%QT/%')
            ->where('order_header.tanggal_order', '<=', $sixMonthsAgo);

        $orderHeaderKontrak = OrderHeader::joinSub($lastOrder, 'last_order', function ($join) {
            $join->on('order_header.id_pelanggan', '=', 'last_order.id_pelanggan')
                ->on('order_header.tanggal_order', '=', 'last_order.tanggal_order')
                ->on('order_header.id', '=', 'last_order.id');
        })
            ->join('master_karyawan', 'order_header.sales_id', '=', 'master_karyawan.id')
            ->join('request_quotation_kontrak_H', 'order_header.no_document', '=', 'request_quotation_kontrak_H.no_document')
            ->select(
                'order_header.id',
                'order_header.id_pelanggan',
                'request_quotation_kontrak_H.periode_kontrak_akhir as tanggal_order',
                'order_header.nama_perusahaan',
                'order_header.konsultan',
                'order_header.no_tlp_perusahaan',
                'order_header.nama_pic_order',
                'order_header.no_pic_order',
                'order_header.sales_id',
                'order_header.no_document',
                'master_karyawan.nama_lengkap',
                DB::raw("'kontrak' as tipe_order"),
                // Konversi MM-YYYY ke YYYY-MM-DD untuk sorting
                DB::raw("STR_TO_DATE(CONCAT('01-', request_quotation_kontrak_H.periode_kontrak_akhir), '%d-%m-%Y') as tanggal_sort")
            )
            ->where('order_header.no_document', 'LIKE', '%QTC/%')
            ->whereRaw("STR_TO_DATE(CONCAT('01-', request_quotation_kontrak_H.periode_kontrak_akhir), '%d-%m-%Y') <= ?", 
                [Carbon::now()->subMonths(6)->endOfMonth()->format('Y-m-d')]);

        $orderHeader = $orderHeaderNonKontrak->union($orderHeaderKontrak);

        // Filter berdasarkan jabatan
        switch ($jabatan) {
            case 24:
            case 148:
                $orderHeader->where('sales_id', $this->user_id);
                break;

            case 21:
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('id')
                    ->toArray();
                array_push($bawahan, $this->user_id);

                $orderHeader->whereIn('sales_id', $bawahan);
                break;
        }

        // Sekarang bisa orderBy menggunakan kolom tanggal_sort
        $data = $orderHeader->orderBy('tanggal_sort', 'desc')->get();

        // 3. Setup Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        // Helper Map Bulan Indonesia
        $bulanIndo = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05'   => 'Mei', '06'      => 'Juni',
            '07' => 'Juli', '08'    => 'Agustus', '09'  => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];

        // --- TITLE ---
        $title = "LAPORAN MAINTENANCE CUSTOMER - " . strtoupper(Carbon::now()->locale('id')->isoFormat('MMMM YYYY'));

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // --- HEADERS ---
        $headers = [
            'No',
            'ID Pelanggan',
            'Nama Perusahaan',
            'Konsultan',
            'No. Telp Perusahaan',
            'Nama PIC Order',
            'No. PIC Order',
            'Tanggal Order Terakhir',
            'No Quotation',
            'Sales Penanggung Jawab',
        ];
        $sheet->fromArray($headers, null, 'A2');

        // Styling Header
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '343A40']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A2:J2')->applyFromArray($headerStyle);

        // --- ISI DATA ---
        $row = 3;
        $no  = 1;

        // Helper Format Tanggal Indonesia (support 2 format: YYYY-MM-DD dan MM-YYYY)
        $formatDateIndo = function ($dateStr) use ($bulanIndo) {
            if (!$dateStr || $dateStr === '-') {
                return '';
            }

            try {
                // Cek apakah format MM-YYYY (kontrak)
                if (preg_match('/^\d{2}-\d{4}$/', $dateStr)) {
                    $parts = explode('-', $dateStr);
                    $m = $parts[0];
                    $y = $parts[1];
                    return ($bulanIndo[$m] ?? '') . " {$y}";
                }
                
                // Format YYYY-MM-DD (non-kontrak)
                $parts = explode('-', $dateStr);
                if (count($parts) === 3) {
                    $y = $parts[0];
                    $m = $parts[1];
                    $d = $parts[2];
                    return "{$d} " . ($bulanIndo[$m] ?? '') . " {$y}";
                }
                
                return $dateStr;
            } catch (\Exception $e) {
                return $dateStr;
            }
        };

        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $item->id_pelanggan);
            $sheet->setCellValue('C' . $row, $item->nama_perusahaan);
            $sheet->setCellValue('D' . $row, $item->konsultan ?? '-');
            $sheet->setCellValue('E' . $row, $item->no_tlp_perusahaan ?? '-');
            $sheet->setCellValue('F' . $row, $item->nama_pic_order ?? '-');
            $sheet->setCellValue('G' . $row, $item->no_pic_order ?? '-');
            $sheet->setCellValue('H' . $row, $formatDateIndo($item->tanggal_order));
            $sheet->setCellValue('I' . $row, $item->no_document ?? '-');
            $sheet->setCellValue('J' . $row, $item->nama_lengkap);

            // Hitung selisih bulan menggunakan tanggal_sort yang sudah dalam format standard
            try {
                $selisihBulan = Carbon::parse($item->tanggal_sort)->diffInMonths(Carbon::now());
            } catch (\Exception $e) {
                $selisihBulan = 0; // Default jika parsing gagal
            }

            // Styling warna untuk highlight customer yang sudah lama tidak order
            if ($selisihBulan >= 9) {
                // Merah untuk >= 9 bulan
                $sheet->getStyle('A' . $row . ':J' . $row)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('F8D7DA');
            } elseif ($selisihBulan >= 6) {
                // Kuning untuk >= 6 bulan
                $sheet->getStyle('A' . $row . ':J' . $row)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFF3CD');
            }

            $row++;
        }

        // --- FINAL FORMATTING ---
        $lastRow = $row - 1;

        // 1. Auto Width
        foreach (range('A', 'J') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // 2. Alignment & Wrap
        $sheet->getStyle('A3:J' . $lastRow)->getAlignment()->setWrapText(false);
        $sheet->getStyle('A3:J' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        // 3. Border
        $sheet->getStyle('A2:J' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // 4. Freeze Pane (Header tetap terlihat saat scroll)
        $sheet->freezePane('A3');

        // Output
        $writer   = new Xlsx($spreadsheet);
        $fileName = "Maintenance_Customer_" . date('Ymd_His') . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

}

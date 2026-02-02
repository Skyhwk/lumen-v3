<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DataLimbah;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapDataLimbahController extends Controller
{
    public function index(Request $request)
    {
        $data = DataLimbah::with(['order']);
        $data = $data->orderBy('id', 'desc');
        return DataTables::of($data)
            ->filter(function ($query) use ($request) {
                if ($request->has('start_date') || $request->has('end_date')) {
                    $dateStart = $request->start_date;
                    $dateEnd = $request->end_date;
                    if (!empty($dateStart) && !empty($dateEnd)) {
                        $query->whereBetween('created_at', [$dateStart, $dateEnd]);
                    } elseif (!empty($dateStart)) {
                        $query->where('created_at', '>=', $dateStart);
                    } elseif (!empty($dateEnd)) {
                        $query->where('created_at', '<=', $dateEnd);
                    }
                }

                if($request->has('status_limbah') && !empty($request->status_limbah)){
                    $status_limbah = $request->status_limbah;
                    $query->where('status_limbah', $status_limbah);
                }
            })
            ->make(true);
    }

    public function exportExcel(Request $request)
    {
        $dateStart = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::today()->startOfDay();

        $dateEnd = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::today()->endOfDay();

        $query = DataLimbah::with(['order', 'karyawan']);

        if ($request->has('start_date') || $request->has('end_date')) {
            if (!empty($request->start_date) && !empty($request->end_date)) {
                $query->whereBetween('created_at', [$dateStart, $dateEnd]);
            } elseif (!empty($request->start_date)) {
                $query->where('created_at', '>=', $dateStart);
            } elseif (!empty($request->end_date)) {
                $query->where('created_at', '<=', $dateEnd);
            }
        }

        if($request->has('status_limbah') && !empty($request->status_limbah)){
            $status_limbah = $request->status_limbah;
            $query->where('status_limbah', $status_limbah);
        }

        $data = $query->orderBy('created_at', 'desc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $bulanIndo = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember'
        ];

        // --- TITLE ---
        $periodeLabel = '';
        if ($request->has('start_date') || $request->has('end_date')) {
            $periodeLabel = ' (' . ($request->start_date ? $dateStart->format('d/m/Y') : '') . ' - ' . ($request->end_date ? $dateEnd->format('d/m/Y') : '') . ')';
        }
        if($request->has('status_limbah') && !empty($request->status_limbah)){
            $periodeLabel .= ' - Status: ' . $request->status_limbah;
        }
        $title = "REKAPITULASI DATA LIMBAH{$periodeLabel}";

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // --- HEADERS ---
        $headers = [
            'No',
            'No Sample',
            'Tanggal Jadwal',
            'Tanggal Terima',
            'Diinput Oleh',
            'Diinput Tanggal',
            'Status Limbah'
        ];
        $sheet->fromArray($headers, null, 'A3');

        // Styling Header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '343A40']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $sheet->getStyle('A3:G3')->applyFromArray($headerStyle);

        // --- ISI DATA ---
        $row = 4;
        $no = 1;

        // Helper Format Tanggal Indo (YYYY-MM-DD -> DD MMMM YYYY)
        $formatDateIndo = function ($dateStr) use ($bulanIndo) {
            if (!$dateStr || $dateStr === '-') return '';
            try {
                // Asumsi format DB: YYYY-MM-DD
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

            $sheet->setCellValue('B' . $row, $item->no_sampel);
            $sheet->setCellValue('C' . $row, $formatDateIndo($item->order ? $item->order->tanggal_sampling : ''));
            $sheet->setCellValue('D' . $row, $formatDateIndo($item->order ? $item->order->tanggal_terima : ''));
            $sheet->setCellValue('E' . $row, $item->created_by || '');
            $sheet->setCellValue('F' . $row, $formatDateIndo($item->created_at ? $item->created_at->format('Y-m-d') : ''));
            $sheet->setCellValue('G' . $row, $item->status_limbah);

            $row++;
        }

        // --- FINAL FORMATTING ---
        $lastRow = $row - 1;

        // 1. Auto Width
        foreach (range('A', 'G') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // 2. Alignment & Wrap
        $sheet->getStyle('A4:G' . $lastRow)->getAlignment()->setWrapText(false);
        $sheet->getStyle('A4:G' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        // 3. Border
        $sheet->getStyle('A4:G' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Output
        $writer = new Xlsx($spreadsheet);
        $fileName = "Rekap_Data_Limbah_" . date('Ymd_His') . ".xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
}
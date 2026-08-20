<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use Mpdf;
use DataTables;
use Carbon\Carbon;

use App\Models\OvertimeRequest;
use App\Models\OvertimeRequestMembers;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapLemburController extends Controller
{
    public function index()
    {
        $rekap = OvertimeRequest::on('intilab_apps')
            ->leftJoin('intilab_apps.overtime_request_members as m', 'm.no_document', '=', 'overtime_requests.no_document')
            ->select('overtime_requests.start_date as tanggal', DB::raw('COUNT(m.employee_id) as jumlah'))
            ->whereNotNull('overtime_requests.approved_finance_by')
            ->where('overtime_requests.is_active', true)
            ->groupBy('overtime_requests.start_date')
            ->orderByDesc('overtime_requests.start_date');

        return DataTables::of($rekap)->make(true);
    }

    private function getRekap($date)
    {
        $divisi = MasterDivisi::where('is_active', true)->get();

        $rekap = [];
        foreach ($divisi as $item) {
            $detail = OvertimeRequestMembers::on('intilab_apps')
                ->leftJoin('intilab_apps.overtime_requests as h', 'h.no_document', '=', 'overtime_request_members.no_document')
                ->where('h.department_id', $item->id)
                ->where('h.start_date', $date)
                ->whereNotNull('h.approved_finance_by')
                ->where('h.is_active', true)
                ->select(
                    'overtime_request_members.employee_id',
                    'h.start_time as jam_mulai',
                    'h.end_time as jam_selesai',
                    'h.description as keterangan'
                )
                ->get();

            if ($detail->isNotEmpty()) {
                $karyawan = MasterKaryawan::whereIn('id', $detail->pluck('employee_id')->unique()->toArray())->get();

                $detail->map(function ($row) use ($karyawan) {
                    $row->karyawan = $karyawan->where('id', $row->employee_id)->first();
                });

                $detail = $detail->sortBy(fn($row) => $row->karyawan->nama_lengkap ?? '')->values();

                $rekap[] = [
                    'kode_divisi' => $item->kode_divisi,
                    'nama_divisi' => $item->nama_divisi,
                    'detail' => $detail->toArray()
                ];
            }
        }

        return $rekap;
    }

    public function detail(Request $request)
    {
        return response()->json(['data' => $this->getRekap($request->tanggal), 'message' => 'Data retrieved successfully'], 200);
    }

    public function exportExcel(Request $request)
    {
        Carbon::setLocale('id');

        $data = $this->getRekap($request->tanggal);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set Header Judul
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'Rekap Lembur Tanggal: ' . Carbon::parse($request->tanggal)->translatedFormat('d F Y'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Set Header Kolom
        $headers = ['No', 'NIK', 'Nama Karyawan', 'Jam Mulai', 'Jam Selesai', 'Keterangan'];
        $sheet->fromArray($headers, NULL, 'A3');

        // Styling Header Kolom
        $sheet->getStyle('A3:F3')->getFont()->setBold(true);
        $sheet->getStyle('A3:F3')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCCCCC'); // Abu-abu

        $rowIdx = 4;
        $no = 1;

        foreach ($data as $divisi) {
            // Row Divider Divisi
            $sheet->mergeCells("A{$rowIdx}:F{$rowIdx}");
            $sheet->setCellValue("A{$rowIdx}", "({$divisi['kode_divisi']}) {$divisi['nama_divisi']}");
            $sheet->getStyle("A{$rowIdx}")->getFont()->setBold(true)->getColor()->setARGB('FF0000FF'); // Biru
            $sheet->getStyle("A{$rowIdx}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFF0F0'); // Agak pink dikit

            $rowIdx++;

            // group detail by keterangan
            $grouped = [];
            foreach ($divisi['detail'] as $row) {
                $key = $row['keterangan'] ?? '-';
                $grouped[$key][] = $row;
            }

            foreach ($grouped as $keterangan => $rows) {
                $startRow = $rowIdx;
                $countRow = count($rows);

                foreach ($rows as $i => $row) {
                    $sheet->setCellValue("A{$rowIdx}", $no++);
                    $sheet->setCellValueExplicit(
                        "B{$rowIdx}",
                        $row['karyawan']['nik_karyawan'] ?? '-',
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                    );
                    $sheet->setCellValue("C{$rowIdx}", $row['karyawan']['nama_lengkap'] ?? '-');
                    $sheet->setCellValue("D{$rowIdx}", $row['jam_mulai']);
                    $sheet->setCellValue("E{$rowIdx}", $row['jam_selesai']);

                    // keterangan cuma ditulis sekali
                    if ($i === 0) {
                        $sheet->setCellValue("F{$rowIdx}", $keterangan);
                    }

                    $rowIdx++;
                }

                // merge keterangan kalo lebih dari 1 row
                if ($countRow > 1) {
                    $endRow = $startRow + $countRow - 1;
                    $sheet->mergeCells("F{$startRow}:F{$endRow}");
                    $sheet->getStyle("F{$startRow}:F{$endRow}")
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }
            }
        }

        // Auto Width Column (Biar rapi otomatis)
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border seluruh tabel
        $lastRow = $rowIdx - 1;
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];
        $sheet->getStyle("A3:F{$lastRow}")->applyFromArray($styleArray);

        $writer = new Xlsx($spreadsheet);
        $fileName = "Rekap_Lembur_'.$request->tanggal.'.xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    public function generatePdf(Request $request)
    {
        $mpdf = new Mpdf();

        $mpdf->WriteHTML(view('pdf.rekap_lembur', [
            'data' => $this->getRekap($request->tanggal),
            'tanggal' => $request->tanggal
        ])->render());

        $filename = 'Rekap_Lembur_' . $request->tanggal . '.pdf';
        $path = public_path('rekap_lembur');

        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }

        $mpdf->Output($path . '/' . $filename, \Mpdf\Output\Destination::FILE);

        return response()->json(['data' => $filename, 'message' => 'PDF generated successfully'], 200);
    }
}

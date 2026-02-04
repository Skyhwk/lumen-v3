<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use Mpdf;
use DataTables;
use Carbon\Carbon;

use App\Models\FormDetail;
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
        $rekap = FormDetail::on('intilab_apps')
            ->select('tanggal_mulai as tanggal', DB::raw('count(user_id) as jumlah'))
            ->whereNotNull('approved_finance_by')
            ->where('is_active', true)
            ->groupBy('tanggal_mulai')
            ->orderByDesc('tanggal_mulai');

        return DataTables::of($rekap)->make(true);
    }

    private function getRekap($date)
    {
        $divisi = MasterDivisi::where('is_active', true)->get();

        $rekap = [];
        foreach ($divisi as $item) {
            $detail = FormDetail::on('intilab_apps')
                ->where('department_id', $item->id)
                ->where('tanggal_mulai', $date)
                ->whereNotNull('approved_finance_by')
                ->where('is_active', true)
                ->get();

            if ($detail->isNotEmpty()) {
                $karyawan = MasterKaryawan::whereIn('id', $detail->pluck('user_id')->unique()->toArray())->get();
                $detail->map(function ($item) use ($karyawan) {
                    $item->karyawan = $karyawan->where('id', $item->user_id)->first();
                });

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

            foreach ($divisi['detail'] as $row) {
                $sheet->setCellValue("A{$rowIdx}", $no++);
                $sheet->setCellValueExplicit("B{$rowIdx}", $row['karyawan']['nik_karyawan'] ?? '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING); // Biar 0 di depan gak ilang
                $sheet->setCellValue("C{$rowIdx}", $row['karyawan']['nama_lengkap'] ?? '-');
                $sheet->setCellValue("D{$rowIdx}", $row['jam_mulai']);
                $sheet->setCellValue("E{$rowIdx}", $row['jam_selesai']);
                $sheet->setCellValue("F{$rowIdx}", $row['keterangan']);
                $rowIdx++;
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

        if (!file_exists($path)) mkdir($path, 0777, true);

        $mpdf->Output($path . '/' . $filename, \Mpdf\Output\Destination::FILE);

        return response()->json(['data' => $filename, 'message' => 'PDF generated successfully'], 200);
    }
}

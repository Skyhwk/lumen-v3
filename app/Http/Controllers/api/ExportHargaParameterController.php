<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

Carbon::setLocale('id');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

use App\Models\MasterRegulasi;
use App\Models\HargaParameter;
use App\Models\OrderDetail;

class ExportHargaParameterController extends Controller
{
    private function getData($tahun)
    {
        $masterRegulasi = MasterRegulasi::with('bakumutu')->where('is_active', true)->orderBy('id_kategori')->get();

        $data = [];
        $no = 1;
        foreach ($masterRegulasi as $regulasi) {
            Log::info("Memproses regulasi ($no/" . count($masterRegulasi) . "): " . $regulasi->deskripsi);
            $hargaBulan = array_fill(1, 12, 0);

            foreach ($regulasi->bakumutu as $bakumutu) {
                for ($bulan = 1; $bulan <= 12; $bulan++) {
                    $masterHargaParameter = HargaParameter::where('id_parameter', $bakumutu->id_parameter);

                    if ($masterHargaParameter->count() > 1) {
                        $tanggalCek = Carbon::create($tahun, $bulan, 1)->endOfMonth();

                        $hargaRecord = HargaParameter::where('id_parameter', $bakumutu->id_parameter)
                            ->where('created_at', '<=', $tanggalCek)
                            ->latest()
                            ->first();

                        if (!$hargaRecord) {
                            $hargaRecord = HargaParameter::where('id_parameter', $bakumutu->id_parameter)
                                ->orderBy('created_at', 'asc')
                                ->first();
                        }

                        $harga = $hargaRecord->harga ?: 0;
                    } else {
                        $harga = $masterHargaParameter->first()->harga ?: 0;
                    }

                    $hargaBulan[$bulan] += $harga;
                }
            }

            $data[] = [
                $no++,
                $regulasi->nama_kategori,
                $regulasi->peraturan,
                $regulasi->bakumutu->count(),
                'Rp.',
                $hargaBulan[1],
                $hargaBulan[2],
                $hargaBulan[3],
                $hargaBulan[4],
                $hargaBulan[5],
                $hargaBulan[6],
                $hargaBulan[7],
                $hargaBulan[8],
                $hargaBulan[9],
                $hargaBulan[10],
                $hargaBulan[11],
                $hargaBulan[12],
            ];
        }

        return $data;
    }

    public function exportExcel(Request $request)
    {
        $currentTimestamp = Carbon::now();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->mergeCells('A1:A2');
        $sheet->setCellValue('A1', 'No.');
        $sheet->mergeCells('B1:B2');
        $sheet->setCellValue('B1', 'Kategori');
        $sheet->mergeCells('C1:C2');
        $sheet->setCellValue('C1', 'Regulasi');
        $sheet->mergeCells('D1:D2');
        $sheet->setCellValue('D1', 'Parameter');
        $sheet->mergeCells('E1:E2');
        $sheet->setCellValue('E1', 'Harga');

        $sheet->mergeCells('F1:Q1');
        $sheet->setCellValue('F1', "Tahun $request->tahun");

        $sheet->fromArray(['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'], NULL, 'F2');

        $headerStyle = $sheet->getStyle('A1:Q2');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $data = $this->getData($request->tahun);

        $startRow = 3;
        $sheet->fromArray($data, NULL, 'A' . $startRow);

        $lastRow = $startRow + count($data) - 1;
        $noColumnStyle = $sheet->getStyle('A' . $startRow . ':A' . $lastRow);
        $noColumnStyle->getFont()->setBold(true);
        $noColumnStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $lastRow = $startRow + count($data) - 1;
        $noColumnStyle = $sheet->getStyle('D' . $startRow . ':D' . $lastRow);
        $noColumnStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // $sheet->getColumnDimension('A')->setWidth(5);
        // $sheet->getColumnDimension('B')->setAutoSize(true);
        // $sheet->getColumnDimension('C')->setAutoSize(true);
        // $sheet->getColumnDimension('D')->setWidth(12);
        // $sheet->getColumnDimension('E')->setWidth(15);

        foreach (range('A', 'Q') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $sheet->getStyle('A1:Q' . $sheet->getHighestRow())
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '000000'],
                    ],
                ],
            ]);

        $fileName = 'HARGA_PARAMETER_' . str_replace(['-', ' ', ':'], '', $currentTimestamp) . '.xlsx';

        $path = public_path('report');
        if (!file_exists($path)) mkdir($path, 0777, true);

        $writer = new Xlsx($spreadsheet);
        $writer->save("$path/$fileName");

        return response()->json(['message' => "File berhasil disimpan : $fileName"], 200);
    }

    // Semi kelar
    // private function getDataPenjualan($tahun)
    // {
    //     $masterRegulasi = MasterRegulasi::where('is_active', true)->orderBy('id_kategori')->limit(50)->get();

    //     $data = [];
    //     $no = 1;
    //     foreach ($masterRegulasi as $regulasi) {
    //         $hargaBulan = array_fill(1, 12, 0);

    //         for ($bulan = 1; $bulan <= 12; $bulan++) {
    //             $orderDetail = OrderDetail::with('orderHeader')
    //                 ->whereRaw('JSON_VALID(regulasi)')
    //                 ->whereRaw(
    //                     "JSON_SEARCH(regulasi, 'one', ?, NULL, '$[*]') IS NOT NULL",
    //                     [(string)$regulasi->id . '-%']
    //                 )
    //                 ->whereHas('orderHeader', function ($query) use ($tahun, $bulan) {
    //                     $query->whereYear('tanggal_order', $tahun)
    //                         ->whereMonth('tanggal_order', $bulan)
    //                         ->where('is_active', true);
    //                 })
    //                 ->where('is_active', true)
    //                 ->get();

    //             foreach ($orderDetail as $od) {
    //                 $parameters = json_decode($od->parameter, true);
    //                 foreach ($parameters as $parameter) {
    //                     $hargaParameter = HargaParameter::where('id_parameter', explode(';', $parameter)[0])
    //                         ->where('is_active', true)
    //                         ->latest()
    //                         ->first();

    //                     if ($hargaParameter) {
    //                         $hargaBulan[$bulan] += $hargaParameter->harga ?: 0;
    //                     }
    //                 }
    //             }
    //         }

    //         $data[] = [
    //             $no++,
    //             $regulasi->nama_kategori,
    //             $regulasi->peraturan,
    //             $regulasi->bakumutu->count(),
    //             'Rp.',
    //             $hargaBulan[1],
    //             $hargaBulan[2],
    //             $hargaBulan[3],
    //             $hargaBulan[4],
    //             $hargaBulan[5],
    //             $hargaBulan[6],
    //             $hargaBulan[7],
    //             $hargaBulan[8],
    //             $hargaBulan[9],
    //             $hargaBulan[10],
    //             $hargaBulan[11],
    //             $hargaBulan[12],
    //         ];
    //     }

    //     return $data;
    // }

    private function getDataPenjualan($tahun)
    {
        Log::info("\n\n\n\n=== Memulai proses ekspor penjualan untuk tahun $tahun ===\n\n\n");

        $masterRegulasi = MasterRegulasi::with('bakumutu.activeHargaParameter')->where('is_active', true)->orderBy('id_kategori')->get();

        $data = [];
        $no = 1;
        foreach ($masterRegulasi as $regulasi) {
            Log::info("Memproses regulasi ($no/" . count($masterRegulasi) . "): " . $regulasi->deskripsi);

            $hargaBulan = array_fill(1, 12, 0);

            $hargaJual = $regulasi->bakumutu->sum(fn($baku) => optional($baku->activeHargaParameter)->harga ?: 0);

            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $countOrderDetail = OrderDetail::with('orderHeader')
                    ->whereHas('orderHeader', function ($query) use ($tahun, $bulan) {
                        $query->whereYear('tanggal_order', $tahun)
                            ->whereMonth('tanggal_order', $bulan)
                            ->where('is_active', true);
                    })
                    ->where('is_active', true)
                    ->whereRaw('JSON_VALID(regulasi)')
                    ->whereRaw(
                        "JSON_SEARCH(regulasi, 'one', ?, NULL, '$[*]') IS NOT NULL",
                        [(string)$regulasi->id . '-%']
                    )
                    ->count();

                $hargaBulan[$bulan] += $countOrderDetail;
            }

            $data[] = [
                $no++,
                $regulasi->nama_kategori,
                $regulasi->peraturan,
                $regulasi->bakumutu->count(),
                $hargaJual,
                $hargaBulan[1],
                $hargaBulan[2],
                $hargaBulan[3],
                $hargaBulan[4],
                $hargaBulan[5],
                $hargaBulan[6],
                $hargaBulan[7],
                $hargaBulan[8],
                $hargaBulan[9],
                $hargaBulan[10],
                $hargaBulan[11],
                $hargaBulan[12],
            ];
        }

        Log::info("\n\n\n=== Proses ekspor penjualan untuk tahun $tahun selesai ===\n\n\n");

        return $data;
    }

    public function exportPenjualanExcel(Request $request)
    {
        $currentTimestamp = Carbon::now();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->mergeCells('A1:A2');
        $sheet->setCellValue('A1', 'No.');
        $sheet->mergeCells('B1:B2');
        $sheet->setCellValue('B1', 'Kategori');
        $sheet->mergeCells('C1:C2');
        $sheet->setCellValue('C1', 'Regulasi');
        $sheet->mergeCells('D1:D2');
        $sheet->setCellValue('D1', 'Parameter');
        $sheet->mergeCells('E1:E2');
        $sheet->setCellValue('E1', 'Harga Jual');

        $sheet->mergeCells('F1:Q1');
        $sheet->setCellValue('F1', "Tahun $request->tahun");

        $sheet->fromArray(['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'], NULL, 'F2');

        $headerStyle = $sheet->getStyle('A1:Q2');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $data = $this->getDataPenjualan($request->tahun);

        $startRow = 3;
        $sheet->fromArray($data, NULL, 'A' . $startRow);

        $lastRow = $startRow + count($data) - 1;
        $noColumnStyle = $sheet->getStyle('A' . $startRow . ':A' . $lastRow);
        $noColumnStyle->getFont()->setBold(true);
        $noColumnStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $lastRow = $startRow + count($data) - 1;
        $noColumnStyle = $sheet->getStyle('D' . $startRow . ':D' . $lastRow);
        $noColumnStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // $sheet->getColumnDimension('A')->setWidth(5);
        // $sheet->getColumnDimension('B')->setAutoSize(true);
        // $sheet->getColumnDimension('C')->setAutoSize(true);
        // $sheet->getColumnDimension('D')->setWidth(12);
        // $sheet->getColumnDimension('E')->setWidth(15);

        foreach (range('A', 'Q') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $sheet->getStyle('A1:Q' . $sheet->getHighestRow())
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '000000'],
                    ],
                ],
            ]);

        $fileName = 'REKAP_PENJUALAN' . str_replace(['-', ' ', ':'], '', $currentTimestamp) . '.xlsx';

        $path = public_path('report/order');
        if (!file_exists($path)) mkdir($path, 0777, true);

        $writer = new Xlsx($spreadsheet);
        $writer->save("$path/$fileName");

        return response()->json(['message' => "File berhasil disimpan : $fileName"], 200);
    }
}

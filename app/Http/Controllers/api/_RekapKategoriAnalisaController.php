<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\MasterSubKategori;
use Illuminate\Http\Request;
use App\Models\OrderDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapKategoriAnalisaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $sub_kategoris = MasterSubKategori::where('is_active', true)
                ->select('id', 'nama_sub_kategori')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->id . "-" . str_replace('/', '-', $item->nama_sub_kategori) => $item->nama_sub_kategori];
                })
                ->toArray();

            // Ambil semua sales yang muncul dalam periode yang diminta
            $salesData = OrderDetail::query()
                ->whereRaw('order_detail.tanggal_sampling >= ? AND order_detail.tanggal_sampling <= ?', [date('Y-m-01', strtotime($request->tanggal_awal)), date('Y-m-t', strtotime($request->tanggal_akhir))])
                ->where('order_detail.is_active', true)
                ->leftJoin('order_header as oh', 'oh.id', '=', 'order_detail.id_order_header')
                ->select(
                    'order_detail.kategori_3',
                    DB::raw("COALESCE(oh.sales_id) as sales_id"),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('order_detail.kategori_3', DB::raw("COALESCE(oh.sales_id)"))
                ->get();

            // Ambil semua sales_id unik dari hasil query
            $allSalesIds = $salesData->pluck('sales_id')->unique()->filter()->values();

            // Ambil data karyawan untuk sales_id yang ditemukan
            $allSales = MasterKaryawan::whereIn('id', $allSalesIds)
                ->pluck('nama_lengkap', 'id')
                ->toArray();

            // Fallback untuk sales yang tidak ditemukan di master
            foreach ($allSalesIds as $salesId) {
                if (!isset($allSales[$salesId])) {
                    $allSales[$salesId] = "Sales " . $salesId;
                }
            }

            // Format hasil
            $result = [];

            foreach ($sub_kategoris as $kategoriKey => $kategoriName) {
                $salesCounts = [];

                // Inisialisasi semua sales dengan nilai 0
                foreach ($allSales as $salesId => $salesName) {
                    $salesCounts[$salesName] = 0;
                }

                // Isi dengan data yang ada
                $categoryData = $salesData->where('kategori_3', $kategoriKey);
                foreach ($categoryData as $item) {
                    if (isset($allSales[$item->sales_id])) {
                        $salesName = $allSales[$item->sales_id];
                        $salesCounts[$salesName] = $item->total;
                    }
                }

                $result[$kategoriName] = [
                    'values' => $salesCounts
                ];
            }

            $listSales = array_values($allSales);

            // Urutkan result berdasarkan index (nama kategori) A-Z
            ksort($result);

            return response()->json([
                'sales' => $listSales,
                'data' => (object) $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function exportExcel(Request $request)
    {
        try {
            $sub_kategoris = MasterSubKategori::where('is_active', true)
                ->select('id', 'nama_sub_kategori')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->id . "-" . str_replace('/', '-', $item->nama_sub_kategori) => $item->nama_sub_kategori];
                })
                ->toArray();

            // Ambil semua sales yang muncul dalam periode yang diminta
            $salesData = OrderDetail::query()
                ->whereRaw('order_detail.tanggal_sampling >= ? AND order_detail.tanggal_sampling <= ?', [date('Y-m-01', strtotime($request->tanggal_awal)), date('Y-m-t', strtotime($request->tanggal_akhir))])
                ->where('order_detail.is_active', true)
                ->leftJoin('request_quotation_kontrak_H as rqkh', function ($join) {
                    $join->on('rqkh.no_document', '=', 'order_detail.no_quotation')
                        ->whereNotNull('order_detail.periode');
                })
                ->leftJoin('request_quotation as rq', function ($join) {
                    $join->on('rq.no_document', '=', 'order_detail.no_quotation')
                        ->whereNull('order_detail.periode');
                })
                ->select(
                    'order_detail.kategori_3',
                    DB::raw("COALESCE(rqkh.sales_id, rq.sales_id) as sales_id"),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('order_detail.kategori_3', DB::raw("COALESCE(rqkh.sales_id, rq.sales_id)"))
                ->get();

            // Ambil semua sales_id unik dari hasil query
            $allSalesIds = $salesData->pluck('sales_id')->unique()->filter()->values();

            // Ambil data karyawan untuk sales_id yang ditemukan
            $allSales = MasterKaryawan::whereIn('user_id', $allSalesIds)
                ->pluck('nama_lengkap', 'user_id')
                ->toArray();

            // Fallback untuk sales yang tidak ditemukan di master
            foreach ($allSalesIds as $salesId) {
                if (!isset($allSales[$salesId])) {
                    $allSales[$salesId] = "Sales " . $salesId;
                }
            }

            // Format hasil
            $result = [];

            foreach ($sub_kategoris as $kategoriKey => $kategoriName) {
                $salesCounts = [];

                // Inisialisasi semua sales dengan nilai 0
                foreach ($allSales as $salesId => $salesName) {
                    $salesCounts[$salesName] = 0;
                }

                // Isi dengan data yang ada
                $categoryData = $salesData->where('kategori_3', $kategoriKey);
                foreach ($categoryData as $item) {
                    if (isset($allSales[$item->sales_id])) {
                        $salesName = $allSales[$item->sales_id];
                        $salesCounts[$salesName] = $item->total;
                    }
                }

                $result[$kategoriName] = [
                    'values' => $salesCounts
                ];
            }

            $listSales = array_values($allSales);

            // Format data untuk Excel dengan struktur yang diminta
            $excelData = [];

            $tanggalAwalFormatted = \Carbon\Carbon::parse($request->tanggal_awal)->locale('id')->format('F Y');
            $tanggalAkhirFormatted = \Carbon\Carbon::parse($request->tanggal_akhir)->locale('id')->format('F Y');
            $tanggalAwalFormattedParts = explode(' ', $tanggalAwalFormatted);
            $tanggalAkhirFormattedParts = explode(' ', $tanggalAkhirFormatted);
            $isSameYear = $tanggalAwalFormattedParts[1] === $tanggalAkhirFormattedParts[1];

            $dateRange = $isSameYear ? explode(' ', $tanggalAwalFormatted)[0] . ' - ' . $tanggalAkhirFormatted : $tanggalAwalFormatted . ' - ' . $tanggalAkhirFormatted;

            // Baris 2: Nama Sales (Header kolom)
            $headerRow1 = [$dateRange];
            foreach ($allSales as $salesName) {
                $headerRow1[] = $salesName;
            }
            $excelData[] = $headerRow1;

            // Baris data: Sub Kategori dan nilai per sales
            foreach ($sub_kategoris as $kategoriKey => $kategoriName) {
                $row = [$kategoriName];

                foreach ($allSales as $salesId => $salesName) {
                    $count = 0;
                    $categoryData = $salesData->where('kategori_3', $kategoriKey)
                        ->where('sales_id', $salesId)
                        ->first();
                    $count = $categoryData ? $categoryData->total : 0;
                    $row[] = $count;
                }

                $excelData[] = $row;
            }
            // Urutkan excelData dari key ke 1, dan di dalam key 1 gunakan key ke 0 sebagai acuan urutan A-Z
            // Ambil header
            $header = $excelData[0];

            // Ambil data (tanpa header)
            $dataRows = array_slice($excelData, 1);

            // Urutkan dataRows berdasarkan kolom pertama (nama sub kategori) A-Z
            usort($dataRows, function($a, $b) {
                return strcmp($a[0], $b[0]);
            });

            // Gabungkan kembali header dan data yang sudah diurutkan
            $excelData = array_merge([$header], $dataRows);
            // Generate Excel file
            $document = $this->generateExcelFile($excelData, $request->tanggal_awal, $request->tanggal_akhir, true);
            return response()->json([
                'url' => $document['url'],
                'filename' => $document['filename'],
                'message' => 'Success export'
            ],200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    private function generateExcelFile(array $data, string $startDate, string $endDate, bool $saveToPublic = false)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Kategori Analisa');

        // Isi data
        $sheet->fromArray($data, null, 'A1');

        // === Styling ===
        $lastColumn = Coordinate::stringFromColumnIndex(count($data[0]));
        $totalRowIndex = count($data);

        // Header utama
        // $sheet->mergeCells("B1:{$lastColumn}1");
        $sheet->getStyle('A1:'.$lastColumn.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('B1:'.$lastColumn.'1')->getFont()->setBold(true)->setSize(12);

        // Header sales (baris 1)
        // $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDEBF7');

        // Baris total
        // $sheet->getStyle("A{$totalRowIndex}:{$lastColumn}{$totalRowIndex}")->getFont()->setBold(true);
        // $sheet->getStyle("A{$totalRowIndex}:{$lastColumn}{$totalRowIndex}")->getFill()
        //     ->setFillType(Fill::FILL_SOLID)
            // ->getStartColor()->setRGB('FFF2CC');

        // Border
        $sheet->getStyle("A1:{$lastColumn}{$totalRowIndex}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Autosize kolom
        foreach (range(1, count($data[0])) as $i) {
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Kolom kategori lebih lebar
        $sheet->getColumnDimension('A')->setWidth(30);

        // Nama file
        $fileName = "Rekap_Kategori_Analisa_{$startDate}_to_{$endDate}.xlsx";

        $writer = new Xlsx($spreadsheet);

        if ($saveToPublic) {
            // Simpan ke public/rekap-kategori-analisa/
            $path = public_path("rekap-kategori-analisa/{$fileName}");

            // Pastikan folder ada
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $writer->save($path);

            // Return path atau url
            return [
                'url' => env('APP_URL') . ("/public/rekap-kategori-analisa/{$fileName}"),
                'filename' => $fileName
            ];
        }

        // Versi download (tanpa php://output → pakai response()->download)
        // $tempPath = storage_path("app/{$fileName}");
        // $writer->save($tempPath);

        // return response()->download($tempPath)->deleteFileAfterSend(true);
    }
}

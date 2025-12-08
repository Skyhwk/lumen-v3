<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\MasterSubKategori;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KuotaKategoriSamplingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $subKategoriNames = MasterSubKategori::where('is_active', true)
                ->pluck('nama_sub_kategori')
                ->toArray();

            $subKategoriMap = [];
            foreach ($subKategoriNames as $name) {
                $normalized                  = $this->normalizeKategoriName($name);
                $subKategoriMap[$normalized] = true;
            }

            $subKategoriNames = array_keys($subKategoriMap);
            $jadwal           = Jadwal::where('is_active', true)
                ->whereNotNull('no_quotation')
                ->where('status', '1')
                ->select('no_quotation', 'kategori', 'durasi', 'tanggal')
                ->when($request->start_date && $request->end_date, function ($q) use ($request) {
                    $q->whereBetween('tanggal', [$request->start_date, $request->end_date]);
                })
                ->when($request->start_date && ! $request->end_date, function ($q) use ($request) {
                    $q->where('tanggal', '>=', $request->start_date);
                })
                ->when(! $request->start_date && $request->end_date, function ($q) use ($request) {
                    $q->where('tanggal', '<=', $request->end_date);
                })
                ->distinct()
                ->get();
            $kategoriCount       = [];
            $kategoriQuots       = [];
            $kategoriQuotsAll    = [];
            $kategoriDurasiCount = [];

            foreach ($jadwal as $value) {
                $kategoriArray = json_decode($value->kategori, true);

                if (! is_array($kategoriArray)) {
                    continue;
                }

                $durasi = (int) $value->durasi;
                foreach ($kategoriArray as $kat) {
                    $cleanKat = $this->normalizeKategoriName($kat);

                    $parts     = preg_split('/\s*[-–—]\s*/u', $cleanKat, 2);
                    $rawBase   = $parts[0] ?? '';
                    $no_sampel = $parts[1] ?? '';

                    $baseKategori = $this->normalizeKategoriName($rawBase);

                    if ($baseKategori === '') {
                        continue;
                    }

                    if (! isset($kategoriCount[$baseKategori])) {
                        $kategoriCount[$baseKategori] = 0;
                    }
                    $kategoriCount[$baseKategori]++;

                    if (! isset($kategoriQuots[$baseKategori])) {
                        $kategoriQuots[$baseKategori] = [];
                    }
                    if (! isset($kategoriQuots[$baseKategori][$durasi])) {
                        $kategoriQuots[$baseKategori][$durasi] = [];
                    }
                    $kategoriQuots[$baseKategori][$durasi][$value->no_quotation] = true;

                    if (! isset($kategoriQuotsAll[$baseKategori])) {
                        $kategoriQuotsAll[$baseKategori] = [];
                    }
                    $kategoriQuotsAll[$baseKategori][$value->no_quotation] = true;

                    if (! isset($kategoriDurasiCount[$baseKategori])) {
                        $kategoriDurasiCount[$baseKategori] = [];
                    }
                    if (! isset($kategoriDurasiCount[$baseKategori][$durasi])) {
                        $kategoriDurasiCount[$baseKategori][$durasi] = 0;
                    }
                    $kategoriDurasiCount[$baseKategori][$durasi]++;
                }
            }

            $linked   = [];
            $unlinked = [];

            foreach ($kategoriCount as $namaKategori => $jumlah) {
                $noQuots = $kategoriQuots[$namaKategori] ?? [];

                if (isset($subKategoriMap[$namaKategori])) {
                    $linked[$namaKategori] = [
                        'jumlah' => $jumlah,
                        'status' => 'linked',
                    ];
                } else {
                    $unlinked[$namaKategori] = [
                        'jumlah' => $jumlah,
                        'status' => 'unlinked',
                    ];
                }
            }

            foreach ($subKategoriNames as $namaSub) {
                $namaSubNorm = $this->normalizeKategoriName($namaSub);

                if (! isset($linked[$namaSubNorm])) {
                    $linked[$namaSubNorm] = [
                        'jumlah' => 0,
                        'status' => 'linked',
                    ];
                }
            }

            $final = [];
            foreach (array_merge($linked, $unlinked) as $namaKategori => $info) {
                $final[$namaKategori] = [
                    'jumlah' => $info['jumlah'],
                    'status' => $info['status'],
                    // 'no_quotation' => array_values(array_keys($kategoriQuotsAll[$namaKategori] ?? [])),
                ];
            }

            $sesaat   = [];
            $delapan  = [];
            $duaEmpat = [];

            foreach ($final as $namaKategori => $data) {
                $durasiData = $kategoriDurasiCount[$namaKategori] ?? [];

                $baseTotal = [
                    'jumlah' => $data['jumlah'] ?? 0,
                    'status' => $data['status'] ?? 'linked',
                    // 'no_quotation' => array_values(array_keys($kategoriQuotsAll[$namaKategori] ?? [])),
                ];

                $countSesaat = $durasiData[0] ?? 0;
                if ($countSesaat > 0) {
                    $sesaat[$namaKategori] = [
                        'jumlah' => $countSesaat,
                        'status' => $data['status'],
                        // 'no_quotation' => array_values(array_keys($kategoriQuots[$namaKategori][0] ?? [])),
                    ];
                }

                $count8 = $durasiData[1] ?? 0;
                if ($count8 > 0) {
                    $delapan[$namaKategori] = [
                        'jumlah' => $count8,
                        'status' => $data['status'],
                        // 'no_quotation' => array_values(array_keys($kategoriQuots[$namaKategori][1] ?? [])),
                    ];
                }

                $count24 = 0;
                $quot24  = [];
                for ($d = 2; $d <= 9; $d++) {
                    $count24 += $durasiData[$d] ?? 0;
                    if (! empty($kategoriQuots[$namaKategori][$d])) {
                        // merge quotation keys
                        $quot24 = array_merge($quot24, array_keys($kategoriQuots[$namaKategori][$d]));
                    }
                }
                $quot24 = array_values(array_unique($quot24));
                if ($count24 > 0) {
                    $duaEmpat[$namaKategori] = [
                        'jumlah' => $count24,
                        'status' => $data['status'],
                        // 'no_quotation' => $quot24,
                    ];
                }
            }

            return response()->json([
                'data' => [
                    'total'  => $final,
                    'sesaat' => $sesaat,
                    '8_jam'  => $delapan,
                    '24_jam' => $duaEmpat,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }

    public function exportExcel(Request $request)
    {
        try {
            // Ambil sub kategori aktif
            $subKategoriNames = MasterSubKategori::where('is_active', true)
                ->pluck('nama_sub_kategori')
                ->toArray();

            $subKategoriMap = [];
            foreach ($subKategoriNames as $name) {
                $normalized                  = $this->normalizeKategoriName($name);
                $subKategoriMap[$normalized] = true;
            }

            $subKategoriNames = array_keys($subKategoriMap);

            // Query jadwal sesuai dengan index
            $jadwal = Jadwal::where('is_active', true)
                ->whereNotNull('no_quotation')
                ->where('status', '1')
                ->select('no_quotation', 'kategori', 'durasi', 'tanggal')
                ->when($request->start_date && $request->end_date, function ($q) use ($request) {
                    $q->whereBetween('tanggal', [$request->start_date, $request->end_date]);
                })
                ->when($request->start_date && ! $request->end_date, function ($q) use ($request) {
                    $q->where('tanggal', '>=', $request->start_date);
                })
                ->when(! $request->start_date && $request->end_date, function ($q) use ($request) {
                    $q->where('tanggal', '<=', $request->end_date);
                })
                ->distinct()
                ->get();

            $kategoriCount       = [];
            $kategoriQuots       = [];
            $kategoriQuotsAll    = [];
            $kategoriDurasiCount = [];

            // Proses data jadwal
            foreach ($jadwal as $value) {
                $kategoriArray = json_decode($value->kategori, true);

                if (! is_array($kategoriArray)) {
                    continue;
                }

                $durasi = (int) $value->durasi;
                foreach ($kategoriArray as $kat) {
                    $cleanKat = $this->normalizeKategoriName($kat);

                    $parts     = preg_split('/\s*[-–—]\s*/u', $cleanKat, 2);
                    $rawBase   = $parts[0] ?? '';
                    $no_sampel = $parts[1] ?? '';

                    $baseKategori = $this->normalizeKategoriName($rawBase);

                    if ($baseKategori === '') {
                        continue;
                    }

                    if (! isset($kategoriCount[$baseKategori])) {
                        $kategoriCount[$baseKategori] = 0;
                    }
                    $kategoriCount[$baseKategori]++;

                    if (! isset($kategoriQuots[$baseKategori])) {
                        $kategoriQuots[$baseKategori] = [];
                    }
                    if (! isset($kategoriQuots[$baseKategori][$durasi])) {
                        $kategoriQuots[$baseKategori][$durasi] = [];
                    }
                    $kategoriQuots[$baseKategori][$durasi][$value->no_quotation] = true;

                    if (! isset($kategoriQuotsAll[$baseKategori])) {
                        $kategoriQuotsAll[$baseKategori] = [];
                    }
                    $kategoriQuotsAll[$baseKategori][$value->no_quotation] = true;

                    if (! isset($kategoriDurasiCount[$baseKategori])) {
                        $kategoriDurasiCount[$baseKategori] = [];
                    }
                    if (! isset($kategoriDurasiCount[$baseKategori][$durasi])) {
                        $kategoriDurasiCount[$baseKategori][$durasi] = 0;
                    }
                    $kategoriDurasiCount[$baseKategori][$durasi]++;
                }
            }

            // Pisahkan linked dan unlinked
            $linked   = [];
            $unlinked = [];

            foreach ($kategoriCount as $namaKategori => $jumlah) {
                if (isset($subKategoriMap[$namaKategori])) {
                    $linked[$namaKategori] = [
                        'jumlah' => $jumlah,
                    ];
                } else {
                    $unlinked[$namaKategori] = [
                        'jumlah' => $jumlah,
                    ];
                }
            }

            // Tambahkan sub kategori yang tidak ada datanya
            foreach ($subKategoriNames as $namaSub) {
                $namaSubNorm = $this->normalizeKategoriName($namaSub);

                if (! isset($linked[$namaSubNorm])) {
                    $linked[$namaSubNorm] = [
                        'jumlah' => 0,
                    ];
                }
            }

            // Gabungkan data
            $final = [];
            foreach (array_merge($linked, $unlinked) as $namaKategori => $info) {
                $final[$namaKategori] = [
                    'jumlah' => $info['jumlah'],
                ];
            }

            // Kelompokkan berdasarkan durasi
            $sesaat   = [];
            $delapan  = [];
            $duaEmpat = [];

            foreach ($final as $namaKategori => $data) {
                $durasiData = $kategoriDurasiCount[$namaKategori] ?? [];

                $countSesaat = $durasiData[0] ?? 0;
                if ($countSesaat > 0) {
                    $sesaat[$namaKategori] = [
                        'jumlah' => $countSesaat,
                    ];
                }

                $count8 = $durasiData[1] ?? 0;
                if ($count8 > 0) {
                    $delapan[$namaKategori] = [
                        'jumlah' => $count8,
                    ];
                }

                $count24 = 0;
                for ($d = 2; $d <= 9; $d++) {
                    $count24 += $durasiData[$d] ?? 0;
                }
                if ($count24 > 0) {
                    $duaEmpat[$namaKategori] = [
                        'jumlah' => $count24,
                    ];
                }
            }

            // Format data untuk Excel
            $excelData = [];

            // Header
            $tanggalAwalFormatted       = \Carbon\Carbon::parse($request->start_date)->locale('id')->format('F Y');
            $tanggalAkhirFormatted      = \Carbon\Carbon::parse($request->end_date)->locale('id')->format('F Y');
            $tanggalAwalFormattedParts  = explode(' ', $tanggalAwalFormatted);
            $tanggalAkhirFormattedParts = explode(' ', $tanggalAkhirFormatted);
            $isSameYear                 = $tanggalAwalFormattedParts[1] === $tanggalAkhirFormattedParts[1];

            $dateRange = $isSameYear
                ? explode(' ', $tanggalAwalFormatted)[0] . ' - ' . $tanggalAkhirFormatted
                : $tanggalAwalFormatted . ' - ' . $tanggalAkhirFormatted;

            // Header row
            $headerRow   = ['Kategori', 'Sesaat', '8 Jam', '24 Jam', 'Total'];
            $excelData[] = $headerRow;

            // Data rows - urutkan A-Z
            $sortedFinal = $final;
            ksort($sortedFinal);

            foreach ($sortedFinal as $namaKategori => $data) {
                $row = [
                    $namaKategori,
                    $sesaat[$namaKategori]['jumlah'] ?? 0,
                    $delapan[$namaKategori]['jumlah'] ?? 0,
                    $duaEmpat[$namaKategori]['jumlah'] ?? 0,
                    $data['jumlah'],
                ];
                $excelData[] = $row;
            }

            // Generate Excel file
            $document = $this->generateExcelFile(
                $excelData,
                $request->start_date,
                $request->end_date,
                true
            );

            return response()->json([
                'url'      => $document['url'],
                'filename' => $document['filename'],
                'message'  => 'Success export',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }

    private function generateExcelFile(array $data, string $startDate, string $endDate, bool $saveToPublic = false)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kuota Kategori Sampling');

        // Isi data
        $sheet->fromArray($data, null, 'A1');

        // === Styling ===
        $lastColumn    = Coordinate::stringFromColumnIndex(count($data[0]));
        $totalRowIndex = count($data);

        // Header styling
        $sheet->getStyle('A1:' . $lastColumn . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDEBF7');

        // Border untuk semua data
        $sheet->getStyle("A1:{$lastColumn}{$totalRowIndex}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Autosize kolom
        foreach (range(1, count($data[0])) as $i) {
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Kolom kategori lebih lebar
        $sheet->getColumnDimension('A')->setWidth(35);

        // Alignment untuk kolom angka (tengah)
        for ($row = 2; $row <= $totalRowIndex; $row++) {
            $sheet->getStyle("B{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }


        // Nama file
        $fileName = "Kuota_Kategori_Sampling_{$startDate}_to_{$endDate}.xlsx";
        $writer   = new Xlsx($spreadsheet);

        if ($saveToPublic) {
            // Simpan ke public/kuota-kategori-sampling/
            $path = public_path("kuota-kategori-sampling/{$fileName}");

            // Pastikan folder ada
            if (! file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $writer->save($path);

            // Return URL
            return [
                'url' => env('APP_URL') . "/public/kuota-kategori-sampling/{$fileName}",
                'filename' => $fileName,
            ];
        }
    }
    private function normalizeKategoriName(string $name): string
    {
        $name = str_replace(["\xc2\xa0", "\xA0"], ' ', $name);

        $name = trim($name);

        $name = preg_replace('/\s+/u', ' ', $name);

        return $name;
    }
}

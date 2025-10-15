<?php

namespace App\Services;

use Mpdf\Mpdf;
use Carbon\Carbon;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManagerStatic as Image;

use App\Models\OrderDetail;

class RenderPermintaanDokumentasiSampling
{
    private function processAndWatermarkImage($originalFileName, $outputPath, array $watermarkData)
    {
        $originalPath = public_path("dokumentasi/sampling/$originalFileName");

        if (!$originalFileName || !file_exists($originalPath)) {
            Log::warning("File gambar sumber tidak ditemukan: " . $originalPath);
            return false;
        }

        try {
            $img = Image::make($originalPath);

            // --- WATERMARK UNTUK KIRI ATAS (Nama PT & Order ID) ---
            if (!empty($watermarkData['header'])) {
                $img->text($watermarkData['header'], 15, 15, function ($font) { // X=15, Y=15 dari kiri atas
                    $font->file(base_path('vendor/mpdf/mpdf/ttfonts/Roboto-Regular.ttf'));
                    $font->size(18);
                    $font->color('#FFFFFF');
                    $font->align('left');
                    $font->valign('top');
                });
            }

            // --- WATERMARK UNTUK KIRI BAWAH (Sampling Date & Report By) ---
            if (!empty($watermarkData['footerLeft'])) {
                $img->text($watermarkData['footerLeft'], 15, $img->height() - 70, function ($font) { // 70px dari bawah
                    $font->file(base_path('vendor/mpdf/mpdf/ttfonts/Roboto-Regular.ttf'));
                    $font->size(18);
                    $font->color('#FFFFFF');
                    $font->align('left');
                    $font->valign('bottom');
                });
            }

            // --- WATERMARK UNTUK KANAN BAWAH (Koordinat) ---
            if (!empty($watermarkData['footerRight'])) {
                $img->text($watermarkData['footerRight'], $img->width() - 15, $img->height() - 15, function ($font) { // 15px dari kanan & bawah
                    $font->file(base_path('vendor/mpdf/mpdf/ttfonts/Roboto-Regular.ttf'));
                    $font->size(18);
                    $font->color('#FFFFFF');
                    $font->align('right');
                    $font->valign('bottom');
                });
            }

            $img->encode('webp', 80);
            $img->save($outputPath);

            return true;
        } catch (\Exception $e) {
            Log::error("Gagal membuat watermark: " . $e->getMessage());
            return false;
        }
    }

    public function renderPdf($permintaanDokumentasiSampling, $qr)
    {
        DB::beginTransaction();
        try {
            $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr;

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 3, // 30mm not pixel
                'margin_bottom' => 3, // 30mm not pixel
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            ]);

            $pdf->setFooter([
                'odd' => [
                    'C' => [
                        'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ],
                    'R' => [
                        'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ],
                    'L' => [
                        'content' => '' . $qr_img . '',
                        'font-size' => 4,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ],
                    'line' => -1,
                ]
            ]);

            $data = $permintaanDokumentasiSampling;
            $detail = OrderDetail::withAnyDataLapangan()
                ->where('no_order', $data->no_order)
                ->where('is_active', true)
                ->get();

            foreach ($detail as $item) {
                foreach ($item->any_data_lapangan as $dataLapangan) {
                    $noOrder = $data->no_order;
                    $noSampelClean = str_replace('/', '_', $item->no_sampel);
                    $randomId = Str::random(12);

                    $outputDir = public_path("request/temp_img/{$noOrder}");
                    File::makeDirectory($outputDir, 0755, true, true);

                    $watermarkData = [
                        'header' => "$data->nama_perusahaan\nOrder ID " . $data->no_order,
                        'footerLeft' => "Sampling Date : " . Carbon::parse($dataLapangan->created_at)->format('j F Y H:i') . "\nReport By : $dataLapangan->created_by",
                        'footerRight' => optional($dataLapangan)->titik_koordinat, // Asumsi ini ada di $dataLapangan
                    ];

                    // Proses gambar 'foto_lokasi_sampel'
                    $outputNameLokasi = "kegiatan_sampling-{$noSampelClean}_{$randomId}.webp";
                    $outputPathLokasi = "{$outputDir}/{$outputNameLokasi}";
                    if ($this->processAndWatermarkImage(optional($dataLapangan)->foto_lokasi_sampel, $outputPathLokasi, $watermarkData)) {
                        $dataLapangan->webp_path_lokasi = "request/temp_img/{$noOrder}/{$outputNameLokasi}";
                    }

                    // Proses gambar 'foto_kondisi_sampel'
                    $outputNameKondisi = "kondisi_sampel-{$noSampelClean}_{$randomId}.webp";
                    $outputPathKondisi = "{$outputDir}/{$outputNameKondisi}";
                    if ($this->processAndWatermarkImage(optional($dataLapangan)->foto_kondisi_sampel, $outputPathKondisi, $watermarkData)) {
                        $dataLapangan->webp_path_kondisi = "request/temp_img/{$noOrder}/{$outputNameKondisi}";
                    }

                    // Proses gambar 'foto_lainnya'
                    $outputNameKondisi = "lainnya-{$noSampelClean}_{$randomId}.webp";
                    $outputPathKondisi = "{$outputDir}/{$outputNameKondisi}";
                    if ($this->processAndWatermarkImage(optional($dataLapangan)->foto_lainnya, $outputPathKondisi, $watermarkData)) {
                        $dataLapangan->webp_path_kondisi = "request/temp_img/{$noOrder}/{$outputNameKondisi}";
                    }
                }
            }

            // $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png');
            // $pdf->showWatermarkImage = true;
            // $pdf->watermarkImageAlpha = 0.1;

            $pdf->WriteHTML(view('dokumentasi-sampling', [
                'data' => $data,
                'detail' => $detail
            ])->render());

            $fileName = str_replace("/", "_", $permintaanDokumentasiSampling->no_document) . '.pdf';

            $dir = public_path("request/dokumentasi_sampling");

            if (!file_exists($dir)) mkdir($dir, 0755, true);

            $filePath = public_path('request/dokumentasi_sampling/' . $fileName);
            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);

            $permintaanDokumentasiSampling->filename = $fileName;
            $permintaanDokumentasiSampling->save();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}

// buat ngetes pake gambar dari produksi
// private function processAndWatermarkImage($originalFileName, $outputPath, array $watermarkData)
//     {
//         $url = "https://apps.intilab.com/v3/public/dokumentasi/sampling/$originalFileName";

//         if (!$originalFileName) {
//             Log::warning("Nama file kosong");
//             return false;
//         }

//         try {
//             $response = Http::get($url);

//             if (!$response->successful()) {
//                 Log::warning("Gagal ambil gambar dari URL: $url");
//                 return false;
//             }

//             // Simpen dulu ke temporary file
//             $tempPath = storage_path('app/temp_' . uniqid() . '.jpg');
//             file_put_contents($tempPath, $response->body());

//             $img = Image::make($tempPath);
//             // $img = Image::make($originalPath);

//             // --- WATERMARK UNTUK KIRI ATAS (Nama PT & Order ID) ---
//             if (!empty($watermarkData['header'])) {
//                 $img->text($watermarkData['header'], 15, 15, function ($font) { // X=15, Y=15 dari kiri atas
//                     $font->file(base_path('vendor/mpdf/mpdf/ttfonts/Roboto-Regular.ttf'));
//                     $font->size(18);
//                     $font->color('#FFFFFF');
//                     $font->align('left');
//                     $font->valign('top');
//                 });
//             }

//             // --- WATERMARK UNTUK KIRI BAWAH (Sampling Date & Report By) ---
//             if (!empty($watermarkData['footerLeft'])) {
//                 $img->text($watermarkData['footerLeft'], 15, $img->height() - 70, function ($font) { // 70px dari bawah
//                     $font->file(base_path('vendor/mpdf/mpdf/ttfonts/Roboto-Regular.ttf'));
//                     $font->size(18);
//                     $font->color('#FFFFFF');
//                     $font->align('left');
//                     $font->valign('bottom');
//                 });
//             }

//             // --- WATERMARK UNTUK KANAN BAWAH (Koordinat) ---
//             if (!empty($watermarkData['footerRight'])) {
//                 $img->text($watermarkData['footerRight'], $img->width() - 15, $img->height() - 15, function ($font) { // 15px dari kanan & bawah
//                     $font->file(base_path('vendor/mpdf/mpdf/ttfonts/Roboto-Regular.ttf'));
//                     $font->size(18);
//                     $font->color('#FFFFFF');
//                     $font->align('right');
//                     $font->valign('bottom');
//                 });
//             }

//             $img->encode('webp', 80);
//             $img->save($outputPath);

//             return true;
//         } catch (\Exception $e) {
//             Log::error("Gagal membuat watermark: " . $e->getMessage());
//             return false;
//         }
//     }
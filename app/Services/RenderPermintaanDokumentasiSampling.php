<?php

namespace App\Services;

use Mpdf\Mpdf;
use Carbon\Carbon;

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

                    $outputDir = public_path("request/temp_img/{$noOrder}");
                    File::makeDirectory($outputDir, 0755, true, true);

                    $watermarkData = [
                        'header' => "$data->nama_perusahaan\nOrder ID " . $data->no_order,
                        'footerLeft' => "Sampling Date : " . Carbon::parse($dataLapangan->created_at)->format('j F Y H:i') . "\nReport By : $dataLapangan->created_by",
                        'footerRight' => optional($dataLapangan)->titik_koordinat, // Asumsi ini ada di $dataLapangan
                    ];

                    // Proses gambar 'foto_lokasi_sampel'
                    $outputNameLokasi = "{$noSampelClean}-kegiatan_sampling.webp";
                    $outputPathLokasi = "{$outputDir}/{$outputNameLokasi}";
                    if ($this->processAndWatermarkImage(optional($dataLapangan)->foto_lokasi_sampel, $outputPathLokasi, $watermarkData)) {
                        $dataLapangan->webp_path_lokasi = "request/temp_img/{$noOrder}/{$outputNameLokasi}";
                    }

                    // Proses gambar 'foto_kondisi_sampel'
                    $outputNameKondisi = "{$noSampelClean}-kondisi_sampel.webp";
                    $outputPathKondisi = "{$outputDir}/{$outputNameKondisi}";
                    if ($this->processAndWatermarkImage(optional($dataLapangan)->foto_kondisi_sampel, $outputPathKondisi, $watermarkData)) {
                        $dataLapangan->webp_path_kondisi = "request/temp_img/{$noOrder}/{$outputNameKondisi}";
                    }

                    // Proses gambar 'foto_lainnya'
                    $outputNameKondisi = "{$noSampelClean}-lainnya.webp";
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

<?php

namespace App\Services;

use Mpdf;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManagerStatic as Image;
use App\Models\OrderDetail;

class RenderPermintaanDokumentasiSampling
{
    private function processAndWatermarkImage($originalFileName, $outputPath, array $watermarkData)
    {
        // Log::info("Memproses gambar: $originalFileName");
        // // // DIRECT TO PRODUCTION
        // $url = "https://apps.intilab.com/v3/public/dokumentasi/sampling/$originalFileName";

        // if (!$originalFileName) {
        //     Log::warning("Nama file kosong");
        //     return false;
        // }

        // try {
        //     $response = Http::get($url);

        //     if (!$response->successful()) {
        //         Log::warning("Gagal ambil gambar dari URL: $url");
        //         return false;
        //     }

        //     // Simpen dulu ke temporary file
        //     $tempPath = storage_path('app/temp_' . uniqid() . '.jpg');
        //     file_put_contents($tempPath, $response->body());

        //     $img = Image::make($tempPath);

        // LOCAL
        $originalPath = public_path('dokumentasi/sampling/' . $originalFileName);

        if (!$originalFileName || !File::exists($originalPath)) {
            Log::warning("Image not found: $originalPath");
            return false;
        }

        try {
            $img = Image::make($originalPath);
            $maxWidth = 1500;
            $img->resize($maxWidth, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            $tempCompressed = tempnam(sys_get_temp_dir(), 'cmp_');
            $tempCompressedJpg = $tempCompressed . '.jpg';
            $tempCompressedWebp = $tempCompressed . '.webp';
            $quality = 80;
            $maxSizeKB = 500;
            $supportsWebp = imagetypes() & IMG_WEBP;

            if ($supportsWebp) {
                do {
                    $img->encode('webp', $quality)->save($tempCompressedWebp);
                    $sizeKB = filesize($tempCompressedWebp) / 1024;
                    $quality -= 10;
                } while ($sizeKB > $maxSizeKB && $quality > 30);
                $finalImg = Image::make($tempCompressedWebp);
                unlink($tempCompressedWebp);
            } else {
                do {
                    $img->encode('jpg', $quality)->save($tempCompressedJpg);
                    $sizeKB = filesize($tempCompressedJpg) / 1024;
                    $quality -= 10;
                } while ($sizeKB > $maxSizeKB && $quality > 30);
                $finalImg = Image::make($tempCompressedJpg);
                unlink($tempCompressedJpg);
            }

            $width = $finalImg->width();
            $height = $finalImg->height();

            // Rumus: rata-rata panjang sisi / pembagi (semakin kecil pembagi -> font makin besar)
            $baseFontSize = round((($width + $height) / 2) / 40);

            // Batasi supaya tidak terlalu kecil atau besar
            $baseFontSize = max(18, min($baseFontSize, 48));

            // --- WATERMARK HEADER ---
            if (!empty($watermarkData['header'])) {
                $finalImg->text($watermarkData['header'], 20, 40, function ($font) use ($baseFontSize) {
                    $font->file(base_path('vendor/mpdf/mpdf/ttfonts/Roboto-Regular.ttf'));
                    $font->size($baseFontSize + 6);
                    $font->color('#FFFFFF');
                    $font->align('left');
                    $font->valign('top');
                });
            }

            // --- WATERMARK FOOTER LEFT ---
            if (!empty($watermarkData['footerLeft'])) {
                $finalImg->text($watermarkData['footerLeft'], 20, $finalImg->height() - 80, function ($font) use ($baseFontSize) {
                    $font->file(base_path('vendor/mpdf/mpdf/ttfonts/Roboto-Regular.ttf'));
                    $font->size($baseFontSize + 4);
                    $font->color('#FFFFFF');
                    $font->align('left');
                    $font->valign('bottom');
                });
            }

            // --- WATERMARK FOOTER RIGHT ---
            if (!empty($watermarkData['footerRight'])) {
                $finalImg->text($watermarkData['footerRight'], $finalImg->width() - 20, $finalImg->height() - 30, function ($font) use ($baseFontSize) {
                    $font->file(base_path('vendor/mpdf/mpdf/ttfonts/Roboto-Regular.ttf'));
                    $font->size($baseFontSize + 4);
                    $font->color('#FFFFFF');
                    $font->align('right');
                    $font->valign('bottom');
                });
            }

            $outputPath = preg_replace('/\.\w+$/', $supportsWebp ? '.webp' : '.jpg', $outputPath);
            $finalImg->save($outputPath, $quality, $supportsWebp ? 'webp' : 'jpg');

            unlink($tempCompressed);
            // Log::info("✅ Watermark berhasil dibuat: " . $outputPath);
            return true;
        } catch (\Exception $e) {
            Log::error("❌ Gagal membuat watermark: " . $e->getMessage() . " on line: " . $e->getLine());
            return false;
        }
    }



    public function renderPdf($permintaanDokumentasiSampling, $qr, $periode = null)
    {
        ini_set("pcre.backtrack_limit", "10000000");
        ini_set("pcre.recursion_limit", "10000000");

        DB::beginTransaction();

        try {
            $qr_img = isset($qr->file)
                ? '<img src="' . public_path("qr_documents/{$qr->file}.svg") . '" width="30" height="30"><br>' . e($qr->kode_qr)
                : '';

            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_header' => 3,
                'margin_bottom' => 3,
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'jpeg_quality' => 60,
                'img_dpi' => 72,
                'dpi' => 72,
                'tempDir' => storage_path('app/tmp'),
                'useSubstitutions' => false,
                'autoScriptToLang' => false,
                'autoLangToFont' => false,
            ]);

            $pdf->SetCompression(true);

            $pdf->setFooter([
                'odd' => [
                    'C' => [
                        'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'color' => '#606060'
                    ],
                    'R' => [
                        'content' => 'Note: Dokumen ini diterbitkan otomatis oleh sistem<br>{DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        'color' => '#000000'
                    ],
                    'L' => [
                        'content' => $qr_img,
                        'font-size' => 4,
                        'font-style' => 'I',
                        'color' => '#000000'
                    ],
                    'line' => -1,
                ]
            ]);

            $data = $permintaanDokumentasiSampling;
            $noOrder = $data->no_order;
            $detail = OrderDetail::withAnyDataLapangan()
                ->where('no_order', $noOrder)
                ->where('is_active', true);

            if ($periode) $detail = $detail->where('periode', $periode);

            $detail = $detail->get()->filter(fn($item) => $item->any_data_lapangan);
            if ($detail->isEmpty()) {
                throw new \Exception("No data lapangan found for Order: {$noOrder}.");
            }

            foreach ($detail as $item) {
                foreach ($item->any_data_lapangan as $dataLapangan) {
                    $noSampelClean = str_replace('/', '_', $item->no_sampel);
                    $randomId = Str::random(8);

                    $outputDir = public_path("request/temp_img/{$noOrder}");
                    File::makeDirectory($outputDir, 2775, true, true);

                    $watermarkData = [
                        'header' => "{$data->nama_perusahaan}\nOrder ID: {$noOrder}",
                        'footerLeft' => "Sampling Date: " . Carbon::parse($dataLapangan->created_at)->format('j F Y H:i') . "\nReport By: {$dataLapangan->created_by}",
                        'footerRight' => optional($dataLapangan)->titik_koordinat,
                    ];

                    $fotoFields = [
                        'foto_lokasi_sampel' => 'lokasi',
                        'foto_kondisi_sampel' => 'kondisi',
                        'foto_lainnya' => 'lainnya',
                        'foto_samping_kiri' => 'kiri',
                        'foto_samping_kanan' => 'kanan',
                        'foto_depan' => 'depan',
                        'foto_belakang' => 'belakang',
                        'foto_lain' => 'lain'
                    ];

                    foreach ($fotoFields as $field => $label) {
                        $source = optional($dataLapangan)->{$field};
                        if (!$source) continue;

                        $outputName = "{$label}-{$noSampelClean}_{$randomId}.webp";
                        $outputPath = "{$outputDir}/{$outputName}";

                        if ($this->processAndWatermarkImage($source, $outputPath, $watermarkData)) {
                            $dataLapangan->{"webp_path_{$label}"} = "request/temp_img/{$noOrder}/{$outputName}";
                        }
                    }
                }
            }

            $html = view('dokumentasi-sampling', compact('data', 'detail'))->render();
            $pdf->WriteHTML($html);

            $fileName = str_replace("/", "_", $permintaanDokumentasiSampling->no_document) . '.pdf';
            $dir = public_path("request/dokumentasi_sampling");

            if (!File::exists($dir)) File::makeDirectory($dir, 0755, true);

            $filePath = "{$dir}/{$fileName}";
            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);

            $permintaanDokumentasiSampling->update([
                'status' => 'PDF Ready',
                'filename' => $fileName
            ]);

            DB::commit();

            $tempImgDir = public_path("request/temp_img/{$noOrder}");
            if (File::exists($tempImgDir)) {
                exec("rm -rf {$tempImgDir}");
            }

            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            $permintaanDokumentasiSampling->update(['status' => 'Tidak ada sampling']);

            Log::error($e->getMessage() . ' on line: ' . $e->getLine());
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }
}

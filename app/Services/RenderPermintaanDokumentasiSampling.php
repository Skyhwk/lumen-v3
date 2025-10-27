<?php

namespace App\Services;

use Mpdf\Mpdf;
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
        $originalPath = public_path('dokumentasi/sampling/' . $originalFileName);
        Log::info("Fetching image from PATH: $originalPath");

        if (!$originalFileName || !File::exists($originalPath)) {
            Log::warning("Image not found: $originalPath");
            return false;
        }

        try {
            $img = Image::make($originalPath);

            // ✅ 1️⃣ Resize untuk menjaga proporsionalitas (maksimal 1500px)
            $maxWidth = 1500;
            $img->resize($maxWidth, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // ✅ 2️⃣ Simpan sementara hasil kompres
            $tempCompressed = tempnam(sys_get_temp_dir(), 'cmp');
            $quality = 80;
            $maxSizeKB = 500;
            $supportsWebp = imagetypes() & IMG_WEBP;

            if ($supportsWebp) {
                do {
                    $img->encode('webp', $quality)->save($tempCompressed);
                    $sizeKB = filesize($tempCompressed) / 1024;
                    $quality -= 10;
                } while ($sizeKB > $maxSizeKB && $quality > 30);
            } else {
                do {
                    $img->encode('jpg', $quality)->save($tempCompressed);
                    $sizeKB = filesize($tempCompressed) / 1024;
                    $quality -= 10;
                } while ($sizeKB > $maxSizeKB && $quality > 30);
            }

            // ✅ 3️⃣ Buka kembali versi hasil kompres
            $finalImg = Image::make($tempCompressed);

            // ✅ 4️⃣ Hitung ukuran font secara dinamis berdasarkan dimensi
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

            // ✅ 5️⃣ Simpan hasil akhir (watermark + kompres)
            $outputPath = preg_replace('/\.\w+$/', $supportsWebp ? '.webp' : '.jpg', $outputPath);
            $finalImg->save($outputPath, $quality, $supportsWebp ? 'webp' : 'jpg');

            Log::info("✅ Base font size: $baseFontSize from $width x $height Final saved {$outputPath} ({$sizeKB}KB @ quality {$quality})");

            unlink($tempCompressed);
            return true;
        } catch (\Exception $e) {
            Log::error("❌ Gagal membuat watermark: " . $e->getMessage());
            return false;
        }
    }



    public function renderPdf($permintaanDokumentasiSampling, $qr, $periode = null)
    {
        Log::info("=== RENDERING PDF REQ DOC SAMPLING: {$permintaanDokumentasiSampling->no_quotation} ({$permintaanDokumentasiSampling->no_order}) ===");

        DB::beginTransaction();

        try {
            $qr_img = isset($qr->file)
                ? '<img src="' . public_path("qr_documents/{$qr->file}.svg") . '" width="30" height="30"><br>' . e($qr->kode_qr)
                : '';

            // ✅ Konfigurasi mPDF fokus efisiensi
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
            $detail = OrderDetail::withAnyDataLapangan()
                ->where('no_order', $data->no_order)
                ->where('is_active', true);

            if ($periode) {
                $detail = $detail->where('periode', $periode);
            }

            $detail = $detail->get()->filter(fn($item) => $item->any_data_lapangan);

            foreach ($detail as $item) {
                foreach ($item->any_data_lapangan as $dataLapangan) {
                    $noOrder = $data->no_order;
                    $noSampelClean = str_replace('/', '_', $item->no_sampel);
                    $randomId = Str::random(8);

                    $outputDir = public_path("request/temp_img/{$noOrder}");
                    File::makeDirectory($outputDir, 2775, true, true);

                    $watermarkData = [
                        'header' => "{$data->nama_perusahaan}\nOrder ID: {$data->no_order}",
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
            Log::info("✅ PDF READY: {$filePath}");

            $tempImgDir = public_path("request/temp_img/{$noOrder}");
            if (File::exists($tempImgDir)) {
                exec("rm -rf {$tempImgDir}");
            }

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage() . ' on line: ' . $e->getLine());
            return response()->json(['message' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }
}

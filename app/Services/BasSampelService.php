<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\DataLapanganAir;
use App\Models\BasSampelSelesai;
use App\Models\SampelTidakSelesai;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BasSampelService
{
    /**
     * Proses semua sampel saat Submit Final BAS.
     * 
     * Mengecek status setiap sampel (selesai / parsial / belum selesai),
     * lalu memasukkannya ke bas_sampel_selesai atau sampel_tidak_selesai.
     *
     * @param array $item Data order (no_order, no_quotation, tanggal_sampling)
     * @param callable $getStatusSampling Callback ke fungsi getStatusSampling di controller
     * @return array|null Mengembalikan error array jika ada sampel parsial, null jika sukses
     */
    public static function processFinalSamples(array $item, callable $getStatusSampling): ?array
    {
        $fullExpectedNoSampel = OrderDetail::where('no_order', $item['no_order'])
            ->where('is_active', true)
            ->where('tanggal_sampling', $item['tanggal_sampling'])
            ->pluck('no_sampel')
            ->unique()
            ->toArray();

        if (!is_array($fullExpectedNoSampel) || count($fullExpectedNoSampel) === 0) {
            return null;
        }

        Log::info('Reaching BasSampelSelesai loop. expectedNoSampel: ' . json_encode($fullExpectedNoSampel));

        foreach ($fullExpectedNoSampel as $fullNoSampel) {
            $detailSample = OrderDetail::where('no_sampel', $fullNoSampel)->first();
            $rawKategori = $detailSample ? ($detailSample->kategori_3 ?? $detailSample->kategori_2) : 'Umum';
            $kategoriStr = preg_replace('/^\d+-/', '', $rawKategori);
            $parts = explode(' ', $kategoriStr);
            $mainKategori = $parts[0];
            $subKategori = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

            $isCompleted = false;
            $statusSampling = 'belum selesai';

            if ($detailSample && $detailSample->kategori_2 === "1-Air") {
                $isCompleted = DataLapanganAir::where('no_sampel', $fullNoSampel)
                    ->where('is_blocked', 0)
                    ->where('is_rejected', 0)
                    ->exists();
                $statusSampling = $isCompleted ? 'selesai' : 'belum selesai';
            } else if ($detailSample) {
                $statusSampling = $getStatusSampling($detailSample);
                if ($statusSampling === 'parsial') {
                    return [
                        'status' => 'error',
                        'message' => 'Mohon isi data, data Anda belum lengkap untuk sampel ' . $fullNoSampel
                    ];
                }
                $isCompleted = ($statusSampling === 'selesai');
            }

            if (!$isCompleted) {
                $existingCancel = SampelTidakSelesai::where('no_sampel', $fullNoSampel)->first();
                if (!$existingCancel) {
                    Log::info('Auto-cancelling sample: ' . $fullNoSampel);
                    SampelTidakSelesai::create([
                        'no_sampel' => $fullNoSampel,
                        'no_order' => $item['no_order'],
                        'kategori' => $kategoriStr,
                        'alasan' => 'Dibatalkan otomatis dari Submit BAS',
                        'status' => 'Belum Selesai',
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'created_by' => 'System'
                    ]);
                }
            } else {
                Log::info('Inserting into bas_sampel_selesai: ' . $fullNoSampel);
                BasSampelSelesai::updateOrCreate(
                    [
                        'no_order' => $item['no_order'],
                        'no_sampel' => $fullNoSampel,
                        'tanggal_sampling' => $item['tanggal_sampling'],
                    ],
                    [
                        'no_quotation' => $item['no_quotation'],
                        'kategori' => $mainKategori,
                        'sub_kategori' => $subKategori,
                        'status' => 'Selesai',
                    ]
                );

                // Bersihkan dari sampel_tidak_selesai karena sekarang sudah diisi
                SampelTidakSelesai::where('no_sampel', $fullNoSampel)->delete();
            }
        }

        return null; // Sukses, tidak ada error
    }
}

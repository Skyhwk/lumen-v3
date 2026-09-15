<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\DataLapanganAir;
use App\Models\BasSampelSelesai;
use App\Models\SampelTidakSelesai;
use App\Models\PersiapanSampelHeader;
use App\Models\PersiapanSampelDetail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BasSampelService
{
    /**
     * Proses sampel milik BAS yang sedang di-submit (bukan seluruh order).
     *
     * @param array $item Data order (no_order, no_quotation, tanggal_sampling, expectedNoSampel)
     * @param callable $getStatusSampling Callback ke fungsi getStatusSampling
     * @param int|null $idPersiapan Header persiapan BAS yang sedang disimpan
     * @return array|true|null Error array jika parsial, true jika sukses, null jika tidak ada sampel
     */
    public static function processFinalSamples(array $item, callable $getStatusSampling, $idPersiapan = null)
    {
        $fullExpectedNoSampel = self::resolveScopedSamples($item);

        if (empty($fullExpectedNoSampel)) {
            Log::warning('processFinalSamples skipped: no scoped samples on this BAS', [
                'no_order' => $item['no_order'] ?? null,
                'tanggal_sampling' => $item['tanggal_sampling'] ?? null,
            ]);
            return null;
        }

        foreach ($fullExpectedNoSampel as $fullNoSampel) {
            $detailSample = OrderDetail::where('no_sampel', $fullNoSampel)
                ->where('is_active', true)
                ->first();
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

            $resolvedHeaderId = self::resolveIdPersiapan(
                $fullNoSampel,
                $item['no_order'] ?? null,
                $item['tanggal_sampling'] ?? null,
                $idPersiapan
            );

            if (!$isCompleted) {
                $existingCancel = SampelTidakSelesai::where('no_sampel', $fullNoSampel)->first();
                if (!$existingCancel) {
                    if (!$resolvedHeaderId) {
                        Log::warning('Auto-cancelling sample without id_persiapan', [
                            'no_sampel' => $fullNoSampel,
                            'no_order' => $item['no_order'] ?? null,
                            'tanggal_sampling' => $item['tanggal_sampling'] ?? null,
                        ]);
                    }
                    Log::info('Auto-cancelling sample: ' . $fullNoSampel);
                    SampelTidakSelesai::create([
                        'no_sampel' => $fullNoSampel,
                        'no_order' => $item['no_order'],
                        'id_persiapan' => $resolvedHeaderId,
                        'kategori' => $kategoriStr,
                        'alasan' => 'Dibatalkan otomatis dari Submit BAS',
                        'keterangan' => 'Dibatalkan otomatis dari Submit BAS',
                        'status' => 'Belum Selesai',
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'created_by' => 'System'
                    ]);
                } elseif (empty($existingCancel->id_persiapan) && $resolvedHeaderId) {
                    $existingCancel->id_persiapan = $resolvedHeaderId;
                    $existingCancel->save();
                }
            } else {
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

                SampelTidakSelesai::where('no_sampel', $fullNoSampel)->delete();
            }
        }

        return true;
    }

    /**
     * Hanya sampel dokumen BAS yang sedang disubmit.
     * Tidak boleh fallback ke seluruh OrderDetail order+tanggal.
     */
    public static function resolveScopedSamples(array $item): array
    {
        $fromPayload = [];

        if (!empty($item['expectedNoSampel']) && is_array($item['expectedNoSampel'])) {
            $fromPayload = $item['expectedNoSampel'];
        } elseif (!empty($item['no_sampel']) && is_array($item['no_sampel'])) {
            $fromPayload = array_map(function ($kode) use ($item) {
                if (!is_string($kode) && !is_numeric($kode)) {
                    return null;
                }
                $kode = (string) $kode;
                if (strpos($kode, '/') !== false) {
                    return $kode;
                }
                $noOrder = $item['no_order'] ?? '';
                return $noOrder !== '' ? $noOrder . '/' . $kode : $kode;
            }, $item['no_sampel']);
        }

        return array_values(array_unique(array_filter($fromPayload)));
    }

    /**
     * Cari header persiapan yang benar untuk 1 nomor sampel.
     * Tidak memakai "header terakhir per order" tanpa mencocokkan sampel.
     */
    public static function resolveIdPersiapan(
        ?string $noSampel,
        ?string $noOrder = null,
        ?string $tanggalSampling = null,
        $fallbackHeaderId = null
    ): ?int {
        if (!empty($fallbackHeaderId)) {
            return (int) $fallbackHeaderId;
        }

        if (empty($noSampel)) {
            return null;
        }

        $parts = explode('/', $noSampel);
        $kode = end($parts);

        $detailMatches = PersiapanSampelDetail::where(function ($q) use ($noSampel, $kode) {
                $q->where('no_sampel', $noSampel)
                    ->orWhere('no_sampel', 'LIKE', '%/' . $kode);
            })
            ->orderByDesc('id')
            ->get();

        $headerFromDetail = self::pickActiveHeader(
            $detailMatches->pluck('id_persiapan_sampel_header')->filter()->unique()->values()->all(),
            $noOrder,
            $tanggalSampling
        );
        if ($headerFromDetail) {
            return (int) $headerFromDetail;
        }

        $headerQuery = PersiapanSampelHeader::where('is_active', true)
            ->where(function ($query) use ($noSampel, $kode) {
                $query->whereJsonContains('no_sampel', $noSampel)
                    ->orWhere('no_sampel', 'LIKE', '%"' . $noSampel . '"%')
                    ->orWhere('no_sampel', 'LIKE', '%/' . $kode . '"%');
            })
            ->orderByDesc('id');

        if ($noOrder) {
            $headerQuery->where('no_order', $noOrder);
        }
        if ($tanggalSampling) {
            $headerQuery->where('tanggal_sampling', $tanggalSampling);
        }

        $psh = $headerQuery->first();
        if ($psh) {
            return (int) $psh->id;
        }

        if ($tanggalSampling) {
            $withoutDate = PersiapanSampelHeader::where('is_active', true)
                ->where(function ($query) use ($noSampel, $kode) {
                    $query->whereJsonContains('no_sampel', $noSampel)
                        ->orWhere('no_sampel', 'LIKE', '%"' . $noSampel . '"%')
                        ->orWhere('no_sampel', 'LIKE', '%/' . $kode . '"%');
                })
                ->when($noOrder, function ($q) use ($noOrder) {
                    $q->where('no_order', $noOrder);
                })
                ->orderByDesc('id')
                ->first();

            if ($withoutDate) {
                return (int) $withoutDate->id;
            }
        }

        return null;
    }

    private static function pickActiveHeader(array $headerIds, ?string $noOrder, ?string $tanggalSampling): ?int
    {
        if (empty($headerIds)) {
            return null;
        }

        if ($tanggalSampling) {
            $datedQuery = PersiapanSampelHeader::whereIn('id', $headerIds)
                ->where('is_active', true)
                ->where('tanggal_sampling', $tanggalSampling)
                ->orderByDesc('id');
            if ($noOrder) {
                $datedQuery->where('no_order', $noOrder);
            }
            $match = $datedQuery->first();
            if ($match) {
                return (int) $match->id;
            }
        }

        $fallbackQuery = PersiapanSampelHeader::whereIn('id', $headerIds)
            ->where('is_active', true)
            ->orderByDesc('id');
        if ($noOrder) {
            $fallbackQuery->where('no_order', $noOrder);
        }
        $match = $fallbackQuery->first();
        return $match ? (int) $match->id : null;
    }
}

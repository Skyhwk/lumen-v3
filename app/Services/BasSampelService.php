<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\DataLapanganAir;
use App\Models\BasSampelSelesai;
use App\Models\SampelTidakSelesai;

class BasSampelService
{
    public static function isCompleted($sample, callable $getStatusSampling)
    {
        if ($sample->kategori_2 === '1-Air') {
            return DataLapanganAir::where('no_sampel', $sample->no_sampel)
                ->where('is_blocked', 0)->where('is_rejected', 0)->exists();
        }
        if (($sample->kategori_3 ?? null) === '118-Psikologi') {
            return true;
        }
        return $getStatusSampling($sample) === 'selesai';
    }

    public static function processFinalSamples(array $item, callable $getStatusSampling)
    {
        $header = BasDocumentScope::resolve($item, true);
        $samples = BasDocumentScope::samples($item['no_sampel'], $item['no_order']);
        $details = OrderDetail::where('no_order', $item['no_order'])
            ->where('tanggal_sampling', $item['tanggal_sampling'])->where('is_active', true)
            ->whereIn('no_sampel', $samples)->lockForUpdate()->get()->unique('no_sampel');
        foreach ($details as $sample) {
            // Hapus semua STS order+sampel (termasuk orphan beda id_persiapan) saat selesai.
            $decisionQuery = SampelTidakSelesai::where('no_order', $item['no_order'])
                ->where('no_sampel', $sample->no_sampel);
            if (!self::isCompleted($sample, $getStatusSampling)) {
                $decision = (clone $decisionQuery)
                    ->where(function ($query) use ($header) {
                        $query->where('id_persiapan', $header->id)->orWhereNull('id_persiapan');
                    })
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();
                if (!BasDocumentScope::validDecision($decision, $item['tanggal_sampling'])) {
                    throw new \InvalidArgumentException('Simpan keputusan yang valid untuk sampel belum lengkap: ' . $sample->no_sampel);
                }
                continue;
            }
            $category = preg_replace('/^\d+-/', '', $sample->kategori_3 ?? $sample->kategori_2);
            $parts = explode(' ', $category, 2);
            BasSampelSelesai::updateOrCreate([
                'no_order' => $item['no_order'], 'no_sampel' => $sample->no_sampel,
                'tanggal_sampling' => $item['tanggal_sampling'],
            ], [
                'no_quotation' => $header->no_quotation, 'kategori' => $parts[0],
                'sub_kategori' => $parts[1] ?? null, 'status' => 'Selesai',
            ]);
            $decisionQuery->delete();
        }
        self::clearOrphanTidakSelesai([$item['no_order']]);
        return true;
    }

    /** Bersihkan STS orphan untuk sampel yang sudah ada di bas_sampel_selesai. */
    public static function clearOrphanTidakSelesai(array $orderNos)
    {
        if (!$orderNos) {
            return 0;
        }
        $completed = BasSampelSelesai::whereIn('no_order', $orderNos)
            ->pluck('no_sampel')
            ->unique()
            ->all();
        if (!$completed) {
            return 0;
        }
        return SampelTidakSelesai::whereIn('no_order', $orderNos)
            ->whereIn('no_sampel', $completed)
            ->delete();
    }
}

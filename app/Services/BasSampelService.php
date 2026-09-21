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
            $decisionQuery = SampelTidakSelesai::where('no_order', $item['no_order'])
                ->where('no_sampel', $sample->no_sampel)->where('id_persiapan', $header->id);
            if (!self::isCompleted($sample, $getStatusSampling)) {
                $decision = (clone $decisionQuery)->orderBy('id', 'desc')->lockForUpdate()->first();
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
        return true;
    }
}

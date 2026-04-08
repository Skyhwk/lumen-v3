<?php

namespace App\Services;

use App\Models\KalkulasiTargetPenjadwalan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KalkulasiTargetPenjadwalanService
{
    const NAMA_BULAN = [
        1  => 'januari',   2  => 'februari', 3  => 'maret',    4  => 'april',
        5  => 'mei',       6  => 'juni',      7  => 'juli',     8  => 'agustus',
        9  => 'september', 10 => 'oktober',   11 => 'november', 12 => 'desember',
    ];

    public function execute(): KalkulasiTargetPenjadwalan
    {
        try {
            $hasil = $this->hitungTargetTahunan();

            return DB::transaction(function () use ($hasil) {
                return KalkulasiTargetPenjadwalan::updateOrCreate(
                    ['tahun' => $hasil['tahun']],
                    $hasil
                );
            });
        } catch (\Exception $e) {
            Log::error("Gagal kalkulasi target: " . $e->getMessage());
            throw $e;
        }
    }

    public function hitungTargetTahunan(): array
    {
        $tahun = (int) Carbon::now()->year;

        $raw = $this->querySetahun($tahun);

        // mapping hasil ke array bulan
        $result = ['tahun' => (string) $tahun];

        foreach (self::NAMA_BULAN as $bulanAngka => $namaBulan) {
            $data = $raw->firstWhere('bulan', $bulanAngka);

            $total = $data 
                ? ((float) $data->total_kontrak + (float) $data->total_non_kontrak)
                : null;

            $result[$namaBulan] = $total === 0.0 ? null : $total;
        }

        return $result;
    }

    /**
     * 🔥 CORE QUERY: hitung 1 tahun langsung
     */
    private function querySetahun(int $tahun)
    {
        /**
         * 1. Ambil FIRST DATE per quotation + periode
         */
        $base = DB::table('sampling_plan as sp')
            ->join('jadwal as j', 'sp.id', '=', 'j.id_sampling')
            ->select([
                'sp.no_quotation',
                'j.periode',
                DB::raw('MIN(j.tanggal) as tanggal_pertama'),
                DB::raw('MONTH(MIN(j.tanggal)) as bulan'),
                DB::raw('YEAR(MIN(j.tanggal)) as tahun'),
            ])
            ->where('sp.is_active', 1)
            ->where('j.is_active', 1)
            ->where('j.status', 1)
            ->groupBy('sp.no_quotation', 'j.periode')
            ->havingRaw('YEAR(MIN(j.tanggal)) = ?', [$tahun]);

        /**
         * 2. Kontrak (per periode)
         */
        $kontrak = DB::table('request_quotation_kontrak_H as kh')
            ->join('request_quotation_kontrak_D as kd', 'kd.id_request_quotation_kontrak_h', '=', 'kh.id')
            ->select([
                'kh.no_document',
                'kd.periode_kontrak',
                DB::raw('SUM(kd.biaya_akhir) as total_kontrak'),
            ])
            ->groupBy('kh.no_document', 'kd.periode_kontrak');

        /**
         * 3. Non-kontrak
         */
        $nonKontrak = DB::table('request_quotation as q')
            ->select([
                'q.no_document',
                DB::raw('SUM(q.biaya_akhir) as total_non_kontrak'),
            ])
            ->groupBy('q.no_document');

        /**
         * 4. Final aggregation per bulan
         */
        return DB::table(DB::raw("({$base->toSql()}) as b"))
            ->mergeBindings($base)
            ->leftJoinSub($kontrak, 'k', function ($join) {
                $join->on('k.no_document', '=', 'b.no_quotation')
                    ->on('k.periode_kontrak', '=', 'b.periode');
            })
            ->leftJoinSub($nonKontrak, 'nk', function ($join) {
                $join->on('nk.no_document', '=', 'b.no_quotation');
            })
            ->select([
                'b.bulan',
                DB::raw('COALESCE(SUM(k.total_kontrak), 0) as total_kontrak'),
                DB::raw('COALESCE(SUM(nk.total_non_kontrak), 0) as total_non_kontrak'),
            ])
            ->groupBy('b.bulan')
            ->get();
    }
}
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

    /**
     * Fungsi Utama: Kalkulasi + Simpan
     */
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

    /**
     * Kalkulasi target bulanan tahun sekarang
     */
    public function hitungTargetTahunan(): array
    {
        $tahunSekarang = (string) Carbon::now()->year;

        $targetBulanan = ['tahun' => $tahunSekarang];

        foreach (self::NAMA_BULAN as $bulanAngka => $namaBulan) {
            $hasil = $this->queryTotalPerBulan($bulanAngka, (int) $tahunSekarang);

            $kontrak    = (float) ($hasil->total_kontrak    ?? 0);
            $nonKontrak = (float) ($hasil->total_non_kontrak ?? 0);
            $total      = $kontrak + $nonKontrak;

            $targetBulanan[$namaBulan] = ($kontrak === 0.0 && $nonKontrak === 0.0)
                ? null
                : $total;
        }

        return $targetBulanan;
    }

    /**
     * Query total kontrak + non kontrak untuk 1 bulan tertentu
     */
    private function queryTotalPerBulan(int $bulan, int $tahun): object
    {
        $jadwalSub = DB::table('jadwal as j')
            ->select([
                'j.no_quotation',
                DB::raw('MIN(j.tanggal) as tanggal_pertama'),
            ])
            ->where('j.is_active', 1)
            ->whereYear('j.tanggal', $tahun)
            ->whereMonth('j.tanggal', $bulan)
            ->groupBy('j.no_quotation')
            ->havingRaw('MONTH(MIN(j.tanggal)) = ?', [$bulan])
            ->havingRaw('YEAR(MIN(j.tanggal)) = ?', [$tahun]);

        $kontrakSub = DB::table('request_quotation_kontrak_H as kh')
            ->select([
                'kh.no_document',
                DB::raw('SUM(kd.biaya_akhir) as total_biaya'),
            ])
            ->join('request_quotation_kontrak_D as kd', 'kd.id_request_quotation_kontrak_h', '=', 'kh.id')
            ->groupBy('kh.no_document');

        $nonKontrakSub = DB::table('request_quotation as qnk')
            ->select([
                'qnk.no_document',
                DB::raw('SUM(qnk.biaya_akhir) as total_biaya'),
            ])
            ->groupBy('qnk.no_document');

        $result = DB::table(DB::raw("({$jadwalSub->toSql()}) as jadwal_bulan"))
            ->mergeBindings($jadwalSub)
            ->select([
                DB::raw('COALESCE(SUM(kontrak.total_biaya), 0)     as total_kontrak'),
                DB::raw('COALESCE(SUM(non_kontrak.total_biaya), 0) as total_non_kontrak'),
            ])
            ->leftJoinSub($kontrakSub, 'kontrak', function ($join) {
                $join->on('kontrak.no_document', '=', 'jadwal_bulan.no_quotation');
            })
            ->leftJoinSub($nonKontrakSub, 'non_kontrak', function ($join) {
                $join->on('non_kontrak.no_document', '=', 'jadwal_bulan.no_quotation');
            })
            ->first();

        return $result ?? (object) ['total_kontrak' => 0, 'total_non_kontrak' => 0];
    }
}
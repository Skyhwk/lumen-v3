<?php
namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesDailyQSD
{
    public static function run()
    {
        try {
            Log::info('[SalesDailyQSD] Starting QSD data update...');

            // Get current year
            // $currentYear = '2024';
            // $maxDate     = '2024-12-31';

            $currentYear = Carbon::now()->format('Y');
            $maxDate     = Carbon::now()->endOfYear()->format('Y-m-d');

            // Build query untuk data QSD
            $rekapOrder = DB::table('order_detail')
                ->selectRaw('
                    order_detail.no_order,
                    order_detail.no_quotation,
                    COUNT(DISTINCT order_detail.cfr) AS total_cfr,
                    order_detail.nama_perusahaan,
                    order_detail.konsultan,
                    MIN(CASE order_detail.kontrak WHEN "C" THEN rqkd.periode_kontrak ELSE NULL END) as periode,
                    order_detail.kontrak,
                    MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkh.sales_id ELSE NULL END) as sales_id_kontrak,
                    MAX(CASE WHEN order_detail.kontrak = "C" THEN mk_kontrak.nama_lengkap ELSE NULL END) as sales_nama_kontrak,
                    MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.sales_id ELSE NULL END) as sales_id_non_kontrak,
                    MAX(CASE WHEN order_detail.kontrak != "C" THEN mk_non_kontrak.nama_lengkap ELSE NULL END) as sales_nama_non_kontrak,
                    MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.total_discount ELSE NULL END) as total_discount_kontrak,
                    MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.total_ppn ELSE NULL END) as total_ppn_kontrak,
                    MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.total_pph ELSE NULL END) as total_pph_kontrak,
                    MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.biaya_akhir ELSE NULL END) as biaya_akhir_kontrak,
                    MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.grand_total ELSE NULL END) as grand_total_kontrak,
                    MAX(CASE WHEN order_detail.kontrak = "C" THEN (COALESCE(rqkd.biaya_akhir, 0) + COALESCE(rqkd.total_pph, 0) - COALESCE(rqkd.total_ppn, 0)) ELSE NULL END) as total_revenue_kontrak,
                    MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_discount ELSE NULL END) as total_discount_non_kontrak,
                    MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_ppn ELSE NULL END) as total_ppn_non_kontrak,
                    MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_pph ELSE NULL END) as total_pph_non_kontrak,
                    MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.biaya_akhir ELSE NULL END) as biaya_akhir_non_kontrak,
                    MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.grand_total ELSE NULL END) as grand_total_non_kontrak,
                    MAX(CASE WHEN order_detail.kontrak != "C" THEN (COALESCE(rq.biaya_akhir, 0) + COALESCE(rq.total_pph, 0) - COALESCE(rq.total_ppn, 0)) ELSE NULL END) as total_revenue_non_kontrak,
                    MIN(order_detail.tanggal_sampling) as tanggal_sampling_min
                ')
                ->where('order_detail.is_active', true)
                ->whereDate('order_detail.tanggal_sampling', '<=', $maxDate)
                ->whereRaw("YEAR(order_detail.tanggal_sampling) = ?", [$currentYear]);

            // JOIN UNTUK KONTRAK (C)
            $rekapOrder->leftJoin('request_quotation_kontrak_H as rqkh', function ($join) {
                $join->on('order_detail.no_quotation', '=', 'rqkh.no_document')
                    ->whereNotIn('rqkh.pelanggan_ID', ['SAIR02', 'T2PE01'])
                    ->where('rqkh.is_active', true);

            });

            $rekapOrder->leftJoin('request_quotation_kontrak_D as rqkd', function ($join) use ($currentYear) {
                $join->on('rqkh.id', '=', 'rqkd.id_request_quotation_kontrak_H')
                    ->whereRaw("LEFT(rqkd.periode_kontrak, 4) = ?", [$currentYear]);
            });

            // JOIN master_karyawan untuk sales kontrak
            $rekapOrder->leftJoin('master_karyawan as mk_kontrak', function ($join) {
                $join->on('rqkh.sales_id', '=', 'mk_kontrak.id');
            });

            // JOIN UNTUK NON-KONTRAK (!= C)
            $rekapOrder->leftJoin('request_quotation as rq', function ($join) {
                $join->on('order_detail.no_quotation', '=', 'rq.no_document')
                    ->whereNotIn('rq.pelanggan_ID', ['SAIR02', 'T2PE01'])
                    ->where('rq.is_active', true);
            });

            // JOIN master_karyawan untuk sales non-kontrak
            $rekapOrder->leftJoin('master_karyawan as mk_non_kontrak', function ($join) {
                $join->on('rq.sales_id', '=', 'mk_non_kontrak.id');
            });

            // FILTER UTAMA
            $rekapOrder->where(function ($query) use ($currentYear) {
                $query->where(function ($q) use ($currentYear) {
                    $q->where('order_detail.kontrak', 'C')
                        ->whereNotNull('rqkh.id')
                        ->whereColumn('order_detail.periode', 'rqkd.periode_kontrak')
                        ->whereNotNull('rqkd.id')
                        ->whereRaw("LEFT(rqkd.periode_kontrak, 4) = ?", [$currentYear]);
                })
                    ->orWhere(function ($q) {
                        $q->where('order_detail.kontrak', '!=', 'C')
                            ->whereNotNull('rq.id');
                    });
            });

            // GROUP BY
            $rekapOrder->groupByRaw('
                order_detail.no_order,
                order_detail.no_quotation,
                order_detail.nama_perusahaan,
                order_detail.konsultan,
                order_detail.periode,
                order_detail.kontrak,
                CASE
                    WHEN order_detail.kontrak = "C" THEN rqkd.periode_kontrak
                    ELSE NULL
                END
            ');

            // ORDER BY
            $rekapOrder->orderBy('tanggal_sampling_min', 'desc')
                ->orderBy('order_detail.no_order', 'asc');

            // Get data
            $data = $rekapOrder->get();

            Log::info('[SalesDailyQSD] Found ' . $data->count() . ' records');

            // Start transaction
            DB::beginTransaction();

            try {
                // Truncate table QSD
                DB::table('daily_qsd')
                    ->whereRaw("YEAR(tanggal_sampling_min) = ?", [$currentYear])
                    ->delete();

                Log::info('[SalesDailyQSD] Table daily_qsd truncated for year ' . $currentYear);

                // Insert data in chunks untuk performa lebih baik
                if ($data->isNotEmpty()) {
                    $chunks        = $data->chunk(500);
                    $totalInserted = 0;

                    foreach ($chunks as $chunk) {
                        $insertData = [];

                        foreach ($chunk as $row) {
                            $insertData[] = [
                                'no_order'             => $row->no_order,
                                'no_quotation'         => $row->no_quotation,
                                'total_cfr'            => $row->total_cfr,
                                'nama_perusahaan'      => $row->nama_perusahaan,
                                'konsultan'            => $row->konsultan,
                                'periode'              => $row->periode,
                                'kontrak'              => $row->kontrak,
                                'sales_id'             => $row->kontrak === 'C' ? $row->sales_id_kontrak : $row->sales_id_non_kontrak,
                                'sales_nama'           => $row->kontrak === 'C' ? $row->sales_nama_kontrak : $row->sales_nama_non_kontrak,
                                'total_discount'       => $row->kontrak === 'C' ? $row->total_discount_kontrak : $row->total_discount_non_kontrak,
                                'total_ppn'            => $row->kontrak === 'C' ? $row->total_ppn_kontrak : $row->total_ppn_non_kontrak,
                                'total_pph'            => $row->kontrak === 'C' ? $row->total_pph_kontrak : $row->total_pph_non_kontrak,
                                'biaya_akhir'          => $row->kontrak === 'C' ? $row->biaya_akhir_kontrak : $row->biaya_akhir_non_kontrak,
                                'grand_total'          => $row->kontrak === 'C' ? $row->grand_total_kontrak : $row->grand_total_non_kontrak,
                                'total_revenue'        => $row->kontrak === 'C' ? $row->total_revenue_kontrak : $row->total_revenue_non_kontrak,
                                'tanggal_sampling_min' => $row->tanggal_sampling_min,
                                'created_at'           => Carbon::now()->format('Y-m-d H:i:s'),
                            ];
                        }

                        DB::table('daily_qsd')->insert($insertData);
                        $totalInserted += count($insertData);
                    }

                    Log::info('[SalesDailyQSD] Inserted ' . $totalInserted . ' records into daily_qsd table');
                }

                // Commit transaction
                DB::commit();
                Log::info('[SalesDailyQSD] QSD data update completed successfully');

                return true;

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('[SalesDailyQSD] Transaction error: ' . $e->getMessage());
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('[SalesDailyQSD] Error updating QSD data: ' . $e->getMessage());
            Log::error('[SalesDailyQSD] Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
}

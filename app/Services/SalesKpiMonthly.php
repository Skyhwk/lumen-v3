<?php

namespace App\Services;

use App\Models\SalesKpi;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\{
    QuotationKontrakH,
    QuotationNonKontrak,
    OrderHeader,
};

class SalesKpiMonthly
{
//     public static function run()
//     {
//         try {
//             $currentMonth = Carbon::now()->month;
//             $currentYear = Carbon::now()->year;

//             $monthList = [];
//             for ($i = 1; $i <= $currentMonth; $i++) {
//                 $month = str_pad($i, 2, '0', STR_PAD_LEFT);
//                 $monthList[] = "{$currentYear}-{$month}";
//             }

//             foreach ($monthList as $periodeBulan) {
//                 $indo = Carbon::createFromFormat('Y-m', $periodeBulan)->locale('id')->translatedFormat('F Y');
                
//                 $period = self::convertPeriod($indo);
            
//                 $getAllSales = DB::table(function ($query) {
//                     $query->select('request_quotation.sales_id')
//                         ->from('request_quotation')
//                         ->join('order_header', 'request_quotation.no_document', '=', 'order_header.no_document')
//                         ->where('request_quotation.is_active', true)
//                         ->unionAll(
//                             DB::table('request_quotation_kontrak_H')
//                                 ->select('request_quotation_kontrak_H.sales_id')
//                                 ->join('order_header', 'request_quotation_kontrak_H.no_document', '=', 'order_header.no_document')
//                                 ->where('request_quotation_kontrak_H.is_active', true)
//                         );
//                 }, 'sub')
//                 ->pluck('sales_id')
//                 ->unique()
//                 ->values()
//                 ->toArray();

//                 $cekCall = DB::table('log_webphone')
//                     ->whereIn('karyawan_id', $getAllSales)
//                     ->whereDate('created_at', '>=', $period['awal'])
//                     ->whereDate('created_at', '<=', $period['akhir'])
//                     ->whereRaw("
//                         time IS NOT NULL 
//                         AND time <> ''
//                         AND (
//                             CASE
//                                 WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 2 THEN
//                                     (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 3600) +
//                                     (CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(time, ':', 2), ':', -1) AS UNSIGNED) * 60) +
//                                     (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
//                                 WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 1 THEN
//                                     (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 60) +
//                                     (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
//                                 ELSE 0
//                             END
//                         ) > 180
//                     ")
//                     ->selectRaw("
//                         karyawan_id,
//                         COUNT(*) AS total_calls,
//                         SUM(
//                             CASE
//                                 WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 2 THEN
//                                     (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 3600) +
//                                     (CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(time, ':', 2), ':', -1) AS UNSIGNED) * 60) +
//                                     (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
//                                 WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 1 THEN
//                                     (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 60) +
//                                     (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
//                                 ELSE 0
//                             END
//                         ) AS total_time
//                     ")
//                     ->groupBy('karyawan_id')
//                     ->get()->toArray();

                
//                 // cek call

//                 $daily_qs = DB::table('daily_qsd')
//                     ->whereIn('sales_id', $getAllSales)
//                     ->whereDate('tanggal_sampling_min', '>=', $period['awal'])
//                     ->whereDate('tanggal_sampling_min', '<=', $period['akhir'])
//                     ->selectRaw('sales_id, COUNT(*) as total_qt, SUM(total_revenue) as total_revenue')
//                     ->groupBy('daily_qsd.sales_id')
//                     ->get()->toArray();


                

                

//                 // cek quotation non kontrak
//                 $qtNonData = QuotationNonKontrak::whereIn('request_quotation.sales_id', $getAllSales)
//                     ->whereDate('request_quotation.created_at', ">=", $period['awal'])
//                     ->whereDate('request_quotation.created_at', "<=", $period['akhir'])
//                     ->leftJoin('order_header', 'request_quotation.pelanggan_ID', '=', 'order_header.id_pelanggan')
//                     ->where('request_quotation.is_active', true)
//                     ->selectRaw("
//                         request_quotation.sales_id, 
//                         COUNT(*) as total_qt, 
//                         SUM(CASE WHEN order_header.id_pelanggan IS NOT NULL THEN 1 ELSE 0 END) as exist_count,
//                         SUM(CASE WHEN order_header.id_pelanggan IS NULL THEN 1 ELSE 0 END) as new_count
//                     ")
//                     ->groupBy('request_quotation.sales_id')
//                     ->get()->toArray();

//                 $qtKonData = QuotationKontrakH::whereIn('request_quotation_kontrak_H.sales_id', $getAllSales)
//                     ->whereDate('request_quotation_kontrak_H.created_at', ">=", $period['awal'])
//                     ->whereDate('request_quotation_kontrak_H.created_at', "<=", $period['akhir'])
//                     ->leftJoin('order_header', 'request_quotation_kontrak_H.pelanggan_ID', '=', 'order_header.id_pelanggan')
//                     ->where('request_quotation_kontrak_H.is_active', true)
//                     ->selectRaw("
//                         request_quotation_kontrak_H.sales_id, 
//                         COUNT(*) as total_qt, 
//                         SUM(CASE WHEN order_header.id_pelanggan IS NOT NULL THEN 1 ELSE 0 END) as exist_count,
//                         SUM(CASE WHEN order_header.id_pelanggan IS NULL THEN 1 ELSE 0 END) as new_count
//                     ")
//                     ->groupBy('request_quotation_kontrak_H.sales_id')
//                     ->get()->toArray();



//                 //cek quote yang menjadi order
                
//                 $qsNonDataNonSp = OrderHeader::query()  // qs non kontrak non sampling
//                     ->join('request_quotation', 'order_header.no_document', '=', 'request_quotation.no_document')
//                     ->leftJoin(DB::raw("
//                         (
//                             SELECT id_pelanggan, COUNT(*) as total_order
//                             FROM order_header
//                             WHERE id_pelanggan IS NOT NULL
//                             GROUP BY id_pelanggan
//                         ) as pelanggan_count
//                     "), 'order_header.id_pelanggan', '=', 'pelanggan_count.id_pelanggan')
//                     ->whereIn('request_quotation.sales_id', $getAllSales)
//                     ->whereBetween(DB::raw('DATE(request_quotation.created_at)'), [$period['awal'], $period['akhir']])
//                     ->where('order_header.is_active', true)
//                     ->selectRaw("
//                         request_quotation.sales_id,
//                         COUNT(DISTINCT order_header.no_document) as total_qt,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN 1 ELSE 0 END) as exist_count,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN 1 ELSE 0 END) as new_count,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN order_header.biaya_akhir ELSE 0 END) as total_amount_exist,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN order_header.biaya_akhir ELSE 0 END) as total_amount_new,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN order_header.total_dpp ELSE 0 END) as revenue_exist,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN order_header.total_dpp ELSE 0 END) as revenue_new
//                     ")
//                     ->groupBy('request_quotation.sales_id')
//                     ->get()
//                     ->toArray();

//                 $yearMonth = $periodeBulan;

//                 $qsKonDataNonSp = DB::table('order_header as oh') // qs kontrak non sampling
//                     ->join('request_quotation_kontrak_H as h', 'oh.no_document', '=', 'h.no_document')
//                     ->join('request_quotation_kontrak_D as d', 'h.id', '=', 'd.id_request_quotation_kontrak_h')
//                     ->leftJoin(DB::raw("
//                         (
//                             SELECT id_pelanggan, COUNT(*) as total_order
//                             FROM order_header
//                             WHERE id_pelanggan IS NOT NULL
//                             GROUP BY id_pelanggan
//                         ) as pelanggan_count
//                     "), 'oh.id_pelanggan', '=', 'pelanggan_count.id_pelanggan')
//                     ->whereIn('h.sales_id', $getAllSales)
//                     ->where('oh.is_active', true)
//                     ->where('d.periode_kontrak', 'LIKE', $yearMonth . '%')
//                     ->selectRaw("
//                         h.sales_id,
//                         COUNT(DISTINCT h.id) as total_qt,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN 1 ELSE 0 END) as exist_count,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN 1 ELSE 0 END) as new_count,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN d.biaya_akhir ELSE 0 END) as total_amount_exist,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN d.biaya_akhir ELSE 0 END) as total_amount_new,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN d.total_dpp ELSE 0 END) as revenue_exist,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN d.total_dpp ELSE 0 END) as revenue_new
//                     ")
//                     ->groupBy('h.sales_id')
//                     ->get()
//                     ->toArray();
                
//                 $qsNonDataSp = OrderHeader::query()
//                     ->join('request_quotation', 'order_header.no_document', '=', 'request_quotation.no_document')
//                     ->join(DB::raw('
//                         (
//                             SELECT id_order_header, MIN(tanggal_terima) as tanggal_terima_terkecil
//                             FROM order_detail
//                             GROUP BY id_order_header
//                         ) as od
//                     '), 'order_header.id', '=', 'od.id_order_header')
//                     ->leftJoin(DB::raw("
//                         (
//                             SELECT id_pelanggan, COUNT(*) as total_order
//                             FROM order_header
//                             WHERE id_pelanggan IS NOT NULL
//                             GROUP BY id_pelanggan
//                         ) as pelanggan_count
//                     "), 'order_header.id_pelanggan', '=', 'pelanggan_count.id_pelanggan')
//                     ->whereIn('request_quotation.sales_id', $getAllSales)
//                     ->where('request_quotation.is_active', true)
//                     ->whereBetween('od.tanggal_terima_terkecil', [$period['awal'], $period['akhir']])
//                     ->selectRaw("
//                         request_quotation.sales_id,
//                         COUNT(DISTINCT order_header.no_document) as total_qt,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN 1 ELSE 0 END) as exist_count,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN 1 ELSE 0 END) as new_count,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN order_header.biaya_akhir ELSE 0 END) as total_amount_exist,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN order_header.biaya_akhir ELSE 0 END) as total_amount_new,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN order_header.total_dpp ELSE 0 END) as revenue_exist,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN order_header.total_dpp ELSE 0 END) as revenue_new
//                     ")
//                     ->groupBy('request_quotation.sales_id')
//                     ->get()
//                     ->toArray();
                
//                     $qsKonDataSp = OrderHeader::query()
//                     ->join('request_quotation_kontrak_H as h', 'order_header.no_document', '=', 'h.no_document')
//                     ->join('request_quotation_kontrak_D as d', 'h.id', '=', 'd.id_request_quotation_kontrak_h')
//                     ->join(DB::raw('
//                         (
//                             SELECT id_order_header, MIN(tanggal_terima) as tanggal_terkecil, periode 
//                             FROM order_detail 
//                             GROUP BY id_order_header, periode
//                         ) as od
//                     '), function($join) use ($yearMonth) {
//                         $join->on('order_header.id', '=', 'od.id_order_header')
//                                 ->where('od.periode', '=', $yearMonth);
//                     })
//                     ->leftJoin(DB::raw("
//                         (
//                             SELECT id_pelanggan, COUNT(*) as total_order
//                             FROM order_header
//                             WHERE id_pelanggan IS NOT NULL
//                             GROUP BY id_pelanggan
//                         ) as pelanggan_count
//                     "), 'order_header.id_pelanggan', '=', 'pelanggan_count.id_pelanggan')
//                     ->whereIn('h.sales_id', $getAllSales)
//                     ->where('h.is_active', true)
//                     ->where('d.periode_kontrak', $yearMonth)
//                     ->whereBetween('od.tanggal_terkecil', [$period['awal'], $period['akhir']])
//                     ->selectRaw("
//                         h.sales_id,
//                         COUNT(DISTINCT order_header.no_document) as total_qt,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN 1 ELSE 0 END) as exist_count,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN 1 ELSE 0 END) as new_count,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN d.biaya_akhir ELSE 0 END) as total_amount_exist,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN d.biaya_akhir ELSE 0 END) as total_amount_new,
//                         SUM(CASE WHEN pelanggan_count.total_order > 1 THEN d.total_dpp ELSE 0 END) as revenue_exist,
//                         SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN d.total_dpp ELSE 0 END) as revenue_new,
//                         MIN(od.tanggal_terkecil) as tanggal_sampling
//                     ")
//                     ->groupBy('h.sales_id')
//                     ->get()
//                     ->toArray();            
            
//                 // ==================== GABUNGKAN SEMUA DATA KE SATU ARRAY ====================

//                 // Ubah semua collection ke keyed array berdasarkan sales_id
                
//                 $cekCall = collect($cekCall)->keyBy('karyawan_id');
//                 $qtNonData = collect($qtNonData)->keyBy('sales_id')->map(function($item) {
//                     return (object) $item;
//                 });
//                 $qtKonData = collect($qtKonData)->keyBy('sales_id')->map(function($item) {
//                     return (object) $item;
//                 });
//                 $qsNonDataNonSp = collect($qsNonDataNonSp)->keyBy('sales_id')->map(function($item) {
//                     return (object) $item;
//                 });
//                 $qsKonDataNonSp = collect($qsKonDataNonSp)->keyBy('sales_id');
//                 $qsNonDataSp = collect($qsNonDataSp)->keyBy('sales_id')->map(function($item) {
//                     return (object) $item;
//                 });
//                 $qsKonDataSp = collect($qsKonDataSp)->keyBy('sales_id');

//                 $dataInsert = [];

//                 foreach ($getAllSales as $salesId) {
//                     $call = $cekCall->get($salesId);
//                     $qtNon = $qtNonData->get($salesId);
//                     $qtKon = $qtKonData->get($salesId);
//                     $nonOrder = $qsNonDataNonSp->get($salesId);
//                     $konOrder = $qsKonDataNonSp->get($salesId);
//                     $nonOrderSp = $qsNonDataSp->get($salesId);
//                     $konOrderSp = $qsKonDataSp->get($salesId);

//                     $dataInsert[] = [
//                         'karyawan_id' => $salesId,
//                         'periode' => $periodeBulan,
//                         'dfus_call' => $call->total_calls ?? 0,
//                         'duration' => $call->total_time ?? 0,

//                         // Quotation Non Kontrak
//                         'qty_qt_nonkontrak_new' => $qtNon->new_count ?? 0,
//                         'qty_qt_nonkontrak_exist' => $qtNon->exist_count ?? 0,

//                         // Quotation Kontrak
//                         'qty_qt_kontrak_new' => $qtKon->new_count ?? 0,
//                         'qty_qt_kontrak_exist' => $qtKon->exist_count ?? 0,

//                         // Order dari quotation non kontrak (non sampling)
//                         'qty_qt_order_nonkontrak_new' => $nonOrder->new_count ?? 0,
//                         'qty_qt_order_nonkontrak_exist' => $nonOrder->exist_count ?? 0,
//                         'qty_qt_order_kontrak_new' => $konOrder->new_count ?? 0,
//                         'qty_qt_order_kontrak_exist' => $konOrder->exist_count ?? 0,

                        
//                         'amount_order_nonkontrak_new' => $nonOrder->total_amount_new ?? 0,
//                         'amount_order_nonkontrak_exist' => $nonOrder->total_amount_exist ?? 0,
//                         'revenue_order_nonkontrak_new' => $nonOrder->revenue_new ?? 0,
//                         'revenue_order_nonkontrak_exist' => $nonOrder->revenue_exist ?? 0,

//                         // Order dari quotation kontrak (non sampling)
//                         'amount_order_kontrak_new' => $konOrder->total_amount_new ?? 0,
//                         'amount_order_kontrak_exist' => $konOrder->total_amount_exist ?? 0,
//                         'revenue_order_kontrak_new' => $konOrder->revenue_new ?? 0,
//                         'revenue_order_kontrak_exist' => $konOrder->revenue_exist ?? 0,

//                         // Order dari quotation non kontrak (sampling)
//                         'amount_bysampling_order_nonkontrak_new' => $nonOrderSp->total_amount_new ?? 0,
//                         'amount_bysampling_order_nonkontrak_exist' => $nonOrderSp->total_amount_exist ?? 0,
//                         'revenue_bysampling_order_nonkontrak_new' => $nonOrderSp->revenue_new ?? 0,
//                         'revenue_bysampling_order_nonkontrak_exist' => $nonOrderSp->revenue_exist ?? 0,

//                         // Order dari quotation kontrak (sampling)
//                         'amount_bysampling_order_kontrak_new' => $konOrderSp->total_amount_new ?? 0,
//                         'amount_bysampling_order_kontrak_exist' => $konOrderSp->total_amount_exist ?? 0,
//                         'revenue_bysampling_order_kontrak_new' => $konOrderSp->revenue_new ?? 0,
//                         'revenue_bysampling_order_kontrak_exist' => $konOrderSp->revenue_exist ?? 0,

//                         'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
//                     ];

//                 }
                
                
//                 foreach ($dataInsert as $item) {
//                     SalesKpi::updateOrCreate(
//                         [
//                             'karyawan_id' => $item['karyawan_id'],
//                             'periode' => $item['periode'],
//                         ],
//                         [
//                             'dfus_call' => $item['dfus_call'] ?? 0,
//                             'duration' => $item['duration'] ?? 0,
//                             'qty_qt_nonkontrak_new' => $item['qty_qt_nonkontrak_new'] ?? 0,
//                             'qty_qt_nonkontrak_exist' => $item['qty_qt_nonkontrak_exist'] ?? 0,
//                             'qty_qt_kontrak_new' => $item['qty_qt_kontrak_new'] ?? 0,
//                             'qty_qt_kontrak_exist' => $item['qty_qt_kontrak_exist'] ?? 0,
//                             'qty_qt_order_nonkontrak_new' => $item['qty_qt_order_nonkontrak_new'] ?? 0,
//                             'qty_qt_order_nonkontrak_exist' => $item['qty_qt_order_nonkontrak_exist'] ?? 0,
//                             'qty_qt_order_kontrak_new' => $item['qty_qt_order_kontrak_new'] ?? 0,
//                             'qty_qt_order_kontrak_exist' => $item['qty_qt_order_kontrak_exist'] ?? 0,
//                             'amount_order_nonkontrak_new' => $item['amount_order_nonkontrak_new'] ?? 0,
//                             'amount_order_nonkontrak_exist' => $item['amount_order_nonkontrak_exist'] ?? 0,
//                             'amount_order_kontrak_new' => $item['amount_order_kontrak_new'] ?? 0,
//                             'amount_order_kontrak_exist' => $item['amount_order_kontrak_exist'] ?? 0,
//                             'amount_bysampling_order_nonkontrak_new' => $item['amount_bysampling_order_nonkontrak_new'] ?? 0,
//                             'amount_bysampling_order_nonkontrak_exist' => $item['amount_bysampling_order_nonkontrak_exist'] ?? 0,
//                             'amount_bysampling_order_kontrak_new' => $item['amount_bysampling_order_kontrak_new'] ?? 0,
//                             'amount_bysampling_order_kontrak_exist' => $item['amount_bysampling_order_kontrak_exist'] ?? 0,
//                             'revenue_order_nonkontrak_new' => $item['revenue_order_nonkontrak_new'] ?? 0,
//                             'revenue_order_nonkontrak_exist' => $item['revenue_order_nonkontrak_exist'] ?? 0,
//                             'revenue_order_kontrak_new' => $item['revenue_order_kontrak_new'] ?? 0,
//                             'revenue_order_kontrak_exist' => $item['revenue_order_kontrak_exist'] ?? 0,
//                             'revenue_bysampling_order_nonkontrak_new' => $item['revenue_bysampling_order_nonkontrak_new'] ?? 0,
//                             'revenue_bysampling_order_nonkontrak_exist' => $item['revenue_bysampling_order_nonkontrak_exist'] ?? 0,
//                             'revenue_bysampling_order_kontrak_new' => $item['revenue_bysampling_order_kontrak_new'] ?? 0,
//                             'revenue_bysampling_order_kontrak_exist' => $item['revenue_bysampling_order_kontrak_exist'] ?? 0,
//                             'updated_at' => $item['updated_at'] ?? now(),
//                         ]
//                     );
//                 }
//             }
            
//             Log::channel('update_kpi_sales')->info('Update KPI Sales berhasil dijalankan pada '. Carbon::now()->format('Y-m-d H:i:s'));

//             return true;
//         } catch (\Throwable $th) {
//             dd($th);
//         }
//     }

    public static function run()
    {
       try {
        $currentMonth = 12;
        $currentYear = 2024;

        // dd($currentMonth, $currentYear);

        $monthList = [];
        for ($i = 1; $i <= $currentMonth; $i++) {
            $month = str_pad($i, 2, '0', STR_PAD_LEFT);
            $monthList[] = "{$currentYear}-{$month}";
        }

        foreach ($monthList as $periodeBulan) {
            $indo = Carbon::createFromFormat('Y-m', $periodeBulan)->locale('id')->translatedFormat('F Y');
            
            $period = self::convertPeriod($indo);
        
            // Ambil semua sales_id unik dari daily_qsd
            $getAllSales = DB::table('daily_qsd')
                ->whereNotNull('sales_id')
                ->distinct()
                ->pluck('sales_id')
                ->toArray();

            // ==================== CEK CALL ====================
            $cekCall = DB::table('log_webphone')
                ->whereIn('karyawan_id', $getAllSales)
                ->whereDate('created_at', '>=', $period['awal'])
                ->whereDate('created_at', '<=', $period['akhir'])
                ->whereRaw("
                    time IS NOT NULL 
                    AND time <> ''
                    AND (
                        CASE
                            WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 2 THEN
                                (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 3600) +
                                (CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(time, ':', 2), ':', -1) AS UNSIGNED) * 60) +
                                (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
                            WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 1 THEN
                                (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 60) +
                                (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
                            ELSE 0
                        END
                    ) > 180
                ")
                ->selectRaw("
                    karyawan_id,
                    COUNT(*) AS total_calls,
                    SUM(
                        CASE
                            WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 2 THEN
                                (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 3600) +
                                (CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(time, ':', 2), ':', -1) AS UNSIGNED) * 60) +
                                (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
                            WHEN LENGTH(time) - LENGTH(REPLACE(time, ':', '')) = 1 THEN
                                (CAST(SUBSTRING_INDEX(time, ':', 1) AS UNSIGNED) * 60) +
                                (CAST(SUBSTRING_INDEX(time, ':', -1) AS UNSIGNED))
                            ELSE 0
                        END
                    ) AS total_time
                ")
                ->groupBy('karyawan_id')
                ->get();

            // dd($period['awal'], $period['akhir']);

            // ==================== DATA DARI daily_qsd ====================
            
            // Query untuk mendapatkan data quotation (belum jadi order)
            // Asumsi: jika ada no_order berarti sudah jadi order, jika NULL masih quotation

           $quotationData = DB::table('daily_qsd as d')
                    ->whereIn('d.sales_id', $getAllSales)
                    ->whereYear('d.tanggal_sampling_min', '=', explode('-', $periodeBulan)[0])
                    ->whereMonth('d.tanggal_sampling_min', '=', explode('-', $periodeBulan)[1])
                    ->selectRaw("
                        d.sales_id,
                        d.kontrak,
                        
                        -- Quotation counts berdasarkan status_customer
                        COUNT(DISTINCT d.no_quotation) as total_quotation,
                        SUM(CASE WHEN d.status_customer = 'exist' THEN 1 ELSE 0 END) as qt_exist_count,
                        SUM(CASE WHEN d.status_customer = 'new' THEN 1 ELSE 0 END) as qt_new_count,
                        
                        -- Order counts berdasarkan status_customer
                        COUNT(DISTINCT d.no_order) as total_order,
                        SUM(CASE WHEN d.status_customer = 'exist' THEN 1 ELSE 0 END) as order_exist_count,
                        SUM(CASE WHEN d.status_customer = 'new' THEN 1 ELSE 0 END) as order_new_count,
                        
                        -- Amount & Revenue untuk exist customer
                        SUM(CASE WHEN d.status_customer = 'exist' THEN d.biaya_akhir ELSE 0 END) as amount_exist,
                        SUM(CASE WHEN d.status_customer = 'exist' THEN d.total_revenue ELSE 0 END) as revenue_exist,
                        
                        -- Amount & Revenue untuk new customer
                        SUM(CASE WHEN d.status_customer = 'new' THEN d.biaya_akhir ELSE 0 END) as amount_new,
                        SUM(CASE WHEN d.status_customer = 'new' THEN d.total_revenue ELSE 0 END) as revenue_new
                    ")
                    ->groupBy('d.sales_id', 'd.kontrak')
                    ->get();

            // dd($quotationData);

            // ==================== GABUNGKAN DATA ====================
            
            $cekCall = collect($cekCall)->keyBy('karyawan_id');
            
            // Pisahkan data berdasarkan kontrak (Y/N) dan sales_id

            $dataByKontrak = collect($quotationData)->groupBy(function($item) {
                return $item->sales_id . '_' . ($item->kontrak ?? 'N');
            });

            // dd($dataByKontrak);
            $dataInsert = [];

            foreach ($getAllSales as $salesId) {
                $call = $cekCall->get($salesId);
                
                // Ambil data non kontrak (kontrak = 'N' atau NULL)
                $nonKontrak = $dataByKontrak->get($salesId . '_N');
                // Ambil data kontrak (kontrak = 'C')
                $kontrak = $dataByKontrak->get($salesId . '_C');

                $dataInsert[] = [
                    'karyawan_id' => $salesId,
                    'periode' => $periodeBulan,
                    'dfus_call' => $call->total_calls ?? 0,
                    'duration' => $call->total_time ?? 0,

                    // Quotation Non Kontrak
                    'qty_qt_nonkontrak_new' => $nonKontrak ? $nonKontrak->sum('qt_new_count') : 0,
                    'qty_qt_nonkontrak_exist' => $nonKontrak ? $nonKontrak->sum('qt_exist_count') : 0,

                    // Quotation Kontrak
                    'qty_qt_kontrak_new' => $kontrak ? $kontrak->sum('qt_new_count') : 0,
                    'qty_qt_kontrak_exist' => $kontrak ? $kontrak->sum('qt_exist_count') : 0,
                    // Order Non Kontrak
                    'qty_qt_order_nonkontrak_new' => $nonKontrak ? $nonKontrak->sum('order_new_count') : 0,
                    'qty_qt_order_nonkontrak_exist' => $nonKontrak ? $nonKontrak->sum('order_exist_count') : 0,
                    'amount_order_nonkontrak_new' => $nonKontrak ? $nonKontrak->sum('amount_new') : 0,
                    'amount_order_nonkontrak_exist' => $nonKontrak ? $nonKontrak->sum('amount_exist') : 0,
                    'revenue_order_nonkontrak_new' => $nonKontrak ? $nonKontrak->sum('revenue_new') : 0,
                    'revenue_order_nonkontrak_exist' => $nonKontrak ? $nonKontrak->sum('revenue_exist') : 0,

                    // Order Kontrak
                    'qty_qt_order_kontrak_new' => $kontrak ? $kontrak->sum('order_new_count') : 0,
                    'qty_qt_order_kontrak_exist' => $kontrak ? $kontrak->sum('order_exist_count') : 0,
                    'amount_order_kontrak_new' => $kontrak ? $kontrak->sum('amount_new') : 0,
                    'amount_order_kontrak_exist' => $kontrak ? $kontrak->sum('amount_exist') : 0,
                    'revenue_order_kontrak_new' => $kontrak ? $kontrak->sum('revenue_new') : 0,
                    'revenue_order_kontrak_exist' => $kontrak ? $kontrak->sum('revenue_exist') : 0,

                    // Sampling data - untuk sekarang diset 0 dulu
                    // Jika perlu logika khusus untuk sampling, bisa ditambahkan
                    'amount_bysampling_order_nonkontrak_new' => 0,
                    'amount_bysampling_order_nonkontrak_exist' => 0,
                    'revenue_bysampling_order_nonkontrak_new' => 0,
                    'revenue_bysampling_order_nonkontrak_exist' => 0,
                    'amount_bysampling_order_kontrak_new' => 0,
                    'amount_bysampling_order_kontrak_exist' => 0,
                    'revenue_bysampling_order_kontrak_new' => 0,
                    'revenue_bysampling_order_kontrak_exist' => 0,

                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ];
            }

            // Debug - uncomment jika perlu            
            // Insert/Update ke database
            // foreach ($dataInsert as $item) {
            //     SalesKpi::updateOrCreate(
            //         [
            //             'karyawan_id' => $item['karyawan_id'],
            //             'periode' => $item['periode'],
            //         ],
            //         [
            //             'dfus_call' => $item['dfus_call'] ?? 0,
            //             'duration' => $item['duration'] ?? 0,
            //             'qty_qt_nonkontrak_new' => $item['qty_qt_nonkontrak_new'] ?? 0,
            //             'qty_qt_nonkontrak_exist' => $item['qty_qt_nonkontrak_exist'] ?? 0,
            //             'qty_qt_kontrak_new' => $item['qty_qt_kontrak_new'] ?? 0,
            //             'qty_qt_kontrak_exist' => $item['qty_qt_kontrak_exist'] ?? 0,
            //             'qty_qt_order_nonkontrak_new' => $item['qty_qt_order_nonkontrak_new'] ?? 0,
            //             'qty_qt_order_nonkontrak_exist' => $item['qty_qt_order_nonkontrak_exist'] ?? 0,
            //             'qty_qt_order_kontrak_new' => $item['qty_qt_order_kontrak_new'] ?? 0,
            //             'qty_qt_order_kontrak_exist' => $item['qty_qt_order_kontrak_exist'] ?? 0,
            //             'amount_order_nonkontrak_new' => $item['amount_order_nonkontrak_new'] ?? 0,
            //             'amount_order_nonkontrak_exist' => $item['amount_order_nonkontrak_exist'] ?? 0,
            //             'amount_order_kontrak_new' => $item['amount_order_kontrak_new'] ?? 0,
            //             'amount_order_kontrak_exist' => $item['amount_order_kontrak_exist'] ?? 0,
            //             'amount_bysampling_order_nonkontrak_new' => $item['amount_bysampling_order_nonkontrak_new'] ?? 0,
            //             'amount_bysampling_order_nonkontrak_exist' => $item['amount_bysampling_order_nonkontrak_exist'] ?? 0,
            //             'amount_bysampling_order_kontrak_new' => $item['amount_bysampling_order_kontrak_new'] ?? 0,
            //             'amount_bysampling_order_kontrak_exist' => $item['amount_bysampling_order_kontrak_exist'] ?? 0,
            //             'revenue_order_nonkontrak_new' => $item['revenue_order_nonkontrak_new'] ?? 0,
            //             'revenue_order_nonkontrak_exist' => $item['revenue_order_nonkontrak_exist'] ?? 0,
            //             'revenue_order_kontrak_new' => $item['revenue_order_kontrak_new'] ?? 0,
            //             'revenue_order_kontrak_exist' => $item['revenue_order_kontrak_exist'] ?? 0,
            //             'revenue_bysampling_order_nonkontrak_new' => $item['revenue_bysampling_order_nonkontrak_new'] ?? 0,
            //             'revenue_bysampling_order_nonkontrak_exist' => $item['revenue_bysampling_order_nonkontrak_exist'] ?? 0,
            //             'revenue_bysampling_order_kontrak_new' => $item['revenue_bysampling_order_kontrak_new'] ?? 0,
            //             'revenue_bysampling_order_kontrak_exist' => $item['revenue_bysampling_order_kontrak_exist'] ?? 0,
            //             'updated_at' => $item['updated_at'] ?? now(),
            //         ]
            //     );
            // }

            SalesKpi::upsert(
                $dataInsert,
                ['karyawan_id', 'periode'], // unique constraint
                [
                    'dfus_call',
                    'duration',
                    'qty_qt_nonkontrak_new',
                    'qty_qt_nonkontrak_exist',
                    'qty_qt_kontrak_new',
                    'qty_qt_kontrak_exist',
                    'qty_qt_order_nonkontrak_new',
                    'qty_qt_order_nonkontrak_exist',
                    'qty_qt_order_kontrak_new',
                    'qty_qt_order_kontrak_exist',
                    'amount_order_nonkontrak_new',
                    'amount_order_nonkontrak_exist',
                    'amount_order_kontrak_new',
                    'amount_order_kontrak_exist',
                    'amount_bysampling_order_nonkontrak_new',
                    'amount_bysampling_order_nonkontrak_exist',
                    'amount_bysampling_order_kontrak_new',
                    'amount_bysampling_order_kontrak_exist',
                    'revenue_order_nonkontrak_new',
                    'revenue_order_nonkontrak_exist',
                    'revenue_order_kontrak_new',
                    'revenue_order_kontrak_exist',
                    'revenue_bysampling_order_nonkontrak_new',
                    'revenue_bysampling_order_nonkontrak_exist',
                    'revenue_bysampling_order_kontrak_new',
                    'revenue_bysampling_order_kontrak_exist',
                    'updated_at'
                ]
            );
        }
        
        Log::channel('update_kpi_sales')->info('Update KPI Sales berhasil dijalankan pada '. Carbon::now()->format('Y-m-d H:i:s'));

        return true;
    } catch (\Throwable $th) {
        Log::channel('update_kpi_sales')->error('Error Update KPI Sales: ' . $th->getMessage());
        dd($th);
    }
    }

    protected static function convertPeriod($period)
    {
        $months = [
            "Januari" => "01",
            "Februari" => "02",
            "Maret"=> "03",
            "April"=> "04",
            "Mei"=> "05",
            "Juni"=> "06",
            "Juli"=> "07",
            "Agustus"=> "08",
            "September"=> "09",
            "Oktober"=> "10",
            "November"=> "11",
            "Desember"=> "12",
        ];

        // Misal input: "Oktober 2025"
        $parts = explode(' ', trim($period));
        if(count($parts) !== 2) return [];

        $monthName = $parts[0];
        $year = $parts[1];

        if (!isset($months[$monthName])) return [];
        $month = $months[$monthName];

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);
        $awal = "{$year}-{$month}-01";
        $akhir = "{$year}-{$month}-" . str_pad($daysInMonth, 2, "0", STR_PAD_LEFT);

        return [ 'awal' => $awal, 'akhir' => $akhir ];
    }

    protected static function convertYearMonth($periode){
        $months = [
            "Januari" => "01",
            "Februari" => "02",
            "Maret"=> "03",
            "April"=> "04",
            "Mei"=> "05",
            "Juni"=> "06",
            "Juli"=> "07",
            "Agustus"=> "08",
            "September"=> "09",
            "Oktober"=> "10",
            "November"=> "11",
            "Desember"=> "12",
        ];


        return \explode(' ', $periode)[1] . '-' . $months[\explode(' ', $periode)[0]];
    }
}
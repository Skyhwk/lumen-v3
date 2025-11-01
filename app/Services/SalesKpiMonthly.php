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
    public static function run()
    {
        try {
            $currentMonth = Carbon::now()->month;
            $currentYear = Carbon::now()->year;

            $monthList = [];
            for ($i = 1; $i <= $currentMonth; $i++) {
                $month = str_pad($i, 2, '0', STR_PAD_LEFT);
                $monthList[] = "{$currentYear}-{$month}";
            }

            foreach ($monthList as $periodeBulan) {
                $indo = Carbon::createFromFormat('Y-m', $periodeBulan)->locale('id')->translatedFormat('F Y');
                
                $period = self::convertPeriod($indo);
            
                $getAllSales = DB::table(function ($query) {
                    $query->select('sales_id')
                        ->from('request_quotation')
                        ->join('order_header', 'request_quotation.no_document', '=', 'order_header.no_document')
                        ->where('request_quotation.is_active', true)
                        ->unionAll(
                            DB::table('request_quotation_kontrak_H')
                                ->select('sales_id')
                                ->join('order_header', 'request_quotation_kontrak_H.no_document', '=', 'order_header.no_document')
                                ->where('request_quotation_kontrak_H.is_active', true)
                        );
                }, 'sub')
                ->pluck('sales_id')
                ->unique()
                ->values()
                ->toArray();

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
                    ->get()->toArray();

                
                // cek call
                

                // cek quotation non kontrak
                $qtNonData = QuotationNonKontrak::whereIn('sales_id', $getAllSales)
                    ->whereDate('request_quotation.created_at', ">=", $period['awal'])
                    ->whereDate('request_quotation.created_at', "<=", $period['akhir'])
                    ->leftJoin('order_header', 'request_quotation.pelanggan_ID', '=', 'order_header.id_pelanggan')
                    ->where('request_quotation.is_active', true)
                    ->selectRaw("
                        request_quotation.sales_id, 
                        COUNT(*) as total_qt, 
                        SUM(CASE WHEN order_header.id_pelanggan IS NOT NULL THEN 1 ELSE 0 END) as exist_count,
                        SUM(CASE WHEN order_header.id_pelanggan IS NULL THEN 1 ELSE 0 END) as new_count
                    ")
                    ->groupBy('request_quotation.sales_id')
                    ->get()->toArray();

                $qtKonData = QuotationKontrakH::whereIn('sales_id', $getAllSales)
                    ->whereDate('request_quotation_kontrak_H.created_at', ">=", $period['awal'])
                    ->whereDate('request_quotation_kontrak_H.created_at', "<=", $period['akhir'])
                    ->leftJoin('order_header', 'request_quotation_kontrak_H.pelanggan_ID', '=', 'order_header.id_pelanggan')
                    ->where('request_quotation_kontrak_H.is_active', true)
                    ->selectRaw("
                        request_quotation_kontrak_H.sales_id, 
                        COUNT(*) as total_qt, 
                        SUM(CASE WHEN order_header.id_pelanggan IS NOT NULL THEN 1 ELSE 0 END) as exist_count,
                        SUM(CASE WHEN order_header.id_pelanggan IS NULL THEN 1 ELSE 0 END) as new_count
                    ")
                    ->groupBy('request_quotation_kontrak_H.sales_id')
                    ->get()->toArray();

                //cek quote yang menjadi order
                $qsNonDataNonSp = OrderHeader::query()  // qs non kontrak non sampling
                    ->join('request_quotation', 'order_header.no_document', '=', 'request_quotation.no_document')
                    ->leftJoin(DB::raw("
                        (
                            SELECT id_pelanggan, COUNT(*) as total_order
                            FROM order_header
                            WHERE id_pelanggan IS NOT NULL
                            GROUP BY id_pelanggan
                        ) as pelanggan_count
                    "), 'order_header.id_pelanggan', '=', 'pelanggan_count.id_pelanggan')
                    ->whereIn('request_quotation.sales_id', $getAllSales)
                    ->whereBetween(DB::raw('DATE(request_quotation.created_at)'), [$period['awal'], $period['akhir']])
                    ->where('order_header.is_active', true)
                    ->selectRaw("
                        request_quotation.sales_id,
                        COUNT(DISTINCT order_header.no_document) as total_qt,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN 1 ELSE 0 END) as exist_count,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN 1 ELSE 0 END) as new_count,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN order_header.biaya_akhir ELSE 0 END) as total_amount_exist,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN order_header.biaya_akhir ELSE 0 END) as total_amount_new,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN order_header.total_dpp ELSE 0 END) as revenue_exist,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN order_header.total_dpp ELSE 0 END) as revenue_new
                    ")
                    ->groupBy('request_quotation.sales_id')
                    ->get()
                    ->toArray();

                $yearMonth = $periodeBulan;

                $qsKonDataNonSp = DB::table('order_header as oh') // qs kontrak non sampling
                    ->join('request_quotation_kontrak_H as h', 'oh.no_document', '=', 'h.no_document')
                    ->join('request_quotation_kontrak_D as d', 'h.id', '=', 'd.id_request_quotation_kontrak_h')
                    ->leftJoin(DB::raw("
                        (
                            SELECT id_pelanggan, COUNT(*) as total_order
                            FROM order_header
                            WHERE id_pelanggan IS NOT NULL
                            GROUP BY id_pelanggan
                        ) as pelanggan_count
                    "), 'oh.id_pelanggan', '=', 'pelanggan_count.id_pelanggan')
                    ->whereIn('h.sales_id', $getAllSales)
                    ->where('oh.is_active', true)
                    ->where('d.periode_kontrak', 'LIKE', $yearMonth . '%')
                    ->selectRaw("
                        h.sales_id,
                        COUNT(DISTINCT h.id) as total_qt,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN 1 ELSE 0 END) as exist_count,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN 1 ELSE 0 END) as new_count,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN d.biaya_akhir ELSE 0 END) as total_amount_exist,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN d.biaya_akhir ELSE 0 END) as total_amount_new,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN d.total_dpp ELSE 0 END) as revenue_exist,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN d.total_dpp ELSE 0 END) as revenue_new
                    ")
                    ->groupBy('h.sales_id')
                    ->get()
                    ->toArray();
                
                $qsNonDataSp = OrderHeader::query()
                    ->join('request_quotation', 'order_header.no_document', '=', 'request_quotation.no_document')
                    ->join(DB::raw('
                        (
                            SELECT id_order_header, MIN(tanggal_terima) as tanggal_terima_terkecil
                            FROM order_detail
                            GROUP BY id_order_header
                        ) as od
                    '), 'order_header.id', '=', 'od.id_order_header')
                    ->leftJoin(DB::raw("
                        (
                            SELECT id_pelanggan, COUNT(*) as total_order
                            FROM order_header
                            WHERE id_pelanggan IS NOT NULL
                            GROUP BY id_pelanggan
                        ) as pelanggan_count
                    "), 'order_header.id_pelanggan', '=', 'pelanggan_count.id_pelanggan')
                    ->whereIn('request_quotation.sales_id', $getAllSales)
                    ->where('request_quotation.is_active', true)
                    ->whereBetween('od.tanggal_terima_terkecil', [$period['awal'], $period['akhir']])
                    ->selectRaw("
                        request_quotation.sales_id,
                        COUNT(DISTINCT order_header.no_document) as total_qt,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN 1 ELSE 0 END) as exist_count,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN 1 ELSE 0 END) as new_count,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN order_header.biaya_akhir ELSE 0 END) as total_amount_exist,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN order_header.biaya_akhir ELSE 0 END) as total_amount_new,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN order_header.total_dpp ELSE 0 END) as revenue_exist,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN order_header.total_dpp ELSE 0 END) as revenue_new
                    ")
                    ->groupBy('request_quotation.sales_id')
                    ->get()
                    ->toArray();
                
                    $qsKonDataSp = OrderHeader::query()
                    ->join('request_quotation_kontrak_H as h', 'order_header.no_document', '=', 'h.no_document')
                    ->join('request_quotation_kontrak_D as d', 'h.id', '=', 'd.id_request_quotation_kontrak_h')
                    ->join(DB::raw('
                        (
                            SELECT id_order_header, MIN(tanggal_terima) as tanggal_terkecil 
                            FROM order_detail 
                            GROUP BY id_order_header
                        ) as od
                    '), 'order_header.id', '=', 'od.id_order_header')
                    ->leftJoin(DB::raw("
                        (
                            SELECT id_pelanggan, COUNT(*) as total_order
                            FROM order_header
                            WHERE id_pelanggan IS NOT NULL
                            GROUP BY id_pelanggan
                        ) as pelanggan_count
                    "), 'order_header.id_pelanggan', '=', 'pelanggan_count.id_pelanggan')
                    ->whereIn('h.sales_id', $getAllSales)
                    ->where('h.is_active', true)
                    ->where('d.periode_kontrak', $yearMonth)
                    ->whereBetween('od.tanggal_terkecil', [$period['awal'], $period['akhir']])
                    ->selectRaw("
                        h.sales_id,
                        COUNT(DISTINCT order_header.no_document) as total_qt,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN 1 ELSE 0 END) as exist_count,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN 1 ELSE 0 END) as new_count,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN d.biaya_akhir ELSE 0 END) as total_amount_exist,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN d.biaya_akhir ELSE 0 END) as total_amount_new,
                        SUM(CASE WHEN pelanggan_count.total_order > 1 THEN d.total_dpp ELSE 0 END) as revenue_exist,
                        SUM(CASE WHEN pelanggan_count.total_order = 1 OR pelanggan_count.total_order IS NULL THEN d.total_dpp ELSE 0 END) as revenue_new,
                        MIN(od.tanggal_terkecil) as tanggal_sampling
                    ")
                    ->groupBy('h.sales_id')
                    ->get()
                    ->toArray();            
            
                // ==================== GABUNGKAN SEMUA DATA KE SATU ARRAY ====================

                // Ubah semua collection ke keyed array berdasarkan sales_id
                
                $cekCall = collect($cekCall)->keyBy('karyawan_id');
                $qtNonData = collect($qtNonData)->keyBy('sales_id')->map(function($item) {
                    return (object) $item;
                });
                $qtKonData = collect($qtKonData)->keyBy('sales_id')->map(function($item) {
                    return (object) $item;
                });
                $qsNonDataNonSp = collect($qsNonDataNonSp)->keyBy('sales_id')->map(function($item) {
                    return (object) $item;
                });
                $qsKonDataNonSp = collect($qsKonDataNonSp)->keyBy('sales_id');
                $qsNonDataSp = collect($qsNonDataSp)->keyBy('sales_id')->map(function($item) {
                    return (object) $item;
                });
                $qsKonDataSp = collect($qsKonDataSp)->keyBy('sales_id');

                $dataInsert = [];

                foreach ($getAllSales as $salesId) {
                    $call = $cekCall->get($salesId);
                    $qtNon = $qtNonData->get($salesId);
                    $qtKon = $qtKonData->get($salesId);
                    $nonOrder = $qsNonDataNonSp->get($salesId);
                    $konOrder = $qsKonDataNonSp->get($salesId);
                    $nonOrderSp = $qsNonDataSp->get($salesId);
                    $konOrderSp = $qsKonDataSp->get($salesId);

                    $dataInsert[] = [
                        'karyawan_id' => $salesId,
                        'periode' => $periodeBulan,
                        'dfus_call' => $call->total_calls ?? 0,
                        'duration' => $call->total_time ?? 0,

                        // Quotation Non Kontrak
                        'qty_qt_nonkontrak_new' => $qtNon->new_count ?? 0,
                        'qty_qt_nonkontrak_exist' => $qtNon->exist_count ?? 0,

                        // Quotation Kontrak
                        'qty_qt_kontrak_new' => $qtKon->new_count ?? 0,
                        'qty_qt_kontrak_exist' => $qtKon->exist_count ?? 0,

                        // Order dari quotation non kontrak (non sampling)
                        'qty_qt_order_nonkontrak_new' => $nonOrder->new_count ?? 0,
                        'qty_qt_order_nonkontrak_exist' => $nonOrder->exist_count ?? 0,
                        'qty_qt_order_kontrak_new' => $konOrder->new_count ?? 0,
                        'qty_qt_order_kontrak_exist' => $konOrder->exist_count ?? 0,

                        
                        'amount_order_nonkontrak_new' => $nonOrder->total_amount_new ?? 0,
                        'amount_order_nonkontrak_exist' => $nonOrder->total_amount_exist ?? 0,
                        'revenue_order_nonkontrak_new' => $nonOrder->revenue_new ?? 0,
                        'revenue_order_nonkontrak_exist' => $nonOrder->revenue_exist ?? 0,

                        // Order dari quotation kontrak (non sampling)
                        'amount_order_kontrak_new' => $konOrder->total_amount_new ?? 0,
                        'amount_order_kontrak_exist' => $konOrder->total_amount_exist ?? 0,
                        'revenue_order_kontrak_new' => $konOrder->revenue_new ?? 0,
                        'revenue_order_kontrak_exist' => $konOrder->revenue_exist ?? 0,

                        // Order dari quotation non kontrak (sampling)
                        'amount_bysampling_order_nonkontrak_new' => $nonOrderSp->total_amount_new ?? 0,
                        'amount_bysampling_order_nonkontrak_exist' => $nonOrderSp->total_amount_exist ?? 0,
                        'revenue_bysampling_order_nonkontrak_new' => $nonOrderSp->revenue_new ?? 0,
                        'revenue_bysampling_order_nonkontrak_exist' => $nonOrderSp->revenue_exist ?? 0,

                        // Order dari quotation kontrak (sampling)
                        'amount_bysampling_order_kontrak_new' => $konOrderSp->total_amount_new ?? 0,
                        'amount_bysampling_order_kontrak_exist' => $konOrderSp->total_amount_exist ?? 0,
                        'revenue_bysampling_order_kontrak_new' => $konOrderSp->revenue_new ?? 0,
                        'revenue_bysampling_order_kontrak_exist' => $konOrderSp->revenue_exist ?? 0,

                        'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    ];

                }
                
                
                foreach ($dataInsert as $item) {
                    SalesKpi::updateOrCreate(
                        [
                            'karyawan_id' => $item['karyawan_id'],
                            'periode' => $item['periode'],
                        ],
                        [
                            'dfus_call' => $item['dfus_call'] ?? 0,
                            'duration' => $item['duration'] ?? 0,
                            'qty_qt_nonkontrak_new' => $item['qty_qt_nonkontrak_new'] ?? 0,
                            'qty_qt_nonkontrak_exist' => $item['qty_qt_nonkontrak_exist'] ?? 0,
                            'qty_qt_kontrak_new' => $item['qty_qt_kontrak_new'] ?? 0,
                            'qty_qt_kontrak_exist' => $item['qty_qt_kontrak_exist'] ?? 0,
                            'qty_qt_order_nonkontrak_new' => $item['qty_qt_order_nonkontrak_new'] ?? 0,
                            'qty_qt_order_nonkontrak_exist' => $item['qty_qt_order_nonkontrak_exist'] ?? 0,
                            'qty_qt_order_kontrak_new' => $item['qty_qt_order_kontrak_new'] ?? 0,
                            'qty_qt_order_kontrak_exist' => $item['qty_qt_order_kontrak_exist'] ?? 0,
                            'amount_order_nonkontrak_new' => $item['amount_order_nonkontrak_new'] ?? 0,
                            'amount_order_nonkontrak_exist' => $item['amount_order_nonkontrak_exist'] ?? 0,
                            'amount_order_kontrak_new' => $item['amount_order_kontrak_new'] ?? 0,
                            'amount_order_kontrak_exist' => $item['amount_order_kontrak_exist'] ?? 0,
                            'amount_bysampling_order_nonkontrak_new' => $item['amount_bysampling_order_nonkontrak_new'] ?? 0,
                            'amount_bysampling_order_nonkontrak_exist' => $item['amount_bysampling_order_nonkontrak_exist'] ?? 0,
                            'amount_bysampling_order_kontrak_new' => $item['amount_bysampling_order_kontrak_new'] ?? 0,
                            'amount_bysampling_order_kontrak_exist' => $item['amount_bysampling_order_kontrak_exist'] ?? 0,
                            'revenue_order_nonkontrak_new' => $item['revenue_order_nonkontrak_new'] ?? 0,
                            'revenue_order_nonkontrak_exist' => $item['revenue_order_nonkontrak_exist'] ?? 0,
                            'revenue_order_kontrak_new' => $item['revenue_order_kontrak_new'] ?? 0,
                            'revenue_order_kontrak_exist' => $item['revenue_order_kontrak_exist'] ?? 0,
                            'revenue_bysampling_order_nonkontrak_new' => $item['revenue_bysampling_order_nonkontrak_new'] ?? 0,
                            'revenue_bysampling_order_nonkontrak_exist' => $item['revenue_bysampling_order_nonkontrak_exist'] ?? 0,
                            'revenue_bysampling_order_kontrak_new' => $item['revenue_bysampling_order_kontrak_new'] ?? 0,
                            'revenue_bysampling_order_kontrak_exist' => $item['revenue_bysampling_order_kontrak_exist'] ?? 0,
                            'updated_at' => $item['updated_at'] ?? now(),
                        ]
                    );
                }
            }
            
            Log::channel('update_kpi_sales')->info('Update KPI Sales berhasil dijalankan pada '. Carbon::now()->format('Y-m-d H:i:s'));

            return true;
        } catch (\Throwable $th) {
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
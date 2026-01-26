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
    public static function run(): void
    {
        $now = Carbon::now();
        $currentYear = $now->format('Y');
        self::handle((int)$currentYear);
    }

    private static function handle($currentYear): bool
    {
       try {
            $currentMonth = 12;

            $arrayYears = self::getYearRange($currentYear);
            Log::info('Array Years: ' . json_encode($arrayYears));
            foreach ($arrayYears as $year) {

                $monthList = [];
                for ($i = 1; $i <= $currentMonth; $i++) {
                    $month = str_pad($i, 2, '0', STR_PAD_LEFT);
                    $monthList[] = "{$year}-{$month}";
                }

                $getAllSales = DB::table('daily_qsd')
                        ->whereNotNull('sales_id')
                        ->distinct()
                        ->pluck('sales_id')
                        ->toArray();
                
                Log::info('Month List: ' . json_encode($monthList));
                foreach ($monthList as $periodeBulan) {
                    $indo = Carbon::createFromFormat('Y-m', $periodeBulan)->locale('id')->translatedFormat('F Y');
                    
                    $period = self::convertPeriod($indo);
                
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

                    $quotationData = DB::table('daily_qsd as d')
                        ->whereIn('d.sales_id', $getAllSales)
                        ->whereYear('d.tanggal_kelompok', '=', explode('-', $periodeBulan)[0])
                        ->whereMonth('d.tanggal_kelompok', '=', explode('-', $periodeBulan)[1])
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
                DB::table('sales_kpi_monthly')->whereRaw('LEFT(tanggal_kelompok, 4) = ?', [$year])
                ->whereNotIn('karyawan_id', $getAllSales)
                ->delete();
            }

            Log::channel('update_kpi_sales')->info('Update KPI Sales berhasil dijalankan pada '. Carbon::now()->format('Y-m-d H:i:s'));

            return true;

        } catch (\Throwable $th) {
            Log::channel('update_kpi_sales')->error('Error Update KPI Sales: ' . $th->getMessage());
            dd($th);
        }
    }

    private static function getYearRange(int $currentYear): array
    {
        $nextYear = (int)Carbon::create($currentYear, 12, 1)->addYear(1)->endOfMonth()->format('Y');
        $arrayYears = [];
        for ($i = ($currentYear - 1); $i <= $nextYear; $i++) {
            $arrayYears[] = $i;
        }
        return $arrayYears;
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
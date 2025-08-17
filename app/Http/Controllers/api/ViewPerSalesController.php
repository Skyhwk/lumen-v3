<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\MasterKaryawan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\TargetSales;
use App\Models\RekapLiburKalender;

class ViewPerSalesController extends Controller
{
    public function index(Request $r)
    {
        // Clean dan setup tanggal
        $clean = fn($d) => Carbon::parse(preg_replace('/\s\(.*\)$/', '', $d));
        $start = $clean($r->startDate);
        $end = $clean($r->endDate);

        $monthKey = strtolower($start->format('M'));
        $year = $start->year;

        // Get working days data
        $rekapLibur = RekapLiburKalender::where(['tahun' => $year, 'is_active' => true])->first();
        $workingDays = $rekapLibur ? json_decode($rekapLibur->tanggal, true) : [];

        // Process date range dan target calculation berdasarkan filter
        switch ($r->rangeFilter) {
            case 'Daily':
                $start = $start->startOfDay();
                $end = $end->endOfDay();
                $currentMonth = $start->format('Y-m');
                $monthWorkingDays = isset($workingDays[$currentMonth]) ? count($workingDays[$currentMonth]) : 0;
                $targetCalc = fn($t) => $monthWorkingDays > 0 ? ($t->$monthKey ?? 0) / $monthWorkingDays : 0;
                break;

            case 'Weekly':
                // $targetCalc = function ($t) use ($start, $end, $monthKey, $workingDays) {
                //     $currentMonth = $start->format('Y-m');
                //     $monthWorkingDays = isset($workingDays[$currentMonth]) ? count($workingDays[$currentMonth]) : 0;

                //     $dateRangeWorkingDays = 0;
                //     if (isset($workingDays[$currentMonth])) {
                //         foreach ($workingDays[$currentMonth] as $workingDate) {
                //             $date = Carbon::parse($workingDate);
                //             if ($date->between($start, $end)) $dateRangeWorkingDays++;
                //         }
                //     }

                //     dd($currentMonth, $monthWorkingDays, $dateRangeWorkingDays, $monthWorkingDays > 0 ? (($t->$monthKey ?? 0) / $monthWorkingDays) * $dateRangeWorkingDays : 0);
                //     return $monthWorkingDays > 0 ? (($t->$monthKey ?? 0) / $monthWorkingDays) * $dateRangeWorkingDays : 0;
                // };
                $targetCalc = function ($t) use ($start, $end, $monthKey, $workingDays) {
                    $currentMonth = $start->format('Y-m');
                    $daysInMonth = $workingDays[$currentMonth] ?? [];
                    $monthWorkingDays = count($daysInMonth);
                    $rangeDays = count(array_filter($daysInMonth, fn($d) => Carbon::parse($d)->between($start, $end)));

                    return $monthWorkingDays > 0 ? (($t->$monthKey ?? 0) / $monthWorkingDays) * $rangeDays : 0;
                };
                break;

            case 'Monthly':
                $start = $start->startOfMonth()->startOfDay();
                $end = $end->endOfMonth()->endOfDay();
                $targetCalc = fn($t) => $t->$monthKey ?? 0;
                break;

            case 'Yearly':
                $start = $start->startOfYear()->startOfDay();
                $end = $end->endOfYear()->endOfDay();
                $targetCalc = fn($t) => collect(['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'])->sum(fn($m) => $t->$m ?? 0);
                break;

            default:
                $targetCalc = fn() => 0;
        }

        // Get sales data dengan filter role
        $sales = MasterKaryawan::whereIn('id_jabatan', [21, 24])->where('is_active', true);

        $ids = [];
        $userId = $this->user_id;
        $roleId = $r->attributes->get('user')->karyawan->id_jabatan;

        if ($roleId == 24) { // SALES STAFF
            $ids = [$userId];
        } elseif (in_array($roleId, [21, 15])) { // SALES SPV & MANAGER
            $ids = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $userId)
                ->where('is_active', true)
                ->pluck('id')
                ->push($userId)
                ->toArray();
        }

        if (!empty($ids)) $sales->whereIn('id', $ids);

        $sales = $sales->orderBy('nama_lengkap')->get();

        // Setup card colors
        $cardColors = ['info', 'tosca', 'secondary', 'danger'];
        $spvSales = MasterKaryawan::where([
            'id_jabatan' => 21,
            'is_active' => true
        ])->orderBy('id')->pluck('id')->toArray();

        $spvColors = [];
        foreach ($spvSales as $index => $spvId) {
            $spvColors[$spvId] = $cardColors[$index % count($cardColors)];
        }

        // Assign card colors to sales
        foreach ($sales as &$s) {
            $spvId = null;
            $atasanLangsung = json_decode($s->atasan_langsung);

            if (isset($spvColors[$s->id])) {
                $spvId = $s->id;
            } else {
                foreach ($atasanLangsung as $atasanId) {
                    if (isset($spvColors[$atasanId])) {
                        $spvId = $atasanId;
                        break;
                    }
                }
            }

            $s->cardColor = $spvId && isset($spvColors[$spvId]) ? $spvColors[$spvId] : 'light';
        }

        // Calculate sales metrics
        $salesIds = $sales->pluck('id')->toArray();

        if (!empty($salesIds)) {
            // Get quotation data dengan UNION query
            $quotation = fn($model) => $model::select(['sales_id', 'pelanggan_ID', 'biaya_akhir', 'flag_status'])
                ->whereBetween('tanggal_penawaran', [$start, $end])
                ->whereIn('sales_id', $salesIds)
                ->where('is_active', true);

            $quotationData = $quotation(QuotationKontrakH::class)
                ->union($quotation(QuotationNonKontrak::class))
                ->get();

            // Get target data
            $targetData = TargetSales::whereIn('user_id', $salesIds)
                ->where('year', $year)
                ->where('is_active', true)
                ->get()
                ->keyBy('user_id');

            // Process metrics untuk setiap sales
            foreach ($sales as &$s) {
                $salesData = $quotationData->where('sales_id', $s->id);

                // Hitung metrics
                $s->count_penawaran = $salesData->count();
                $s->total_penawaran = $salesData->sum('biaya_akhir');

                $orderedData = $salesData->where('flag_status', 'ordered');
                $s->count_order = $orderedData->count();
                $s->total_order = $orderedData->sum('biaya_akhir');

                // Hitung repeat vs new orders
                $customerCounts = $orderedData->groupBy('pelanggan_ID')->map->count();
                $s->count_repeat_orders = $customerCounts->filter(fn($count) => $count >= 2)->count();
                $s->count_new_orders = $customerCounts->filter(fn($count) => $count == 1)->count();

                // Set target
                $target = $targetData->get($s->id);
                $s->target_sales = $target ? $targetCalc($target) : 0;
            }
        }

        return response()->json(['data' => $sales, 'statusCode' => 200]);
    }
}

<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use App\Models\{DailyQsd, MasterTargetSales};

class DashboardSalesController extends Controller
{
    public function index(Request $request)
    {
        // $this->user_id = 37;
        $userId = $this->user_id;
        $categoryStr = config('kategori.id');

        $indoMonths = [
            1 => 'januari',
            2 => 'februari',
            3 => 'maret',
            4 => 'april',
            5 => 'mei',
            6 => 'juni',
            7 => 'juli',
            8 => 'agustus',
            9 => 'september',
            10 => 'oktober',
            11 => 'november',
            12 => 'desember'
        ];

        // 1. Setup Tanggal
        $now = Carbon::create($request->year, $request->month, 1);
        $prevMonth = $now->copy()->subMonth();

        // 2. Query Data Sekaligus Tarik
        $dailyQsd = DailyQsd::with('orderHeader.orderDetail')
            ->where('sales_id', $userId)
            ->whereBetween('tanggal_kelompok', [
                $prevMonth->copy()->startOfMonth(),
                $now->copy()->endOfMonth()
            ])
            ->get();

        $currDailyQsd = $dailyQsd->where('tanggal_kelompok', '>=', $now->copy()->startOfMonth());
        $prevDailyQsd = $dailyQsd->where('tanggal_kelompok', '<', $now->copy()->startOfMonth());

        $calcGrowth = fn($curr, $prev) => $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : 0;

        // 3. Hitung Revenue
        $currRevenue = $currDailyQsd->sum('total_revenue');
        $prevRevenue = $prevDailyQsd->sum('total_revenue');
        $percentageRevenue = $calcGrowth($currRevenue, $prevRevenue);

        // 4. Hitung Target Kategori
        $targetSales = MasterTargetSales::where([
            'karyawan_id' => $userId,
            'is_active'   => true,
            'tahun'       => $now->year
        ])->latest()->first();

        $currAchieved = 0;
        $prevAchieved = 0;
        $currCountTarget = 0;

        if ($targetSales) {
            $currBulan = $indoMonths[$now->month];
            $prevBulan = $indoMonths[$prevMonth->month];

            $calcAchieved = function ($targets, $qsdData) use ($categoryStr) {
                $achieved = 0;
                $allDetails = $qsdData->flatMap(fn($q) => optional($q->orderHeader)->orderDetail ?? []);

                foreach ($targets as $cat => $targetVal) {
                    $allowed = $categoryStr[$cat] ?? [];

                    $count = $allDetails->whereIn('kategori_3', $allowed)->count();
                    $achieved += floor($count / $targetVal);
                }
                return $achieved;
            };

            // Hitung Current
            $currTargets = collect($targetSales->$currBulan ?? [])->filter(fn($val) => $val > 0);
            $currCountTarget = $currTargets->count();
            $currAchieved = $calcAchieved($currTargets, $currDailyQsd);

            // Hitung Previous
            $prevTargets = collect($targetSales->$prevBulan ?? [])->filter(fn($val) => $val > 0);
            $prevAchieved = $calcAchieved($prevTargets, $prevDailyQsd);
        }

        $targetKategori = $currAchieved . '/' . $currCountTarget;
        $percentageTargetKategori = $calcGrowth($currAchieved, $prevAchieved);

        // 5. Hitung Customer Status
        $currStatus = $currDailyQsd->countBy('status_customer');
        $prevStatus = $prevDailyQsd->countBy('status_customer');

        $newCustomers = $currStatus->get('new', 0);
        $percentageNewCustomers = $calcGrowth($newCustomers, $prevStatus->get('new', 0));

        $repeatCustomers = $currStatus->get('exist', 0);
        $percentageRepeatCustomers = $calcGrowth($repeatCustomers, $prevStatus->get('exist', 0));

        $period = $request->year . '-' . str_pad($request->month, 2, '0', STR_PAD_LEFT);
        $targetAmount = json_decode(optional($targetSales)->target ?? '{}', true)[$period] ?? 0;

        // 6. Hitung Revenue Trend
        $trendRaw = DailyQsd::where('sales_id', $userId)
            ->whereYear('tanggal_kelompok', $request->year)
            ->selectRaw('MONTH(tanggal_kelompok) as month, SUM(total_revenue) as total')
            ->groupBy(DB::raw('MONTH(tanggal_kelompok)'))
            ->pluck('total', 'month');

        Carbon::setLocale('id');

        $revenueTrend = collect(range(1, 12))
            ->map(fn($m) => [
                'month' => Carbon::create($request->year, $m, 1)->translatedFormat('M'),
                'revenue' => (int) $trendRaw->get($m, 0),
            ])
            ->values()
            ->toArray();

        // 6. Response Output
        return response()->json([
            'message' => 'Data retrieved successfully',
            'data' => [
                'total_revenue'               => $currRevenue,
                'percentage_revenue'          => $percentageRevenue,

                'target_kategori'             => $targetKategori,
                'percentage_target_kategori'  => $percentageTargetKategori,

                'new_customers'               => $newCustomers,
                'percentage_new_customers'    => $percentageNewCustomers,

                'repeat_customers'            => $repeatCustomers,
                'percentage_repeat_customers' => $percentageRepeatCustomers,

                'revenue'                     => $currRevenue,
                'target'                      => $targetAmount,

                'kontrak'                     => $currDailyQsd->where('kontrak', 'C')->sum('total_revenue'),
                'non_kontrak'                 => $currDailyQsd->where('kontrak', 'N')->sum('total_revenue'),

                'revenue_trend'               => $revenueTrend
            ],
        ], 200);
    }
}

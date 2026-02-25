<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Models\{DailyQsd, MasterTargetSales};

class DashboardSalesController extends Controller
{
    private $categoryStr;
    private $indoMonths = [
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

    public function __construct()
    {
        Carbon::setLocale('id');

        $this->categoryStr = config('kategori.id');
    }

    public function index(Request $request)
    {
        $karyawanId = $request->attributes->get('user')->karyawan->id;

        $date = Carbon::create($request->year, $request->month, 1);
        $currMonth = $date->month;
        $prevMonth = $date->subMonth()->month;

        $dailyQsd = DailyQsd::with('orderHeader.orderDetail')
            ->where('sales_id', $karyawanId)
            ->whereYear('tanggal_kelompok', $request->year)
            ->get()
            ->map(function ($qsd) {
                if ($qsd->periode) {
                    $orderDetail = optional($qsd->orderHeader)->orderDetail ? $qsd->orderHeader->orderDetail->filter(fn($od) => $od->periode === $qsd->periode)->values() : collect();
                    if ($orderDetail->isNotEmpty()) {
                        $qsd->orderHeader->setRelation('orderDetail', $orderDetail);
                    }
                }

                return $qsd;
            });

        $currQsd = $dailyQsd->filter(fn($qsd) => Carbon::parse($qsd->tanggal_kelompok)->month == $currMonth);
        $prevQsd = $dailyQsd->filter(fn($qsd) => Carbon::parse($qsd->tanggal_kelompok)->month == $prevMonth);

        $currRevenue = $currQsd->sum('total_revenue');
        $prevRevenue = $prevQsd->sum('total_revenue');

        $growthRevenue = $this->calculateGrowth($currRevenue, $prevRevenue);

        $targetSales = MasterTargetSales::where([
            'karyawan_id' => $karyawanId,
            'is_active'   => true,
            'tahun'       => $request->year
        ])->latest()->first();

        $currTarget = 0;
        $currAchieved = 0;
        $prevTarget = 0;
        $prevAchieved = 0;
        if ($targetSales) {
            $currTargetCategory = collect($targetSales->{$this->indoMonths[$currMonth]})->filter(fn($value) => $value > 0);

            $currAchievedCategory = $currTargetCategory->map(
                function ($_, $category) use ($currQsd, $currTargetCategory) {
                    $target = $currTargetCategory[$category];
                    $achieved = $currQsd->flatMap(fn($q) => optional($q->orderHeader)->orderDetail)->filter(fn($orderDetail) => collect($this->categoryStr[$category])->contains($orderDetail->kategori_3))->count();

                    return $target && $achieved ? floor($achieved / $target) : 0;
                }
            );

            $currAchieved = $currAchievedCategory->sum() == 0 ? 1 : $currAchievedCategory->sum();
            $currTarget = $currTargetCategory->count();

            $prevTargetCategory = collect($targetSales->{$this->indoMonths[$prevMonth]})->filter(fn($value) => $value > 0);

            $prevAchievedCategory = $prevTargetCategory->map(
                function ($_, $category) use ($prevQsd, $prevTargetCategory) {
                    $target = $prevTargetCategory[$category];
                    $achieved = $prevQsd->flatMap(fn($q) => optional($q->orderHeader)->orderDetail)->filter(fn($orderDetail) => collect($this->categoryStr[$category])->contains($orderDetail->kategori_3))->count();

                    return $target && $achieved ? floor($achieved / $target) : 0;
                }
            );

            $prevAchieved = $prevAchievedCategory->sum() == 0 ? 1 : $prevAchievedCategory->sum();
            $prevTarget = $prevTargetCategory->count();
        }
        $currTargetKategori = $currAchieved . '/' . $currTarget;
        $prevTargetKategori = $prevAchieved . '/' . $prevTarget;

        $growthTargetKategori = $this->calculateGrowth(
            $currTarget > 0 ? $currAchieved / $currTarget : 0,
            $prevTarget > 0 ? $prevAchieved / $prevTarget : 0
        );

        $currNewCustomer = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'new')->count();
        $prevNewCustomer = $prevQsd->filter(fn($qsd) => $qsd->status_customer == 'new')->count();

        $growthNewCustomer = $this->calculateGrowth($currNewCustomer, $prevNewCustomer);

        $currExistCustomer = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'exist')->count();
        $prevExistCustomer = $prevQsd->filter(fn($qsd) => $qsd->status_customer == 'exist')->count();

        $growthExistCustomer = $this->calculateGrowth($currExistCustomer, $prevExistCustomer);

        $period = Carbon::create($request->year, $request->month, 1)->format('Y-m');
        $target = json_decode(optional($targetSales)->target ?: '[]', true);
        $targetAmount = isset($target[$period]) ? $target[$period] : 0;

        $newCustomerRevenue = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'new')->sum('total_revenue');
        $existCustomerRevenue = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'exist')->sum('total_revenue');

        $kontrakRevenue = $currQsd->filter(fn($qsd) => $qsd->kontrak == 'C')->sum('total_revenue');
        $nonKontrakRevenue = $currQsd->filter(fn($qsd) => $qsd->kontrak == 'N')->sum('total_revenue');

        $yearlyRevenueTrend = collect(range(1, 12))
            ->map(fn($month) => [
                'month' => Carbon::create($request->year, $month, 1)->translatedFormat('M'),
                'revenue' => $dailyQsd->filter(fn($qsd) => Carbon::parse($qsd->tanggal_kelompok)->month == $month)->sum('total_revenue'),
            ]);

        return response()->json([
            'message' => 'Data retrieved successfully',
            'data' => [
                'total_revenue'               => $currRevenue,
                'percentage_revenue'          => $growthRevenue,

                'target_kategori'             => $currTargetKategori,
                'percentage_target_kategori'  => $growthTargetKategori,

                'new_customers'               => $currNewCustomer,
                'percentage_new_customers'    => $growthNewCustomer,

                'repeat_customers'            => $currExistCustomer,
                'percentage_repeat_customers' => $growthExistCustomer,

                'revenue'                     => $currRevenue,
                'target'                      => $targetAmount,

                'new'                         => $newCustomerRevenue,
                'existing'                    => $existCustomerRevenue,

                'kontrak'                     => $kontrakRevenue,
                'non_kontrak'                 => $nonKontrakRevenue,

                'revenue_trend'               => $yearlyRevenueTrend
            ],
        ], 200);
    }

    private function calculateGrowth($current, $previous)
    {
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;
    }
}

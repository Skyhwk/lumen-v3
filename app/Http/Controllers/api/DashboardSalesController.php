<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Models\{DailyQsd, DFUS, MasterTargetSales, QuotationKontrakH, QuotationNonKontrak};

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
        
        $karyawan = $request->attributes->get('user')->karyawan;
        $karyawanId = $karyawan->id;

        $date = Carbon::create($request->year, $request->month, 1);
        $currMonth = $date->month;
        $prevMonth = $date->copy()->subMonth()->month;

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

        $currentCallMetrics = $this->getCallMetrics(
            $karyawan->nama_lengkap,
            $date->copy()->startOfMonth(),
            $date->copy()->endOfMonth()
        );
        $previousDate = $date->copy()->subMonthNoOverflow();
        $previousCallMetrics = $this->getCallMetrics(
            $karyawan->nama_lengkap,
            $previousDate->copy()->startOfMonth(),
            $previousDate->copy()->endOfMonth()
        );

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

                'total_calls'                 => $currentCallMetrics['total_calls'],
                'percentage_total_calls'      => $this->calculateGrowth($currentCallMetrics['total_calls'], $previousCallMetrics['total_calls']),
                'pic_contacted'                => $currentCallMetrics['pic_contacted'],
                'percentage_pic_contacted'     => $this->calculateGrowth($currentCallMetrics['pic_contacted'], $previousCallMetrics['pic_contacted']),
                'unreachable'                  => $currentCallMetrics['unreachable'],
                'percentage_unreachable'       => $this->calculateGrowth($currentCallMetrics['unreachable'], $previousCallMetrics['unreachable']),
                'unqualified'                  => $currentCallMetrics['unqualified'],
                'percentage_unqualified'       => $this->calculateGrowth($currentCallMetrics['unqualified'], $previousCallMetrics['unqualified']),
                'success_rate'                 => $currentCallMetrics['success_rate'],
                'success_rate_change'          => round($currentCallMetrics['success_rate'] - $previousCallMetrics['success_rate'], 1),

                'revenue_trend'               => $yearlyRevenueTrend,
                'quotation_analytics'         => $this->getQuotationAnalytics($karyawanId, $date),
            ],
        ], 200);
    }

    private function calculateGrowth($current, $previous)
    {
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;
    }

    private function getQuotationAnalytics(int $salesId, Carbon $date): array
    {
        $quotes = collect([QuotationNonKontrak::class, QuotationKontrakH::class])
            ->flatMap(function ($model) use ($salesId, $date) {
                return $model::query()
                    ->where('is_active', 1)
                    ->where('sales_id', $salesId)
                    ->whereBetween('created_at', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])
                    ->get(['no_document', 'pelanggan_ID', 'flag_status', 'status_quotation', 'kode_promo', 'total_discount_promo', 'biaya_akhir', 'tanggal_penawaran']);
            })
            ->map(function ($quote) {
                $flag = strtolower(trim((string) $quote->flag_status));
                $status = strtolower(trim((string) $quote->status_quotation));

                return [
                    'pelanggan_id' => $quote->pelanggan_ID,
                    'category' => $flag === 'ordered' ? 'ordered' : ($flag === 'void' ? 'void' : 'pending'),
                    'status' => $flag === 'ordered' ? 'ordered'
                        : ($flag === 'void' ? 'void' : (in_array($status, ['cold', 'warm', 'hot'], true) ? $status : 'no_status')),
                    'amount' => (float) ($quote->biaya_akhir ?? 0),
                    'has_promo' => filled($quote->kode_promo) || (float) ($quote->total_discount_promo ?? 0) > 0,
                    'is_revisi' => (bool) preg_match('/R\d+$/i', (string) $quote->no_document),
                    'tanggal_penawaran' => $quote->tanggal_penawaran,
                ];
            })
            ->values();

        $customerIds = $quotes->pluck('pelanggan_id')->filter()->unique()->values();
        $firstOrders = $customerIds->isEmpty() ? collect() : \DB::table('order_header')
            ->where('is_active', 1)
            ->whereIn('id_pelanggan', $customerIds)
            ->selectRaw('id_pelanggan, MIN(tanggal_order) as first_order_date')
            ->groupBy('id_pelanggan')
            ->pluck('first_order_date', 'id_pelanggan');

        $quotes = $quotes->map(function (array $quote) use ($firstOrders) {
            $firstOrder = $firstOrders->get($quote['pelanggan_id']);
            $quote['customer_type'] = $firstOrder && Carbon::parse($firstOrder)->lt(Carbon::parse($quote['tanggal_penawaran']))
                ? 'repeat'
                : 'new';
            return $quote;
        });

        $summarize = fn($items) => ['qty' => $items->count(), 'amount' => (float) $items->sum('amount')];
        $breakdown = function ($items) use ($summarize) {
            return [
                'total' => $summarize($items),
                'customer' => ['new' => $summarize($items->where('customer_type', 'new')), 'repeat' => $summarize($items->where('customer_type', 'repeat'))],
                'promo' => ['with' => $summarize($items->where('has_promo', true)), 'without' => $summarize($items->where('has_promo', false))],
                'revision' => ['with' => $summarize($items->where('is_revisi', true)), 'without' => $summarize($items->where('is_revisi', false))],
            ];
        };

        return [
            'status' => collect(['cold', 'warm', 'hot', 'ordered', 'void', 'no_status'])
                ->mapWithKeys(fn($key) => [$key => $summarize($quotes->where('status', $key))])->all(),
            'pending' => $breakdown($quotes->where('category', 'pending')),
            'ordered' => $breakdown($quotes->where('category', 'ordered')),
        ];
    }

    private function getCallMetrics($salesName, Carbon $startDate, Carbon $endDate)
    {
        $statusCounts = DFUS::where('sales_penanggung_jawab', $salesName)
            ->whereBetween('tanggal', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->selectRaw("UPPER(TRIM(COALESCE(keterangan, ''))) as status, COUNT(*) as total")
            ->groupByRaw("UPPER(TRIM(COALESCE(keterangan, '')))")
            ->pluck('total', 'status');

        $totalCalls = (int) $statusCounts->sum();
        $picContacted = (int) $statusCounts->get('PIC', 0);
        $unreachable = (int) $statusCounts->get('NA', 0) + (int) $statusCounts->get('D', 0) + (int) $statusCounts->get('FO', 0);
        $unqualified = (int) $statusCounts->get('NI', 0);

        return [
            'total_calls' => $totalCalls,
            'pic_contacted' => $picContacted,
            'unreachable' => $unreachable,
            'unqualified' => $unqualified,
            'success_rate' => $totalCalls > 0 ? round(($picContacted / $totalCalls) * 100, 1) : 0,
        ];
    }
}

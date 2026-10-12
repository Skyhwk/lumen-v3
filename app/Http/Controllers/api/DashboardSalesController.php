<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Models\{DailyQsd, DFUS, MasterTargetSales, QuotationKontrakH, QuotationNonKontrak};
use App\Services\DashboardQuotationNoStatusService;

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

        $period = $this->resolveSalesDashboardPeriod($request);
        $startDate = $period['start'];
        $endDate = $period['end'];
        $prevStart = $period['prev_start'];
        $prevEnd = $period['prev_end'];
        $trendYear = (int) $startDate->year;

        $dailyQsd = $this->loadDailyQsdForDateRange(
            $karyawanId,
            $prevStart->lt($startDate) ? $prevStart : $startDate,
            $endDate
        );
        $currQsd = $this->filterQsdByDateRange($dailyQsd, $startDate, $endDate);
        $prevQsd = $this->filterQsdByDateRange($dailyQsd, $prevStart, $prevEnd);

        $currRevenue = $currQsd->sum('total_revenue');
        $prevRevenue = $prevQsd->sum('total_revenue');

        $growthRevenue = $this->calculateGrowth($currRevenue, $prevRevenue);

        $targetSales = MasterTargetSales::where([
            'karyawan_id' => $karyawanId,
            'is_active'   => true,
            'tahun'       => $trendYear,
        ])->latest()->first();

        $categoryMonth = (int) $startDate->month;
        $prevCategoryMonth = (int) $prevStart->month;

        $currCategoryMetrics = $this->buildCategoryTargetMetrics($targetSales, $currQsd, $categoryMonth);
        $prevCategoryMetrics = $this->buildCategoryTargetMetrics($targetSales, $prevQsd, $prevCategoryMonth);

        $currTargetKategori = $currCategoryMetrics['target_kategori'];
        $growthTargetKategori = $this->calculateGrowth(
            $currCategoryMetrics['ratio'],
            $prevCategoryMetrics['ratio']
        );

        $currNewCustomer = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'new')->count();
        $prevNewCustomer = $prevQsd->filter(fn($qsd) => $qsd->status_customer == 'new')->count();

        $growthNewCustomer = $this->calculateGrowth($currNewCustomer, $prevNewCustomer);

        $currExistCustomer = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'exist')->count();
        $prevExistCustomer = $prevQsd->filter(fn($qsd) => $qsd->status_customer == 'exist')->count();

        $growthExistCustomer = $this->calculateGrowth($currExistCustomer, $prevExistCustomer);

        $targetAmount = $this->resolveTargetAmount($targetSales, $startDate, $endDate, $period['type']);

        $newCustomerRevenue = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'new')->sum('total_revenue');
        $existCustomerRevenue = $currQsd->filter(fn($qsd) => $qsd->status_customer == 'exist')->sum('total_revenue');

        $kontrakRevenue = $currQsd->filter(fn($qsd) => $qsd->kontrak == 'C')->sum('total_revenue');
        $nonKontrakRevenue = $currQsd->filter(fn($qsd) => $qsd->kontrak == 'N')->sum('total_revenue');

        $currentCallMetrics = $this->getCallMetrics(
            $karyawan->nama_lengkap,
            $startDate,
            $endDate
        );
        $previousCallMetrics = $this->getCallMetrics(
            $karyawan->nama_lengkap,
            $prevStart,
            $prevEnd
        );

        $yearlyQsd = $this->loadDailyQsdForSales($karyawanId, $trendYear);
        $yearlyRevenueTrend = collect(range(1, 12))
            ->map(fn($month) => [
                'month' => Carbon::create($trendYear, $month, 1)->translatedFormat('M'),
                'revenue' => $yearlyQsd->filter(fn($qsd) => Carbon::parse($qsd->tanggal_kelompok)->month == $month)->sum('total_revenue'),
            ]);

        return response()->json([
            'message' => 'Data retrieved successfully',
            'data' => [
                'period_type'                 => $period['type'],
                'comparison_label'            => $period['comparison_label'],
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
                'quotation_analytics'         => $this->getQuotationAnalytics($karyawanId, $startDate, $endDate),
            ],
        ], 200);
    }

    public function categoryTargetDetail(Request $request)
    {
        $karyawan = $request->attributes->get('user')->karyawan;
        $karyawanId = $karyawan->id;

        $period = $this->resolveSalesDashboardPeriod($request);
        $startDate = $period['start'];
        $endDate = $period['end'];
        $currMonth = (int) $startDate->month;

        $dailyQsd = $this->loadDailyQsdForDateRange($karyawanId, $startDate, $endDate);
        $currQsd = $this->filterQsdByDateRange($dailyQsd, $startDate, $endDate);

        $targetSales = MasterTargetSales::where([
            'karyawan_id' => $karyawanId,
            'is_active'   => true,
            'tahun'       => (int) $startDate->year,
        ])->latest()->first();

        $metrics = $this->buildCategoryTargetMetrics($targetSales, $currQsd, $currMonth);

        return response()->json([
            'message' => 'Data retrieved successfully',
            'data'    => $metrics,
        ], 200);
    }

    private function loadDailyQsdForSales(int $karyawanId, int $year)
    {
        return DailyQsd::with('orderHeader.orderDetail')
            ->where('sales_id', $karyawanId)
            ->whereYear('tanggal_kelompok', $year)
            ->get()
            ->map(function ($qsd) {
                if ($qsd->periode) {
                    $orderDetail = optional($qsd->orderHeader)->orderDetail
                        ? $qsd->orderHeader->orderDetail->filter(fn($od) => $od->periode === $qsd->periode)->values()
                        : collect();
                    if ($orderDetail->isNotEmpty()) {
                        $qsd->orderHeader->setRelation('orderDetail', $orderDetail);
                    }
                }

                return $qsd;
            });
    }

    private function buildCategoryTargetMetrics($targetSales, $monthlyQsd, int $month): array
    {
        $empty = [
            'target_kategori'      => '0/0',
            'categories'           => [],
            'total_target'         => 0,
            'total_achieved'       => 0,
            'total_point'          => '0/0',
            'percentage_category'  => 0,
            'ratio'                => 0,
            'point_sum'            => 0,
            'category_count'       => 0,
        ];

        if (!$targetSales) {
            return $empty;
        }

        $monthKey = $this->indoMonths[$month];
        $targetByCategory = collect($targetSales->{$monthKey})->filter(fn($value) => $value > 0);

        if ($targetByCategory->isEmpty()) {
            return $empty;
        }

        $categories = [];
        $pointScores = $targetByCategory->map(function ($target, $category) use ($monthlyQsd, &$categories) {
            $achieved = $monthlyQsd
                ->flatMap(fn($q) => optional($q->orderHeader)->orderDetail)
                ->filter(fn($orderDetail) => collect($this->categoryStr[$category] ?? [])->contains($orderDetail->kategori_3))
                ->count();

            $point = $target && $achieved ? (int) floor($achieved / $target) : 0;

            $categories[] = [
                'category' => $category,
                'target'   => $target,
                'achieved' => $achieved,
                'point'    => $point,
            ];

            return $point;
        });

        $pointSum = $pointScores->sum() == 0 ? 1 : $pointScores->sum();
        $categoryCount = $targetByCategory->count();

        usort($categories, fn($a, $b) => strcmp($a['category'], $b['category']));

        return [
            'target_kategori'     => $pointSum . '/' . $categoryCount,
            'categories'          => $categories,
            'total_target'        => (int) $targetByCategory->sum(),
            'total_achieved'      => (int) collect($categories)->sum('achieved'),
            'total_point'         => $pointSum . '/' . $categoryCount,
            'percentage_category' => $categoryCount > 0 ? $pointSum / $categoryCount : 0,
            'ratio'               => $categoryCount > 0 ? $pointSum / $categoryCount : 0,
            'point_sum'           => $pointSum,
            'category_count'      => $categoryCount,
        ];
    }

    private function calculateGrowth($current, $previous)
    {
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;
    }

    private function getQuotationAnalytics(int $salesId, Carbon $startDate, Carbon $endDate): array
    {
        $quotes = collect([QuotationNonKontrak::class, QuotationKontrakH::class])
            ->flatMap(function ($model) use ($salesId, $startDate, $endDate) {
                return $model::query()
                    ->where('is_active', 1)
                    ->where('sales_id', $salesId)
                    ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
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
            'total' => $summarize($quotes),
            'pending' => $breakdown($quotes->where('category', 'pending')),
            'ordered' => $breakdown($quotes->where('category', 'ordered')),
        ];
    }

    private function resolveSalesDashboardPeriod(Request $request): array
    {
        $periodType = in_array($request->period_type, ['daily', 'yearly', 'range'], true)
            ? $request->period_type
            : 'monthly';

        if ($periodType === 'daily') {
            $start = Carbon::parse($request->date ?: $request->start_date ?: Carbon::now())->startOfDay();
            $end = $start->copy()->endOfDay();
            $prevStart = $start->copy()->subDay()->startOfDay();
            $prevEnd = $prevStart->copy()->endOfDay();
            $comparisonLabel = $prevStart->translatedFormat('j F Y');
        } elseif ($periodType === 'yearly') {
            $year = (int) ($request->year ?: Carbon::now()->year);
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end = $start->copy()->endOfYear();
            $prevStart = $start->copy()->subYear()->startOfYear();
            $prevEnd = $prevStart->copy()->endOfYear();
            $comparisonLabel = (string) ($year - 1);
        } elseif ($periodType === 'range') {
            $start = Carbon::parse($request->start_date ?: Carbon::now())->startOfDay();
            $end = Carbon::parse($request->end_date ?: $request->start_date ?: Carbon::now())->endOfDay();
            if ($end->lt($start)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }
            $dayCount = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
            $prevEnd = $start->copy()->subDay()->endOfDay();
            $prevStart = $prevEnd->copy()->subDays($dayCount - 1)->startOfDay();
            $comparisonLabel = 'periode sebelumnya';
        } else {
            $year = (int) ($request->year ?: Carbon::now()->year);
            $month = (int) ($request->month ?: Carbon::now()->month);
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $prevStart = $start->copy()->subMonth()->startOfMonth();
            $prevEnd = $prevStart->copy()->endOfMonth();
            $comparisonLabel = $prevStart->translatedFormat('F');
        }

        return [
            'type' => $periodType,
            'start' => $start,
            'end' => $end,
            'prev_start' => $prevStart,
            'prev_end' => $prevEnd,
            'comparison_label' => $comparisonLabel,
        ];
    }

    private function loadDailyQsdForDateRange(int $karyawanId, Carbon $startDate, Carbon $endDate)
    {
        $years = range((int) $startDate->year, (int) $endDate->year);

        return collect($years)
            ->flatMap(fn (int $year) => $this->loadDailyQsdForSales($karyawanId, $year))
            ->values();
    }

    private function filterQsdByDateRange($dailyQsd, Carbon $startDate, Carbon $endDate)
    {
        $from = $startDate->copy()->startOfDay();
        $to = $endDate->copy()->endOfDay();

        return $dailyQsd->filter(function ($qsd) use ($from, $to) {
            $tanggal = Carbon::parse($qsd->tanggal_kelompok);

            return $tanggal->betweenIncluded($from, $to);
        });
    }

    private function resolveTargetAmount($targetSales, Carbon $startDate, Carbon $endDate, string $periodType): float
    {
        $target = json_decode(optional($targetSales)->target ?: '[]', true);
        if (!is_array($target)) {
            return 0;
        }

        if ($periodType === 'monthly') {
            return (float) ($target[$startDate->format('Y-m')] ?? 0);
        }

        if ($periodType === 'daily') {
            $monthly = (float) ($target[$startDate->format('Y-m')] ?? 0);

            return $monthly > 0 ? round($monthly / $startDate->daysInMonth, 2) : 0;
        }

        if ($periodType === 'yearly') {
            $sum = 0;
            for ($month = 1; $month <= 12; $month++) {
                $key = Carbon::create($startDate->year, $month, 1)->format('Y-m');
                $sum += (float) ($target[$key] ?? 0);
            }

            return $sum;
        }

        $sum = 0;
        $cursor = $startDate->copy()->startOfDay();
        $last = $endDate->copy()->startOfDay();
        while ($cursor->lte($last)) {
            $monthly = (float) ($target[$cursor->format('Y-m')] ?? 0);
            if ($monthly > 0) {
                $sum += $monthly / $cursor->daysInMonth;
            }
            $cursor->addDay();
        }

        return round($sum, 2);
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

    public function quotationNoStatusList(Request $request)
    {
        try {
            $karyawanId = (int) $request->attributes->get('user')->karyawan->id;
            $period = $this->resolveSalesDashboardPeriod($request);
            $service = app(DashboardQuotationNoStatusService::class);
            $startDate = $period['start']->copy()->startOfDay();
            $endDate = $period['end']->copy()->endOfDay();

            if ($request->has('draw')) {
                return response()->json($service->datatable($request, [$karyawanId], $startDate, $endDate));
            }

            $rows = $service->list([$karyawanId], $startDate, $endDate);

            return response()->json([
                'message' => 'Data retrieved successfully',
                'data' => $rows,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil daftar quotation tanpa status.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}

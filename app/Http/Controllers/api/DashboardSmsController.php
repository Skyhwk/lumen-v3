<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DFUS;
use App\Models\MasterKaryawan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SalesKpi;
use App\Models\TargetSales;
use App\Models\SamplingPlan;
use App\Services\ForecastSpAggregate;
use App\Services\SalesTeamHierarchyService;
use Carbon\Carbon;
use Illuminate\Http\Request;

Carbon::setLocale('id');

class DashboardSmsController extends Controller
{
    public function index(Request $request)
    {
        $startDate = Carbon::now()->startOfDay();
        $endDate   = Carbon::now()->endOfDay();

        if ($request->rangeFilter == 'Weekly') {
            $startDate = Carbon::now()->startOfWeek()->startOfDay();
            $endDate   = Carbon::now()->endOfWeek()->endOfDay();
        }

        if ($request->rangeFilter == 'Monthly') {
            $startDate = Carbon::now()->startOfMonth()->startOfDay();
            $endDate   = Carbon::now()->endOfMonth()->endOfDay();
        }

        $query = collect([QuotationKontrakH::class, QuotationNonKontrak::class])
            ->flatMap(function ($model) use ($request, $startDate, $endDate) {
                $subQuery = $model::where('is_active', true)->whereBetween('tanggal_penawaran', [$startDate, $endDate]);
                $jabatan  = $request->attributes->get('user')->karyawan->id_jabatan;
                if ($jabatan == 24) {
                    $subQuery->where('sales_id', $this->user_id);
                } else if ($jabatan == 21) {
                    $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                    array_push($bawahan, $this->user_id);

                    $subQuery->whereIn('sales_id', $bawahan);
                }

                return $subQuery->get();
            });

        return response()->json([
            'quoted'    => $query->count(),
            'ordered'   => $query->where('flag_status', 'ordered')->count(),
            'unordered' => $query->where('flag_status', '!=', 'ordered')->count(),
            'rejected'  => $query->whereIn('flag_status', ['rejected', 'void'])->count(),
        ], 200);
    }

    public function targetSales(Request $request)
    {
        $now = Carbon::now();

        $rangeFilter = $request->rangeFilter;

        $startDate = $rangeFilter == 'Weekly' ? $now->copy()->startOfWeek() : ($rangeFilter == 'Monthly' ? $now->copy()->startOfMonth() : $now->copy())->startOfDay();
        $endDate   = $rangeFilter == 'Weekly' ? $now->copy()->endOfWeek() : ($rangeFilter == 'Monthly' ? $now->copy()->endOfMonth() : $now->copy())->endOfDay();

        // Tentukan sales IDs
        $jabatan  = $request->attributes->get('user')->karyawan->id_jabatan;
        $salesIds = $jabatan == 24
            ? [$this->user_id]
            : ($jabatan == 21
                ? array_merge(MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)->where('is_active', true)->pluck('id')->toArray(), [$this->user_id])
                : MasterKaryawan::where('is_active', true)->where(fn($q) => $q->whereIn('id_jabatan', [24, 21])->orWhere('id', 41))->pluck('id')->toArray()
        );

        // Get data
        $sales   = MasterKaryawan::whereIn('id', $salesIds)->where('is_active', true)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']);
        $targets = TargetSales::whereIn('user_id', $salesIds)->where('is_active', true)->where('year', $now->format('Y'))->get()->keyBy('user_id');

        $kontrakTotals    = QuotationKontrakH::whereIn('sales_id', $salesIds)->where('is_active', true)->whereBetween('tanggal_penawaran', [$startDate, $endDate])->selectRaw('sales_id, SUM(biaya_akhir) as total')->groupBy('sales_id')->get()->keyBy('sales_id');
        $nonKontrakTotals = QuotationNonKontrak::whereIn('sales_id', $salesIds)->where('is_active', true)->whereBetween('tanggal_penawaran', [$startDate, $endDate])->selectRaw('sales_id, SUM(biaya_akhir) as total')->groupBy('sales_id')->get()->keyBy('sales_id');

        $currentMonth = strtolower($now->format('M'));

        // Build response
        $result = [];
        foreach ($sales as $s) {
            $result[] = [
                'name'   => $s->nama_lengkap,
                'target' => isset($targets[$s->id]) && isset($targets[$s->id]->$currentMonth) ? $targets[$s->id]->$currentMonth : 0,
                'actual' => (isset($kontrakTotals[$s->id]) ? $kontrakTotals[$s->id]->total : 0) + (isset($nonKontrakTotals[$s->id]) ? $nonKontrakTotals[$s->id]->total : 0),
            ];
        }

        return response()->json($result);
    }

    public function getSales(Request $request)
    {
        $payload = app(SalesTeamHierarchyService::class)->getSelectPayload();

        return response()->json([
            'executives' => $payload['executives'],
            'branches'   => $payload['branches'],
            'message'    => 'Sales data retrieved successfully',
        ], 200);
    }

    public function fetchAll(Request $request)
    {
        try {
            $bulan = [
                'Januari'   => '01', 'Februari' => '02', 'Maret'    => '03', 'April'    => '04',
                'Mei'       => '05', 'Juni'     => '06', 'Juli'     => '07', 'Agustus'  => '08',
                'September' => '09', 'Oktober'  => '10', 'November' => '11', 'Desember' => '12',
            ];

            $months = [
                "Jan" => "01",
                "Feb" => "02",
                "Mar" => "03",
                "Apr" => "04",
                "May" => "05",
                "Jun" => "06",
                "Jul" => "07",
                "Aug" => "08",
                "Sep" => "09",
                "Oct" => "10",
                "Nov" => "11",
                "Dec" => "12",
            ];

            $arr     = explode(' ', $request->periode);
            $periode = (count($arr) == 2 && isset($bulan[$arr[0]])) ? $arr[1] . '-' . $bulan[$arr[0]] : null;

            // ==========================COLLECT DATA SP=====================
            $samplingPlans = SamplingPlan::query()
                ->select([
                    'id',
                    'no_quotation',
                    'periode_kontrak',
                    'status_quotation',
                ])
                ->with([
                    'quotation:id,no_document,biaya_akhir',

                    'quotationKontrak:id,no_document',

                    'quotationKontrak.detail:id,id_request_quotation_kontrak_h,periode_kontrak,biaya_akhir',
                ])
                ->where('is_active', true)
                ->where('status', 0)
                ->where('is_approved', 0)
                ->orderByDesc('id')
                ->get();

            $cekSp = [];
            foreach ($samplingPlans as $samplingPlan) {
                $dataSp = null;
                $isKontrak = false;
                if ($samplingPlan->quotationKontrak) {
                    $isKontrak = true;
                    $dataSp = $samplingPlan->quotationKontrak
                        ->detail
                        ->firstWhere(
                            'periode_kontrak',
                            $samplingPlan->periode_kontrak
                        );
                } else {
                    $dataSp = $samplingPlan->quotation;
                }

                $cekSp[] = [
                    'no_qt' => $samplingPlan->no_quotation,
                    'status_quotation' => $isKontrak ? 'kontrak' : 'non_kontrak',
                    'periode' => $samplingPlan->periode_kontrak,
                    'biaya_akhir' => $dataSp->biaya_akhir ?? 0,
                ];
            }
            
            $qtySp = count($cekSp);
            $amountSp = array_sum(array_column($cekSp, 'biaya_akhir'));
            $amountKontrakSp = array_sum(
                array_column(
                    array_filter($cekSp, function ($item) {
                        return $item['status_quotation'] == 'kontrak';
                    }),
                    'biaya_akhir'
                )
            );
            $amountNonKontrakSp = array_sum(
                array_column(
                    array_filter($cekSp, function ($item) {
                        return $item['status_quotation'] == 'non_kontrak';
                    }),
                    'biaya_akhir'
                )
            );

            if ($request->mode == "all") {
                $tahun = explode('-', $periode)[0];

                $cek = \DB::table('sales_kpi_monthly')
                    ->selectRaw("
                        SUM(dfus_call) as dfus_call,
                        SUM(duration) as duration,
                        SUM(qty_qt_nonkontrak_new) as qty_qt_nonkontrak_new,
                        SUM(qty_qt_nonkontrak_exist) as qty_qt_nonkontrak_exist,
                        SUM(qty_qt_kontrak_new) as qty_qt_kontrak_new,
                        SUM(qty_qt_kontrak_exist) as qty_qt_kontrak_exist,
                        SUM(qty_qt_order_nonkontrak_new) as qty_qt_order_nonkontrak_new,
                        SUM(qty_qt_order_nonkontrak_exist) as qty_qt_order_nonkontrak_exist,
                        SUM(qty_qt_order_kontrak_new) as qty_qt_order_kontrak_new,
                        SUM(qty_qt_order_kontrak_exist) as qty_qt_order_kontrak_exist,
                        SUM(amount_order_nonkontrak_new) as amount_order_nonkontrak_new,
                        SUM(amount_order_nonkontrak_exist) as amount_order_nonkontrak_exist,
                        SUM(amount_order_kontrak_new) as amount_order_kontrak_new,
                        SUM(amount_order_kontrak_exist) as amount_order_kontrak_exist,
                        SUM(amount_bysampling_order_nonkontrak_new) as amount_bysampling_order_nonkontrak_new,
                        SUM(amount_bysampling_order_nonkontrak_exist) as amount_bysampling_order_nonkontrak_exist,
                        SUM(amount_bysampling_order_kontrak_new) as amount_bysampling_order_kontrak_new,
                        SUM(amount_bysampling_order_kontrak_exist) as amount_bysampling_order_kontrak_exist,
                        SUM(revenue_order_nonkontrak_new) as revenue_order_nonkontrak_new,
                        SUM(revenue_order_nonkontrak_exist) as revenue_order_nonkontrak_exist,
                        SUM(revenue_order_kontrak_new) as revenue_order_kontrak_new,
                        SUM(revenue_order_kontrak_exist) as revenue_order_kontrak_exist,
                        SUM(revenue_bysampling_order_nonkontrak_new) as revenue_bysampling_order_nonkontrak_new,
                        SUM(revenue_bysampling_order_nonkontrak_exist) as revenue_bysampling_order_nonkontrak_exist,
                        SUM(revenue_bysampling_order_kontrak_new) as revenue_bysampling_order_kontrak_new,
                        SUM(revenue_forecast_nonkontrak_new) as revenue_forecast_nonkontrak_new,
                        SUM(revenue_forecast_nonkontrak_exist) as revenue_bysampling_order_kontrak_exist,
                        SUM(revenue_forecast_kontrak_new) as revenue_forecast_kontrak_new,
                        SUM(revenue_forecast_kontrak_exist) as revenue_forecast_kontrak_exist
                    ")
                    ->where('periode', $periode)
                    ->first();

                $daily_qsd = \DB::table('daily_qsd')
                    ->selectRaw("
                        SUM(CASE
                            WHEN DATE_FORMAT(tanggal_sampling_min, '%Y-%m') = ?
                            THEN total_revenue ELSE 0 END) AS total_revenue_new,

                        SUM(CASE
                            WHEN DATE_FORMAT(tanggal_sampling_min, '%Y-%m') = ?
                            THEN biaya_akhir ELSE 0 END) AS total_ordered_new,

                        SUM(CASE
                            WHEN DATE_FORMAT(tanggal_sampling_min, '%Y-%m') < ?
                            THEN total_revenue ELSE 0 END) AS total_revenue_exist,

                        SUM(CASE
                            WHEN DATE_FORMAT(tanggal_sampling_min, '%Y-%m') < ?
                            THEN biaya_akhir ELSE 0 END) AS total_ordered_exist
                    ", [$periode, $periode, $periode, $periode])
                    ->first();

                // dd($cek, $daily_qsd);

                // Gunakan logika tampilan yang sama
                $return = [
                    ["title" => "DFUS Contacted", "value" => (($cek->dfus_call ?? 0) . " Calls"), "color" => "primary", "info" => (function ($d) {$d = (int) ($d ?? 0);if ($d >= 3600) {$h = floor($d / 3600); $m = floor(($d % 3600) / 60);return "{$h} Hours\n{$m} Minutes";} else { $m = floor($d / 60); $s = $d % 60;return "{$m} Minutes\n{$s} Seconds";}})($cek->duration ?? 0)],
                    ["title" => "Total Quote", "value" => (($cek->qty_qt_nonkontrak_new ?? 0) + ($cek->qty_qt_nonkontrak_exist ?? 0) + ($cek->qty_qt_kontrak_new ?? 0) + ($cek->qty_qt_kontrak_exist ?? 0)) . " Quotes", "color" => "info", "info" => "Exist : " . (($cek->qty_qt_nonkontrak_exist ?? 0) + ($cek->qty_qt_kontrak_exist ?? 0)) . " \nNew : " . (($cek->qty_qt_nonkontrak_new ?? 0) + ($cek->qty_qt_kontrak_new ?? 0))],
                    ["title" => "Quote Ordered", "value" => (($cek->qty_qt_order_nonkontrak_new ?? 0) + ($cek->qty_qt_order_nonkontrak_exist ?? 0) + ($cek->qty_qt_order_kontrak_new ?? 0) + ($cek->qty_qt_order_kontrak_exist ?? 0)) . " QS", "color" => "success", "info" => "Exist : " . (($cek->qty_qt_order_nonkontrak_exist ?? 0) + ($cek->qty_qt_order_kontrak_exist ?? 0)) . " \nNew : " . (($cek->qty_qt_order_nonkontrak_new ?? 0) + ($cek->qty_qt_order_kontrak_new ?? 0))],
                    ["title" => "Ordered (Amount)", "value" => "Rp " . number_format(($cek->amount_order_nonkontrak_new ?? 0) + ($cek->amount_order_nonkontrak_exist ?? 0) + ($cek->amount_order_kontrak_new ?? 0) + ($cek->amount_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "danger", "info" => "Exist : Rp " . number_format(($cek->amount_order_nonkontrak_exist ?? 0) + ($cek->amount_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->amount_order_nonkontrak_new ?? 0) + ($cek->amount_order_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Revenue", "value" => "Rp " . number_format(($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_new ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Forecast", "value" => "Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_bysampling_order_kontrak_exist ?? 0) + ($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Total Revenue + Forecast", "value" => "Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_bysampling_order_kontrak_exist ?? 0) + ($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_new ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Unscheduled QT", "value" => "Rp " . number_format($amountSp, 0, ',', '.'), "color" => "warning", "info" => "Total SP : " . $qtySp . " \nQT Kontrak : Rp " . number_format($amountKontrakSp, 0, ',', '.') . " \nQT Non Kontrak : Rp " . number_format($amountNonKontrakSp, 0, ',', '.')],
                    // [ "title" => "Revenue (By Sampling)", "value" => "Rp " . number_format(($cek->revenue_bysampling_order_nonkontrak_new ?? 0)+($cek->revenue_bysampling_order_nonkontrak_exist ?? 0)+($cek->revenue_bysampling_order_kontrak_new ?? 0)+($cek->revenue_bysampling_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "secondary", "info" => "Exist : Rp " . number_format(($cek->revenue_bysampling_order_nonkontrak_exist ?? 0)+($cek->revenue_bysampling_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_bysampling_order_nonkontrak_new ?? 0)+($cek->revenue_bysampling_order_kontrak_new ?? 0), 0, ',', '.') ]
                ];

                $tahun  = explode('-', $periode)[0];
                $allKpi = SalesKpi::where('periode', 'like', $tahun . '-%')
                    ->selectRaw('periode,
                        SUM(revenue_order_nonkontrak_new) as revenue_order_nonkontrak_new,
                        SUM(revenue_order_nonkontrak_exist) as revenue_order_nonkontrak_exist,
                        SUM(revenue_order_kontrak_new) as revenue_order_kontrak_new,
                        SUM(revenue_order_kontrak_exist) as revenue_order_kontrak_exist
                    ')
                    ->groupBy('periode')
                    ->get()
                    ->keyBy(function ($item) {
                        return $item->periode;
                    });

                $chart = [];
                foreach ($months as $mnthName => $mnthNum) {
                    $periodeKey = $tahun . '-' . $mnthNum;
                    if (isset($allKpi[$periodeKey])) {
                        $item  = $allKpi[$periodeKey];
                        $value =
                            ($item->revenue_order_nonkontrak_new ?? 0) +
                            ($item->revenue_order_nonkontrak_exist ?? 0) +
                            ($item->revenue_order_kontrak_new ?? 0) +
                            ($item->revenue_order_kontrak_exist ?? 0);
                    } else {
                        $value = null;
                    }
                    $chart[] = [
                        'month' => $mnthName,
                        'value' => $value,
                    ];
                }

                $sumall = SalesKpi::where('periode', $periode)
                    ->selectRaw('SUM(revenue_order_nonkontrak_new) as revenue_order_nonkontrak_new,
                        SUM(revenue_order_nonkontrak_exist) as revenue_order_nonkontrak_exist,
                        SUM(revenue_order_kontrak_new) as revenue_order_kontrak_new,
                        SUM(revenue_order_kontrak_exist) as revenue_order_kontrak_exist
                    ')
                    ->first();

                $piechart = [
                    'value' => $sumall->revenue_order_nonkontrak_new + $sumall->revenue_order_nonkontrak_exist + $sumall->revenue_order_kontrak_new + $sumall->revenue_order_kontrak_exist,
                    'new'   => $sumall->revenue_order_nonkontrak_new + $sumall->revenue_order_kontrak_new,
                    'exist' => $sumall->revenue_order_nonkontrak_exist + $sumall->revenue_order_kontrak_exist,
                ];

                [$hierarchyRows, $hierarchyIds] = $this->prepareHierarchyRows($periode);
                $return                        = $this->applyForecastHeading($return, $periode, $hierarchyIds, $cek);
                $return                        = $this->enrichRevenueHeadingWithSampling($return, $periode, null);
                $table                         = $this->mergeHierarchyWithKpi($periode, $hierarchyRows);

                $years = [
                    $tahun - 1,
                    $tahun,
                ];

                $allKpiBar = SalesKpi::where(function ($q) use ($years) {
                    foreach ($years as $yr) {
                        $q->orWhere('periode', 'like', $yr . '-%');
                    }
                })
                    ->selectRaw('periode,
                        SUM(revenue_order_nonkontrak_new) as revenue_order_nonkontrak_new,
                        SUM(revenue_order_nonkontrak_exist) as revenue_order_nonkontrak_exist,
                        SUM(revenue_order_kontrak_new) as revenue_order_kontrak_new,
                        SUM(revenue_order_kontrak_exist) as revenue_order_kontrak_exist
                    ')
                    ->groupBy('periode')
                    ->get()
                    ->keyBy('periode');

                $result = [];

                foreach ($years as $yr) {
                    $chartBar = [];

                    foreach ($months as $mnthName => $mnthNum) {
                        $periodeKey = $yr . '-' . $mnthNum;

                        if (isset($allKpiBar[$periodeKey])) {
                            $item  = $allKpiBar[$periodeKey];
                            $value =
                                ($item->revenue_order_nonkontrak_new ?? 0) +
                                ($item->revenue_order_nonkontrak_exist ?? 0) +
                                ($item->revenue_order_kontrak_new ?? 0) +
                                ($item->revenue_order_kontrak_exist ?? 0);
                        } else {
                            $value = null;
                        }

                        $chartBar[] = [
                            'month' => $mnthName,
                            'value' => $value,
                        ];
                    }

                    $result[$yr] = $chartBar;
                }

                return response()->json([
                    'heading'  => $return,
                    'table'    => $table,
                    'rankings' => $this->buildDailyQsdRankings($periode),
                    'chart'    => $chart,
                    'piechart' => $piechart,
                    'chartBar' => $result
                ], 200);

            } else if (strpos($request->mode, "team") !== false) {
                $teamRootId = (int) str_replace('team_', '', $request->karyawan_id);
                $bawahanIds = app(SalesTeamHierarchyService::class)->resolveDescendantIds($teamRootId);

                $tahun = explode('-', $periode)[0];

                $cek = \DB::table('sales_kpi_monthly')
                    ->selectRaw("
                        SUM(dfus_call) as dfus_call,
                        SUM(duration) as duration,
                        SUM(qty_qt_nonkontrak_new) as qty_qt_nonkontrak_new,
                        SUM(qty_qt_nonkontrak_exist) as qty_qt_nonkontrak_exist,
                        SUM(qty_qt_kontrak_new) as qty_qt_kontrak_new,
                        SUM(qty_qt_kontrak_exist) as qty_qt_kontrak_exist,
                        SUM(qty_qt_order_nonkontrak_new) as qty_qt_order_nonkontrak_new,
                        SUM(qty_qt_order_nonkontrak_exist) as qty_qt_order_nonkontrak_exist,
                        SUM(qty_qt_order_kontrak_new) as qty_qt_order_kontrak_new,
                        SUM(qty_qt_order_kontrak_exist) as qty_qt_order_kontrak_exist,
                        SUM(amount_order_nonkontrak_new) as amount_order_nonkontrak_new,
                        SUM(amount_order_nonkontrak_exist) as amount_order_nonkontrak_exist,
                        SUM(amount_order_kontrak_new) as amount_order_kontrak_new,
                        SUM(amount_order_kontrak_exist) as amount_order_kontrak_exist,
                        SUM(amount_bysampling_order_nonkontrak_new) as amount_bysampling_order_nonkontrak_new,
                        SUM(amount_bysampling_order_nonkontrak_exist) as amount_bysampling_order_nonkontrak_exist,
                        SUM(amount_bysampling_order_kontrak_new) as amount_bysampling_order_kontrak_new,
                        SUM(amount_bysampling_order_kontrak_exist) as amount_bysampling_order_kontrak_exist,
                        SUM(revenue_order_nonkontrak_new) as revenue_order_nonkontrak_new,
                        SUM(revenue_order_nonkontrak_exist) as revenue_order_nonkontrak_exist,
                        SUM(revenue_order_kontrak_new) as revenue_order_kontrak_new,
                        SUM(revenue_order_kontrak_exist) as revenue_order_kontrak_exist,
                        SUM(revenue_bysampling_order_nonkontrak_new) as revenue_bysampling_order_nonkontrak_new,
                        SUM(revenue_bysampling_order_nonkontrak_exist) as revenue_bysampling_order_nonkontrak_exist,
                        SUM(revenue_bysampling_order_kontrak_new) as revenue_bysampling_order_kontrak_new,
                        SUM(revenue_bysampling_order_kontrak_exist) as revenue_bysampling_order_kontrak_exist,
                        SUM(revenue_forecast_nonkontrak_new) as revenue_forecast_nonkontrak_new,
                        SUM(revenue_forecast_nonkontrak_exist) as revenue_bysampling_order_kontrak_exist,
                        SUM(revenue_forecast_kontrak_new) as revenue_forecast_kontrak_new,
                        SUM(revenue_forecast_kontrak_exist) as revenue_forecast_kontrak_exist
                    ")
                    ->where('periode', $periode)
                    ->whereIn('karyawan_id', $bawahanIds)
                    ->first();

                // Gunakan logika tampilan yang sama
                $return = [
                    ["title" => "DFUS Contacted", "value" => (($cek->dfus_call ?? 0) . " Calls"), "color" => "primary", "info" => (function ($d) {$d = (int) ($d ?? 0);if ($d >= 3600) {$h = floor($d / 3600); $m = floor(($d % 3600) / 60);return "{$h} Hours\n{$m} Minutes";} else { $m = floor($d / 60); $s = $d % 60;return "{$m} Minutes\n{$s} Seconds";}})($cek->duration ?? 0)],
                    ["title" => "Total Quote", "value" => (($cek->qty_qt_nonkontrak_new ?? 0) + ($cek->qty_qt_nonkontrak_exist ?? 0) + ($cek->qty_qt_kontrak_new ?? 0) + ($cek->qty_qt_kontrak_exist ?? 0)) . " Quotes", "color" => "info", "info" => "Exist : " . (($cek->qty_qt_nonkontrak_exist ?? 0) + ($cek->qty_qt_kontrak_exist ?? 0)) . " \nNew : " . (($cek->qty_qt_nonkontrak_new ?? 0) + ($cek->qty_qt_kontrak_new ?? 0))],
                    ["title" => "Quote Ordered", "value" => (($cek->qty_qt_order_nonkontrak_new ?? 0) + ($cek->qty_qt_order_nonkontrak_exist ?? 0) + ($cek->qty_qt_order_kontrak_new ?? 0) + ($cek->qty_qt_order_kontrak_exist ?? 0)) . " QS", "color" => "success", "info" => "Exist : " . (($cek->qty_qt_order_nonkontrak_exist ?? 0) + ($cek->qty_qt_order_kontrak_exist ?? 0)) . " \nNew : " . (($cek->qty_qt_order_nonkontrak_new ?? 0) + ($cek->qty_qt_order_kontrak_new ?? 0))],
                    ["title" => "Ordered (Amount)", "value" => "Rp " . number_format(($cek->amount_order_nonkontrak_new ?? 0) + ($cek->amount_order_nonkontrak_exist ?? 0) + ($cek->amount_order_kontrak_new ?? 0) + ($cek->amount_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "danger", "info" => "Exist : Rp " . number_format(($cek->amount_order_nonkontrak_exist ?? 0) + ($cek->amount_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->amount_order_nonkontrak_new ?? 0) + ($cek->amount_order_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Revenue", "value" => "Rp " . number_format(($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_new ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Forecast", "value" => "Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Total Revenue + Forecast", "value" => "Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_new ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Unscheduled QT", "value" => "Rp " . number_format($amountSp, 0, ',', '.'), "color" => "warning", "info" => "Total SP : " . $qtySp . " \nQT Kontrak : Rp " . number_format($amountKontrakSp, 0, ',', '.') . " \nQT Non Kontrak : Rp " . number_format($amountNonKontrakSp, 0, ',', '.')],
                    // ["title" => "Revenue (By Sampling)", "value" => "Rp " . number_format(($cek->revenue_bysampling_order_nonkontrak_new ?? 0) + ($cek->revenue_bysampling_order_nonkontrak_exist ?? 0) + ($cek->revenue_bysampling_order_kontrak_new ?? 0) + ($cek->revenue_bysampling_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "secondary", "info" => "Exist : Rp " . number_format(($cek->revenue_bysampling_order_nonkontrak_exist ?? 0) + ($cek->revenue_bysampling_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_bysampling_order_nonkontrak_new ?? 0) + ($cek->revenue_bysampling_order_kontrak_new ?? 0), 0, ',', '.')],
                ];

                $tahun  = explode('-', $periode)[0];
                $allKpi = SalesKpi::where('periode', 'like', $tahun . '-%')
                    ->selectRaw('periode,
                        SUM(revenue_order_nonkontrak_new) as revenue_order_nonkontrak_new,
                        SUM(revenue_order_nonkontrak_exist) as revenue_order_nonkontrak_exist,
                        SUM(revenue_order_kontrak_new) as revenue_order_kontrak_new,
                        SUM(revenue_order_kontrak_exist) as revenue_order_kontrak_exist
                    ')
                    ->whereIn('karyawan_id', $bawahanIds)
                    ->groupBy('periode')
                    ->get()
                    ->keyBy(function ($item) {
                        return $item->periode;
                    });

                $chart = [];
                foreach ($months as $mnthName => $mnthNum) {
                    $periodeKey = $tahun . '-' . $mnthNum;
                    if (isset($allKpi[$periodeKey])) {
                        $item  = $allKpi[$periodeKey];
                        $value =
                            ($item->revenue_order_nonkontrak_new ?? 0) +
                            ($item->revenue_order_nonkontrak_exist ?? 0) +
                            ($item->revenue_order_kontrak_new ?? 0) +
                            ($item->revenue_order_kontrak_exist ?? 0);
                    } else {
                        $value = null;
                    }
                    $chart[] = [
                        'month' => $mnthName,
                        'value' => $value,
                    ];
                }

                $sumall = SalesKpi::where('periode', $periode)
                    ->selectRaw('SUM(revenue_order_nonkontrak_new) as revenue_order_nonkontrak_new,
                        SUM(revenue_order_nonkontrak_exist) as revenue_order_nonkontrak_exist,
                        SUM(revenue_order_kontrak_new) as revenue_order_kontrak_new,
                        SUM(revenue_order_kontrak_exist) as revenue_order_kontrak_exist
                    ')
                    ->whereIn('karyawan_id', $bawahanIds)
                    ->first();

                $piechart = [
                    'value' => $sumall->revenue_order_nonkontrak_new + $sumall->revenue_order_nonkontrak_exist + $sumall->revenue_order_kontrak_new + $sumall->revenue_order_kontrak_exist,
                    'new'   => $sumall->revenue_order_nonkontrak_new + $sumall->revenue_order_kontrak_new,
                    'exist' => $sumall->revenue_order_nonkontrak_exist + $sumall->revenue_order_kontrak_exist,
                ];

                [$hierarchyRows, $hierarchyIds] = $this->prepareHierarchyRows($periode, [$teamRootId]);
                $return                        = $this->applyForecastHeading($return, $periode, $hierarchyIds, $cek);
                $return                        = $this->enrichRevenueHeadingWithSampling($return, $periode, $bawahanIds);
                $table                         = $this->mergeHierarchyWithKpi($periode, $hierarchyRows);

                return response()->json([
                    'heading'  => $return,
                    'table'    => $table,
                    'rankings' => $this->buildDailyQsdRankings($periode, $bawahanIds),
                    'chart'    => $chart,
                    'piechart' => $piechart,
                ], 200);

            } else {
                $karyawanId  = (int) $request->karyawan_id;
                $hierarchy   = app(SalesTeamHierarchyService::class);
                $member      = MasterKaryawan::find($karyawanId);
                $isExecutive = in_array($karyawanId, $hierarchy->salesExecutiveIds(), true);
                $isSalesStaff = $member && $hierarchy->isSalesStaff($member);

                if ($isExecutive || $isSalesStaff) {
                    $scopeIds = [$karyawanId];
                } else {
                    $scopeIds = $hierarchy->resolveDescendantIds($karyawanId);
                }

                $cek = ($isSalesStaff || $isExecutive)
                    ? SalesKpi::where('karyawan_id', $karyawanId)->where('periode', $periode)->first()
                    : \DB::table('sales_kpi_monthly')
                        ->selectRaw("
                            SUM(dfus_call) as dfus_call,
                            SUM(duration) as duration,
                            SUM(qty_qt_nonkontrak_new) as qty_qt_nonkontrak_new,
                            SUM(qty_qt_nonkontrak_exist) as qty_qt_nonkontrak_exist,
                            SUM(qty_qt_kontrak_new) as qty_qt_kontrak_new,
                            SUM(qty_qt_kontrak_exist) as qty_qt_kontrak_exist,
                            SUM(qty_qt_order_nonkontrak_new) as qty_qt_order_nonkontrak_new,
                            SUM(qty_qt_order_nonkontrak_exist) as qty_qt_order_nonkontrak_exist,
                            SUM(qty_qt_order_kontrak_new) as qty_qt_order_kontrak_new,
                            SUM(qty_qt_order_kontrak_exist) as qty_qt_order_kontrak_exist,
                            SUM(amount_order_nonkontrak_new) as amount_order_nonkontrak_new,
                            SUM(amount_order_nonkontrak_exist) as amount_order_nonkontrak_exist,
                            SUM(amount_order_kontrak_new) as amount_order_kontrak_new,
                            SUM(amount_order_kontrak_exist) as amount_order_kontrak_exist,
                            SUM(revenue_order_nonkontrak_new) as revenue_order_nonkontrak_new,
                            SUM(revenue_order_nonkontrak_exist) as revenue_order_nonkontrak_exist,
                            SUM(revenue_order_kontrak_new) as revenue_order_kontrak_new,
                            SUM(revenue_order_kontrak_exist) as revenue_order_kontrak_exist,
                            SUM(revenue_forecast_nonkontrak_new) as revenue_forecast_nonkontrak_new,
                            SUM(revenue_forecast_nonkontrak_exist) as revenue_forecast_nonkontrak_exist,
                            SUM(revenue_forecast_kontrak_new) as revenue_forecast_kontrak_new,
                            SUM(revenue_forecast_kontrak_exist) as revenue_forecast_kontrak_exist
                        ")
                        ->where('periode', $periode)
                        ->whereIn('karyawan_id', $scopeIds)
                        ->first();

                $return = [
                    ["title" => "DFUS Contacted", "value" => (($cek->dfus_call ?? 0) . " Calls"), "color" => "primary", "info" => (function ($d) {$d = (int) ($d ?? 0);if ($d >= 3600) {$h = floor($d / 3600); $m = floor(($d % 3600) / 60);return "{$h} Hours\n{$m} Minutes";} else { $m = floor($d / 60); $s = $d % 60;return "{$m} Minutes\n{$s} Seconds";}})($cek->duration ?? 0)],
                    ["title" => "Total Quote", "value" => (($cek->qty_qt_nonkontrak_new ?? 0) + ($cek->qty_qt_nonkontrak_exist ?? 0) + ($cek->qty_qt_kontrak_new ?? 0) + ($cek->qty_qt_kontrak_exist ?? 0)) . " Quotes", "color" => "info", "info" => "Exist : " . (($cek->qty_qt_nonkontrak_exist ?? 0) + ($cek->qty_qt_kontrak_exist ?? 0)) . " \nNew : " . (($cek->qty_qt_nonkontrak_new ?? 0) + ($cek->qty_qt_kontrak_new ?? 0))],
                    ["title" => "Quote Ordered", "value" => (($cek->qty_qt_order_nonkontrak_new ?? 0) + ($cek->qty_qt_order_nonkontrak_exist ?? 0) + ($cek->qty_qt_order_kontrak_new ?? 0) + ($cek->qty_qt_order_kontrak_exist ?? 0)) . " QS", "color" => "success", "info" => "Exist : " . (($cek->qty_qt_order_nonkontrak_exist ?? 0) + ($cek->qty_qt_order_kontrak_exist ?? 0)) . " \nNew : " . (($cek->qty_qt_order_nonkontrak_new ?? 0) + ($cek->qty_qt_order_kontrak_new ?? 0))],
                    ["title" => "Ordered (Amount)", "value" => "Rp " . number_format(($cek->amount_order_nonkontrak_new ?? 0) + ($cek->amount_order_nonkontrak_exist ?? 0) + ($cek->amount_order_kontrak_new ?? 0) + ($cek->amount_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "danger", "info" => "Exist : Rp " . number_format(($cek->amount_order_nonkontrak_exist ?? 0) + ($cek->amount_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->amount_order_nonkontrak_new ?? 0) + ($cek->amount_order_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Revenue", "value" => "Rp " . number_format(($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_new ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Forecast", "value" => "Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Total Revenue + Forecast", "value" => "Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_new ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Unscheduled QT", "value" => "Rp " . number_format($amountSp, 0, ',', '.'), "color" => "warning", "info" => "Total SP : " . $qtySp . " \nQT Kontrak : Rp " . number_format($amountKontrakSp, 0, ',', '.') . " \nQT Non Kontrak : Rp " . number_format($amountNonKontrakSp, 0, ',', '.')],
                ];

                $tahun  = explode('-', $periode)[0];
                $allKpiQuery = SalesKpi::where('periode', 'like', $tahun . '-%');

                if (!$isSalesStaff) {
                    $allKpiQuery->whereIn('karyawan_id', $scopeIds)
                        ->selectRaw('periode,
                            SUM(revenue_order_nonkontrak_new) as revenue_order_nonkontrak_new,
                            SUM(revenue_order_nonkontrak_exist) as revenue_order_nonkontrak_exist,
                            SUM(revenue_order_kontrak_new) as revenue_order_kontrak_new,
                            SUM(revenue_order_kontrak_exist) as revenue_order_kontrak_exist
                        ')
                        ->groupBy('periode');
                } else {
                    $allKpiQuery->where('karyawan_id', $karyawanId);
                }

                $allKpi = $allKpiQuery->get()->keyBy(fn($item) => $item->periode);

                $chart = [];
                foreach ($months as $mnthName => $mnthNum) {
                    $periodeKey = $tahun . '-' . $mnthNum;
                    if (isset($allKpi[$periodeKey])) {
                        $item  = $allKpi[$periodeKey];
                        $value =
                            ($item->revenue_order_nonkontrak_new ?? 0) +
                            ($item->revenue_order_nonkontrak_exist ?? 0) +
                            ($item->revenue_order_kontrak_new ?? 0) +
                            ($item->revenue_order_kontrak_exist ?? 0);
                    } else {
                        $value = null;
                    }
                    $chart[] = [
                        'month' => $mnthName,
                        'value' => $value,
                    ];
                }

                $sumallQuery = SalesKpi::where('periode', $periode)
                    ->selectRaw('SUM(revenue_order_nonkontrak_new) as revenue_order_nonkontrak_new,
                        SUM(revenue_order_nonkontrak_exist) as revenue_order_nonkontrak_exist,
                        SUM(revenue_order_kontrak_new) as revenue_order_kontrak_new,
                        SUM(revenue_order_kontrak_exist) as revenue_order_kontrak_exist
                    ');

                if (!$isSalesStaff) {
                    $sumallQuery->whereIn('karyawan_id', $scopeIds);
                } else {
                    $sumallQuery->where('karyawan_id', $karyawanId);
                }

                $sumall = $sumallQuery->first();

                $piechart = [
                    'value' => $sumall->revenue_order_nonkontrak_new + $sumall->revenue_order_nonkontrak_exist + $sumall->revenue_order_kontrak_new + $sumall->revenue_order_kontrak_exist,
                    'new'   => $sumall->revenue_order_nonkontrak_new + $sumall->revenue_order_kontrak_new,
                    'exist' => $sumall->revenue_order_nonkontrak_exist + $sumall->revenue_order_kontrak_exist,
                ];

                [$hierarchyRows, $hierarchyIds] = ($isSalesStaff || $isExecutive)
                    ? $this->prepareHierarchyRows($periode, null, [$karyawanId])
                    : $this->prepareHierarchyRows($periode, [$karyawanId]);
                $return                        = $this->applyForecastHeading($return, $periode, $hierarchyIds, $cek);
                $samplingScopeIds              = ($isSalesStaff || $isExecutive) ? [$karyawanId] : $scopeIds;
                $return                        = $this->enrichRevenueHeadingWithSampling($return, $periode, $samplingScopeIds);
                $table                         = $this->mergeHierarchyWithKpi($periode, $hierarchyRows);

                return response()->json([
                    'heading'  => $return,
                    'table'    => $table,
                    'rankings' => $this->buildDailyQsdRankings($periode, $scopeIds),
                    'chart'    => $chart,
                    'piechart' => $piechart,
                ], 200);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan pada server',
                'line'    => $th->getLine(),
                'getFile' => $th->getFile(),
                'error'   => $th->getMessage(),
            ], 500);
        }
    }

    public function yearlyComparison(Request $request){
        $years = [
            ($request->year - 1),
            $request->year,
        ];

        $allKpiBar = SalesKpi::where(function ($q) use ($years) {
            foreach ($years as $yr) {
                $q->orWhere('periode', 'like', $yr . '-%');
            }
        })
            ->selectRaw('periode,
                SUM(revenue_order_nonkontrak_new) as revenue_order_nonkontrak_new,
                SUM(revenue_order_nonkontrak_exist) as revenue_order_nonkontrak_exist,
                SUM(revenue_order_kontrak_new) as revenue_order_kontrak_new,
                SUM(revenue_order_kontrak_exist) as revenue_order_kontrak_exist
            ')
            ->groupBy('periode')
            ->get()
            ->keyBy('periode');

        $result = [];

        foreach ($years as $yr) {
            $chartBar = [];

            foreach ($this->months as $mnthName => $mnthNum) {
                $periodeKey = $yr . '-' . $mnthNum;

                if (isset($allKpiBar[$periodeKey])) {
                    $item  = $allKpiBar[$periodeKey];
                    $value =
                        ($item->revenue_order_nonkontrak_new ?? 0) +
                        ($item->revenue_order_nonkontrak_exist ?? 0) +
                        ($item->revenue_order_kontrak_new ?? 0) +
                        ($item->revenue_order_kontrak_exist ?? 0);
                } else {
                    $value = null;
                }

                $chartBar[] = [
                    'month' => $mnthName,
                    'value' => $value,
                ];
            }

            $result[$yr] = $chartBar;
        }

        return response()->json([
            'chartBar' => $result,
        ], 200);
    }

    public function fetchRankings(Request $request)
    {
        try {
            $periodType = $request->period_type === 'yearly' ? 'yearly' : 'monthly';
            $periode = null;

            if ($periodType === 'yearly') {
                $year = (int) ($request->year ?: Carbon::now()->year);
                $periode = sprintf('%04d', $year);
            } else {
                if ($request->periode) {
                    $arr = explode(' ', $request->periode);
                    $periode = (count($arr) == 2 && isset($this->bulan[$arr[0]])) ? $arr[1] . '-' . $this->bulan[$arr[0]] : null;
                }
                
                if (!$periode) {
                    $year = (int) ($request->year ?: Carbon::now()->year);
                    $month = (int) ($request->month ?: Carbon::now()->month);
                    $month = max(1, min(12, $month));
                    $periode = sprintf('%04d-%02d', $year, $month);
                }
            }
            $salesIds = null;

            $hierarchy = app(SalesTeamHierarchyService::class);

            if ($request->mode === 'team' && $request->karyawan_id) {
                $salesIds = $hierarchy->resolveDescendantIds((int) str_replace('team_', '', $request->karyawan_id));
            } elseif ($request->mode === 'single' && $request->karyawan_id) {
                $karyawanId  = (int) $request->karyawan_id;
                $member      = MasterKaryawan::find($karyawanId);
                $isExecutive = in_array($karyawanId, $hierarchy->salesExecutiveIds(), true);

                $salesIds = ($isExecutive || ($member && $hierarchy->isSalesStaff($member)))
                    ? [$karyawanId]
                    : $hierarchy->resolveDescendantIds($karyawanId);
            }

            return response()->json([
                'periode' => $periode,
                'period_type' => $periodType,
                'rankings' => $this->buildDailyQsdRankings($periode, $salesIds, $periodType),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan pada server',
                'line'    => $th->getLine(),
                'getFile' => $th->getFile(),
                'error'   => $th->getMessage(),
            ], 500);
        }
    }

    private $bulan = [
        'Januari'   => '01', 'Februari' => '02', 'Maret'    => '03', 'April'    => '04',
        'Mei'       => '05', 'Juni'     => '06', 'Juli'     => '07', 'Agustus'  => '08',
        'September' => '09', 'Oktober'  => '10', 'November' => '11', 'Desember' => '12',
    ];

    private $months = [
        "Jan" => "01",
        "Feb" => "02",
        "Mar" => "03",
        "Apr" => "04",
        "May" => "05",
        "Jun" => "06",
        "Jul" => "07",
        "Aug" => "08",
        "Sep" => "09",
        "Oct" => "10",
        "Nov" => "11",
        "Dec" => "12",
    ];

    private function buildDailyQsdRankings(?string $periode, ?array $salesIds = null, string $periodType = 'monthly'): array
    {
        if (!$periode) {
            return [
                'top_customers' => [],
                'top_consultants' => [],
                'top_regions' => [],
            ];
        }

        $baseQuery = function () use ($periode, $salesIds, $periodType) {
            $query = \DB::table('daily_qsd');

            if ($periodType === 'yearly') {
                $query->whereYear('tanggal_kelompok', $periode);
            } else {
                $query->whereRaw("DATE_FORMAT(tanggal_kelompok, '%Y-%m') = ?", [$periode]);
            }

            if (is_array($salesIds) && count($salesIds) > 0) {
                $query->whereIn('sales_id', $salesIds);
            }

            return $query;
        };

        $revenueExpression = 'SUM(COALESCE(daily_qsd.total_revenue, 0))';

        $topCustomers = $baseQuery()
            ->leftJoin('master_pelanggan as mp', 'mp.id_pelanggan', '=', 'daily_qsd.pelanggan_ID')
            ->select(
                'daily_qsd.pelanggan_ID as id_pelanggan',
                \DB::raw('MAX(COALESCE(mp.nama_pelanggan, daily_qsd.nama_perusahaan)) as nama_pelanggan'),
                \DB::raw('MAX(mp.sales_penanggung_jawab) as sales_nama'),
                \DB::raw($revenueExpression . ' as revenue')
            )
            ->whereNotNull('daily_qsd.pelanggan_ID')
            ->groupBy('daily_qsd.pelanggan_ID')
            ->havingRaw($revenueExpression . ' > 0')
            ->orderByDesc('revenue')
            ->whereNull('daily_qsd.konsultan')
            ->limit(30)
            ->get();

        $topConsultants = $baseQuery()
            ->select(
                'pelanggan_ID as id_pelanggan',
                \DB::raw('MAX(konsultan) as konsultan'),
                \DB::raw('MAX(sales_nama) as sales_nama'),
                \DB::raw($revenueExpression . ' as revenue')
            )
            ->whereNotNull('konsultan')
            ->whereRaw("TRIM(konsultan) != ''")
            ->groupBy('pelanggan_ID')
            ->havingRaw($revenueExpression . ' > 0')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $regionYear = substr((string) $periode, 0, 4);
        $regionBaseQuery = function () use ($regionYear) {
            $query = \DB::table('order_header as oh')
                ->where('oh.is_active', 1)
                ->whereNotNull('oh.wilayah')
                ->whereRaw("TRIM(SUBSTRING_INDEX(oh.wilayah, '-', -1)) != ''")
                ->whereYear('oh.tanggal_order', $regionYear);

            return $query;
        };

        $totalRegionOrders = (int) $regionBaseQuery()->count('oh.id');

        $topRegions = $regionBaseQuery()
            ->select(
                \DB::raw("TRIM(SUBSTRING_INDEX(oh.wilayah, '-', -1)) as wilayah"),
                \DB::raw('COUNT(oh.id) as total_order')
            )
            ->groupBy(\DB::raw("TRIM(SUBSTRING_INDEX(oh.wilayah, '-', -1))"))
            ->havingRaw('COUNT(oh.id) > 0')
            ->orderByDesc('total_order')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($totalRegionOrders) {
                $totalOrder = (int) ($row->total_order ?? 0);
                $row->percentage = $totalRegionOrders > 0 ? round(($totalOrder / $totalRegionOrders) * 100, 2) : 0;

                return $row;
            });

        return [
            'top_customers' => $topCustomers,
            'top_consultants' => $topConsultants,
            'top_regions' => $topRegions,
        ];
    }

    private function prepareHierarchyRows(
        string $periode,
        ?array $scopeRootIds = null,
        ?array $scopeMemberIds = null
    ): array {
        $service         = app(SalesTeamHierarchyService::class);
        $activeKpiMap    = array_flip($this->karyawanIdsWithKpiActivity($periode));
        $forecastSpMap   = ForecastSpAggregate::mapBySalesForPeriode($periode);

        $shouldShowStaff = function ($member) use ($service, $activeKpiMap, $forecastSpMap) {
            if ((int) ($member->is_active ?? 0) === 1) {
                return true;
            }

            if (!$service->isSalesStaff($member)) {
                return false;
            }

            $memberId = (int) $member->id;

            if (isset($activeKpiMap[$memberId])) {
                return true;
            }

            return ($forecastSpMap[$memberId]['revenue_forecast'] ?? 0) > 0;
        };

        $hierarchyRows = $service->buildTableRows($shouldShowStaff, $scopeRootIds, $scopeMemberIds);
        $hierarchyIds  = array_column($hierarchyRows, 'karyawan_id');

        return [$hierarchyRows, $hierarchyIds];
    }

    private function applyForecastHeading(array $heading, string $periode, array $salesIds, $cek): array
    {
        $forecast = ForecastSpAggregate::totalsForPeriode(
            $periode,
            empty($salesIds) ? null : $salesIds
        );

        $forecastTotal = $forecast['revenue_forecast'];
        $forecastExist = $forecast['revenue_forecast_nonkontrak_exist'] + $forecast['revenue_forecast_kontrak_exist'];
        $forecastNew   = $forecast['revenue_forecast_nonkontrak_new'] + $forecast['revenue_forecast_kontrak_new'];
        $revenueTotal  =
            ($cek->revenue_order_nonkontrak_new ?? 0) +
            ($cek->revenue_order_nonkontrak_exist ?? 0) +
            ($cek->revenue_order_kontrak_new ?? 0) +
            ($cek->revenue_order_kontrak_exist ?? 0);
        $revenueExist  = ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0);
        $revenueNew    = ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_kontrak_new ?? 0);

        foreach ($heading as &$item) {
            if ($item['title'] === 'Forecast') {
                $item['value'] = 'Rp ' . number_format($forecastTotal, 0, ',', '.');
                $item['info']  = 'Exist : Rp ' . number_format($forecastExist, 0, ',', '.')
                    . " \nNew : Rp " . number_format($forecastNew, 0, ',', '.');
            }

            if ($item['title'] === 'Total Revenue + Forecast') {
                $item['value'] = 'Rp ' . number_format($forecastTotal + $revenueTotal, 0, ',', '.');
                $item['info']  = 'Exist : Rp ' . number_format($forecastExist + $revenueExist, 0, ',', '.')
                    . " \nNew : Rp " . number_format($forecastNew + $revenueNew, 0, ',', '.');
            }
        }
        unset($item);

        return $heading;
    }

    public function fetchCallPerformance(Request $request)
    {
        try {
            $periodType = in_array($request->period_type, ['daily', 'yearly', 'range'], true)
                ? $request->period_type
                : 'monthly';

            if ($periodType === 'daily') {
                $startDate = Carbon::parse($request->date ?: $request->start_date ?: Carbon::now())->startOfDay();
                $endDate = $startDate->copy()->endOfDay();
            } elseif ($periodType === 'yearly') {
                $year = max(2024, (int) ($request->year ?: Carbon::now()->year));
                $startDate = Carbon::create($year, 1, 1)->startOfDay();
                $endDate = $startDate->copy()->endOfYear();
            } elseif ($periodType === 'range') {
                $startDate = Carbon::parse($request->start_date ?: Carbon::now()->startOfMonth())->startOfDay();
                $endDate = Carbon::parse($request->end_date ?: Carbon::now())->endOfDay();

                if ($endDate->lt($startDate)) {
                    return response()->json([
                        'message' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
                    ], 422);
                }
            } else {
                $arr = explode(' ', (string) $request->periode);
                $periode = count($arr) === 2 && isset($this->bulan[$arr[0]])
                    ? $arr[1] . '-' . $this->bulan[$arr[0]]
                    : Carbon::now()->format('Y-m');
                $startDate = Carbon::createFromFormat('Y-m', $periode)->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();
            }

            $referencePeriode = $startDate->format('Y-m');
            $hierarchy = app(SalesTeamHierarchyService::class);

            if ($request->mode === 'team' && $request->karyawan_id) {
                $rootId = (int) str_replace('team_', '', $request->karyawan_id);
                [$hierarchyRows] = $this->prepareHierarchyRows($referencePeriode, [$rootId]);
            } elseif ($request->mode === 'single' && $request->karyawan_id) {
                $karyawanId = (int) $request->karyawan_id;
                $member = MasterKaryawan::find($karyawanId);
                $isExecutive = in_array($karyawanId, $hierarchy->salesExecutiveIds(), true);
                $isSalesStaff = $member && $hierarchy->isSalesStaff($member);

                [$hierarchyRows] = ($isSalesStaff || $isExecutive)
                    ? $this->prepareHierarchyRows($referencePeriode, null, [$karyawanId])
                    : $this->prepareHierarchyRows($referencePeriode, [$karyawanId]);
            } else {
                [$hierarchyRows] = $this->prepareHierarchyRows($referencePeriode);
            }

            return response()->json([
                'period_type' => $periodType,
                'call_performance' => $this->buildCallPerformanceRowsForRange($startDate, $endDate, $hierarchyRows),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan pada server',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function fetchQuotationAnalytics(Request $request)
    {
        try {
            [$startDate, $endDate] = $this->resolveQuotationAnalyticsDateRange($request);
            $salesIds = $this->resolveDashboardSalesIds($request);

            $quotes = collect([QuotationNonKontrak::class, QuotationKontrakH::class])
                ->flatMap(function ($model) use ($salesIds, $startDate, $endDate) {
                    return $model::query()
                        ->where('is_active', 1)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->when($salesIds !== null, fn($query) => $query->whereIn('sales_id', $salesIds))
                        ->get(['no_document', 'pelanggan_ID', 'flag_status', 'status_quotation', 'kode_promo', 'promo_id', 'total_discount_promo', 'biaya_akhir', 'tanggal_penawaran']);
                })
                ->map(function ($quote) {
                    $flag = strtolower(trim((string) $quote->flag_status));
                    $status = strtolower(trim((string) $quote->status_quotation));
                    $category = $flag === 'ordered' ? 'ordered'
                        : ($flag === 'void' ? 'void' : (in_array($status, ['cold', 'warm', 'hot'], true) ? $status : 'no_status'));

                    return [
                        'no_document' => $quote->no_document,
                        'pelanggan_id' => $quote->pelanggan_ID,
                        'flag_status' => $flag,
                        'category' => $category,
                        'amount' => (float) ($quote->biaya_akhir ?? 0),
                        'has_promo' => filled($quote->kode_promo) || filled($quote->promo_id) || (float) ($quote->total_discount_promo ?? 0) > 0,
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

            $quotes = $quotes->map(function ($quote) use ($firstOrders) {
                $firstOrder = $firstOrders->get($quote['pelanggan_id']);
                $quote['customer_type'] = $firstOrder && Carbon::parse($firstOrder)->lt(Carbon::parse($quote['tanggal_penawaran']))
                    ? 'repeat'
                    : 'new';
                return $quote;
            });

            $summarize = fn($items) => ['qty' => $items->count(), 'amount' => round((float) $items->sum('amount'), 2)];
            $statusKeys = ['cold', 'warm', 'hot', 'ordered', 'void', 'no_status'];
            $status = collect($statusKeys)->mapWithKeys(fn($key) => [$key => $summarize($quotes->where('category', $key))])->all();
            $breakdown = function ($items) use ($summarize) {
                return [
                    'total' => $summarize($items),
                    'customer' => ['new' => $summarize($items->where('customer_type', 'new')), 'repeat' => $summarize($items->where('customer_type', 'repeat'))],
                    'promo' => ['with' => $summarize($items->where('has_promo', true)), 'without' => $summarize($items->where('has_promo', false))],
                    'revision' => ['with' => $summarize($items->where('is_revisi', true)), 'without' => $summarize($items->where('is_revisi', false))],
                ];
            };

            return response()->json([
                'status' => $status,
                'total' => $summarize($quotes),
                'pending' => $breakdown($quotes->whereNotIn('category', ['ordered', 'void'])),
                'ordered' => $breakdown($quotes->where('category', 'ordered')),
            ]);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Terjadi kesalahan saat mengambil analytics penawaran.', 'error' => $th->getMessage()], 500);
        }
    }

    public function fetchQuotationNoStatusList(Request $request)
    {
        try {
            [$startDate, $endDate] = $this->resolveQuotationAnalyticsDateRange($request);
            $salesIds = $this->resolveDashboardSalesIds($request);
            $service = app(\App\Services\DashboardQuotationNoStatusService::class);

            if ($request->has('draw')) {
                return response()->json($service->datatable($request, $salesIds, $startDate, $endDate));
            }

            $rows = $service->list($salesIds, $startDate, $endDate);

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

    private function resolveQuotationAnalyticsDateRange(Request $request): array
    {
        if ($request->period_type === 'daily') {
            $startDate = Carbon::parse($request->date ?: $request->start_date ?: Carbon::now())->startOfDay();
            $endDate = $startDate->copy()->endOfDay();
        } elseif ($request->period_type === 'yearly') {
            $startDate = Carbon::create((int) ($request->year ?: Carbon::now()->year), 1, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfYear();
        } elseif ($request->period_type === 'range') {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
        } else {
            $arr = explode(' ', (string) $request->periode);
            $periode = count($arr) === 2 && isset($this->bulan[$arr[0]]) ? $arr[1] . '-' . $this->bulan[$arr[0]] : Carbon::now()->format('Y-m');
            $startDate = Carbon::createFromFormat('Y-m', $periode)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
        }

        return [$startDate, $endDate];
    }

    private function resolveDashboardSalesIds(Request $request): ?array
    {
        $hierarchy = app(SalesTeamHierarchyService::class);

        if ($request->mode === 'all') {
            return null;
        }

        if ($request->mode === 'team' && $request->karyawan_id) {
            return $hierarchy->resolveDescendantIds((int) str_replace('team_', '', $request->karyawan_id));
        }

        if ($request->mode === 'single' && $request->karyawan_id) {
            $karyawanId = (int) $request->karyawan_id;
            $member = MasterKaryawan::find($karyawanId);

            return (in_array($karyawanId, $hierarchy->salesExecutiveIds(), true) || ($member && $hierarchy->isSalesStaff($member)))
                ? [$karyawanId]
                : $hierarchy->resolveDescendantIds($karyawanId);
        }

        return null;
    }

    /** Metrik call per sales berdasarkan status akhir follow-up DFUS. */
    private function buildCallPerformanceRowsForRange(Carbon $startDate, Carbon $endDate, array $hierarchyRows): array
    {
        $salesIds = array_values(array_unique(array_filter(array_map(
            'intval',
            array_column($hierarchyRows, 'karyawan_id')
        ))));

        if (empty($salesIds)) {
            return [];
        }

        $sales = MasterKaryawan::whereIn('id', $salesIds)
            ->pluck('nama_lengkap', 'id');
        $salesNames = $sales->filter()->values()->all();

        $statusRows = empty($salesNames)
            ? collect()
            : DFUS::whereIn('sales_penanggung_jawab', $salesNames)
                ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
                ->selectRaw("sales_penanggung_jawab, UPPER(TRIM(COALESCE(keterangan, ''))) as status, COUNT(*) as total")
                ->groupBy('sales_penanggung_jawab')
                ->groupByRaw("UPPER(TRIM(COALESCE(keterangan, '')))")
                ->get();

        $statusesBySales = $statusRows->groupBy('sales_penanggung_jawab');

        return array_map(function (array $row) use ($sales, $statusesBySales) {
            $salesId = (int) $row['karyawan_id'];
            $name = $sales->get($salesId, $row['nama_lengkap'] ?? '');
            $statusCounts = $statusesBySales->get($name, collect())->pluck('total', 'status');
            $totalCalls = (int) $statusCounts->sum();
            $picContacted = (int) ($statusCounts->get('PIC', 0));

            return array_merge($row, [
                'total_call' => $totalCalls,
                'pic_contacted' => $picContacted,
                'unreachable' => (int) ($statusCounts->get('NA', 0)) + (int) ($statusCounts->get('D', 0)) + (int) ($statusCounts->get('FO', 0)),
                'unqualified' => (int) ($statusCounts->get('NI', 0)),
                'success_rate' => $totalCalls > 0 ? round(($picContacted / $totalCalls) * 100, 1) : 0,
            ]);
        }, $hierarchyRows);
    }

    private function karyawanIdsWithKpiActivity(string $periode): array
    {
        return SalesKpi::where('periode', $periode)
            ->where(function ($query) {
                $query->where('dfus_call', '!=', 0)
                    ->orWhere('duration', '!=', 0)
                    ->orWhere('qty_qt_order_kontrak_exist', '!=', 0)
                    ->orWhere('qty_qt_order_kontrak_new', '!=', 0)
                    ->orWhere('qty_qt_order_nonkontrak_exist', '!=', 0)
                    ->orWhere('qty_qt_order_nonkontrak_new', '!=', 0)
                    ->orWhere('qty_qt_kontrak_exist', '!=', 0)
                    ->orWhere('qty_qt_kontrak_new', '!=', 0)
                    ->orWhere('qty_qt_nonkontrak_exist', '!=', 0)
                    ->orWhere('qty_qt_nonkontrak_new', '!=', 0)
                    ->orWhere('amount_bysampling_order_nonkontrak_new', '!=', 0)
                    ->orWhere('amount_bysampling_order_nonkontrak_exist', '!=', 0)
                    ->orWhere('amount_bysampling_order_kontrak_new', '!=', 0)
                    ->orWhere('amount_bysampling_order_kontrak_exist', '!=', 0)
                    ->orWhere('revenue_bysampling_order_nonkontrak_new', '!=', 0)
                    ->orWhere('revenue_bysampling_order_nonkontrak_exist', '!=', 0)
                    ->orWhere('revenue_bysampling_order_kontrak_new', '!=', 0)
                    ->orWhere('revenue_bysampling_order_kontrak_exist', '!=', 0)
                    ->orWhere('amount_order_nonkontrak_new', '!=', 0)
                    ->orWhere('amount_order_nonkontrak_exist', '!=', 0)
                    ->orWhere('amount_order_kontrak_new', '!=', 0)
                    ->orWhere('amount_order_kontrak_exist', '!=', 0)
                    ->orWhere('revenue_order_nonkontrak_new', '!=', 0)
                    ->orWhere('revenue_order_nonkontrak_exist', '!=', 0)
                    ->orWhere('revenue_order_kontrak_new', '!=', 0)
                    ->orWhere('revenue_order_kontrak_exist', '!=', 0)
                    ->orWhere('revenue_forecast_nonkontrak_new', '!=', 0)
                    ->orWhere('revenue_forecast_nonkontrak_exist', '!=', 0)
                    ->orWhere('revenue_forecast_kontrak_new', '!=', 0)
                    ->orWhere('revenue_forecast_kontrak_exist', '!=', 0);
            })
            ->pluck('karyawan_id')
            ->unique()
            ->values()
            ->all();
    }

    private function mergeHierarchyWithKpi(string $periode, array $hierarchyRows): array
    {
        if (empty($hierarchyRows)) {
            return [];
        }

        $ids = array_column($hierarchyRows, 'karyawan_id');

        $kpiMap = SalesKpi::leftJoin('master_karyawan', 'sales_kpi_monthly.karyawan_id', '=', 'master_karyawan.id')
            ->where('sales_kpi_monthly.periode', $periode)
            ->whereIn('sales_kpi_monthly.karyawan_id', $ids)
            ->select(
                'sales_kpi_monthly.*',
                \DB::raw('
                    (IFNULL(sales_kpi_monthly.amount_bysampling_order_nonkontrak_new,0) + IFNULL(sales_kpi_monthly.amount_bysampling_order_nonkontrak_exist,0) + IFNULL(sales_kpi_monthly.amount_bysampling_order_kontrak_new,0) + IFNULL(sales_kpi_monthly.amount_bysampling_order_kontrak_exist,0)
                    ) as amount_sp
                '),
                \DB::raw('
                    (IFNULL(sales_kpi_monthly.amount_order_nonkontrak_new,0) + IFNULL(sales_kpi_monthly.amount_order_nonkontrak_exist,0) + IFNULL(sales_kpi_monthly.amount_order_kontrak_new,0) + IFNULL(sales_kpi_monthly.amount_order_kontrak_exist,0)
                    ) as amount_non_sp
                '),
                \DB::raw('
                    (IFNULL(sales_kpi_monthly.revenue_bysampling_order_nonkontrak_new,0) + IFNULL(sales_kpi_monthly.revenue_bysampling_order_nonkontrak_exist,0) + IFNULL(sales_kpi_monthly.revenue_bysampling_order_kontrak_new,0) + IFNULL(sales_kpi_monthly.revenue_bysampling_order_kontrak_exist,0)
                    ) as revenue_sp
                '),
                \DB::raw('
                    (IFNULL(sales_kpi_monthly.revenue_order_nonkontrak_new,0) + IFNULL(sales_kpi_monthly.revenue_order_nonkontrak_exist,0) + IFNULL(sales_kpi_monthly.revenue_order_kontrak_new,0) + IFNULL(sales_kpi_monthly.revenue_order_kontrak_exist,0)
                    ) as revenue_non_sp
                '),
                \DB::raw('
                    (IFNULL(sales_kpi_monthly.revenue_forecast_nonkontrak_exist,0) + IFNULL(sales_kpi_monthly.revenue_forecast_nonkontrak_new,0) + IFNULL(sales_kpi_monthly.revenue_forecast_kontrak_exist,0) + IFNULL(sales_kpi_monthly.revenue_forecast_kontrak_new,0)
                    ) as revenue_forecast
                ')
            )
            ->get()
            ->keyBy('karyawan_id');

        $rows = [];

        $forecastSpMap = ForecastSpAggregate::mapBySalesForPeriode($periode, $ids);

        foreach ($hierarchyRows as $row) {
            $kpi         = $kpiMap->get($row['karyawan_id']);
            $merged      = array_merge($this->emptyKpiRow(), $row, $kpi ? $kpi->toArray() : []);
            $forecastSp  = $forecastSpMap[(int) $row['karyawan_id']] ?? null;

            if ($forecastSp !== null) {
                $merged = array_merge($merged, $forecastSp);
            }

            $rows[] = $merged;
        }

        return $rows;
    }

    private function emptyKpiRow(): array
    {
        return [
            'dfus_call'                    => 0,
            'duration'                     => 0,
            'qty_qt_kontrak_exist'         => 0,
            'qty_qt_kontrak_new'           => 0,
            'qty_qt_nonkontrak_exist'      => 0,
            'qty_qt_nonkontrak_new'        => 0,
            'qty_qt_order_kontrak_exist'   => 0,
            'qty_qt_order_kontrak_new'     => 0,
            'qty_qt_order_nonkontrak_exist'=> 0,
            'qty_qt_order_nonkontrak_new'  => 0,
            'amount_non_sp'                => 0,
            'revenue_non_sp'               => 0,
            'revenue_forecast'             => 0,
        ];
    }

    private function enrichRevenueHeadingWithSampling(array $heading, ?string $periode, ?array $salesIds): array
    {
        $totals = $this->calculateSamplingRevenueTotals($periode, $salesIds);
        $suffix = "\nBelum Sampling : Rp " . number_format($totals['belum_sampling'], 0, ',', '.')
            . "\nSudah Sampling : Rp " . number_format($totals['sudah_sampling'], 0, ',', '.');

        foreach ($heading as &$item) {
            if (($item['title'] ?? '') === 'Revenue') {
                $item['info'] = ($item['info'] ?? '') . $suffix;
            }
        }
        unset($item);

        return $heading;
    }

    private function calculateSamplingRevenueTotals(?string $periode, ?array $salesIds): array
    {
        $empty = ['belum_sampling' => 0.0, 'sudah_sampling' => 0.0];
        if (!$periode) {
            return $empty;
        }

        [$year, $month] = array_pad(explode('-', $periode, 2), 2, null);
        if (!$year || !$month) {
            return $empty;
        }

        $query = \DB::table('daily_qsd')
            ->select('no_order', 'no_quotation', 'periode', 'status_sampling', 'total_revenue')
            ->whereNotNull('no_order')
            ->where('no_order', '!=', '')
            ->whereYear('tanggal_kelompok', (int) $year)
            ->whereMonth('tanggal_kelompok', (int) $month);

        if (is_array($salesIds) && count($salesIds) > 0) {
            $query->whereIn('sales_id', $salesIds);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return $empty;
        }

        $noOrders = $rows->pluck('no_order')->filter()->unique()->values();
        $samplingCategoryByOrder = $this->mapOrderSamplingCategories($noOrders, $rows);
        $sSamplingMap = $this->mapOrderTypeSSamplingStatus($noOrders);
        $sdSamplingMap = $this->mapOrderTypeSdSamplingStatus($rows);

        $belum = 0.0;
        $sudah = 0.0;

        foreach ($rows as $row) {
            $revenue = (float) ($row->total_revenue ?? 0);
            if ($revenue <= 0) {
                continue;
            }

            $category = $this->resolveRowSamplingCategory($row, $samplingCategoryByOrder);
            $isSampled = false;

            if ($category === 'sd') {
                $isSampled = (bool) ($sdSamplingMap[$this->sdSamplingKey($row)] ?? false);
            } else {
                // S / S24 / Non Pengujian / unknown → aturan tanggal_terima (tipe S)
                $isSampled = (bool) ($sSamplingMap[$row->no_order] ?? false);
            }

            if ($isSampled) {
                $sudah += $revenue;
            } else {
                $belum += $revenue;
            }
        }

        return [
            'belum_sampling' => $belum,
            'sudah_sampling' => $sudah,
        ];
    }

    private function mapOrderSamplingCategories($noOrders, $rows): array
    {
        $map = [];

        if ($noOrders->isNotEmpty()) {
            $detailTypes = \DB::table('order_detail')
                ->whereIn('no_order', $noOrders)
                ->where('is_active', 1)
                ->selectRaw('no_order, GROUP_CONCAT(DISTINCT UPPER(TRIM(kategori_1)) ORDER BY kategori_1 SEPARATOR ", ") as types')
                ->groupBy('no_order')
                ->pluck('types', 'no_order');

            foreach ($detailTypes as $noOrder => $typesRaw) {
                $map[$noOrder] = $this->classifySamplingTokens($this->tokenizeSamplingStatus($typesRaw));
            }
        }

        foreach ($rows as $row) {
            $noOrder = $row->no_order ?? null;
            if (!$noOrder || isset($map[$noOrder])) {
                continue;
            }

            $map[$noOrder] = $this->classifySamplingTokens(
                $this->tokenizeSamplingStatus($row->status_sampling ?? '')
            );
        }

        return $map;
    }

    private function resolveRowSamplingCategory($row, array $categoryByOrder): string
    {
        $noOrder = $row->no_order ?? null;
        if ($noOrder && isset($categoryByOrder[$noOrder])) {
            return $categoryByOrder[$noOrder];
        }

        return $this->classifySamplingTokens(
            $this->tokenizeSamplingStatus($row->status_sampling ?? '')
        );
    }

    private function tokenizeSamplingStatus($raw): array
    {
        $raw = strtoupper(trim((string) $raw));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(function ($part) {
            return trim($part);
        }, preg_split('/\s*,\s*/', $raw) ?: [])));
    }

    /**
     * @return 'sd'|'s'
     */
    private function classifySamplingTokens(array $tokens): string
    {
        $sdTokens = ['SD', 'SAR', 'SP'];
        $sTokens = ['S', 'S24'];

        foreach ($tokens as $token) {
            if (in_array($token, $sdTokens, true)) {
                return 'sd';
            }
        }

        foreach ($tokens as $token) {
            if (in_array($token, $sTokens, true)) {
                return 's';
            }
        }

        // Non Pengujian & legacy kosong → ikuti aturan S (tanggal_terima)
        return 's';
    }

    private function mapOrderTypeSSamplingStatus($noOrders): array
    {
        if ($noOrders->isEmpty()) {
            return [];
        }

        $stats = \DB::table('order_detail')
            ->whereIn('no_order', $noOrders)
            ->where('is_active', 1)
            ->selectRaw("
                no_order,
                COUNT(*) as detail_count,
                SUM(CASE
                    WHEN tanggal_terima IS NOT NULL
                        AND TRIM(CAST(tanggal_terima AS CHAR)) NOT IN ('', '0000-00-00')
                    THEN 1 ELSE 0
                END) as filled_count
            ")
            ->groupBy('no_order')
            ->get();

        $map = [];
        foreach ($stats as $stat) {
            $detailCount = (int) ($stat->detail_count ?? 0);
            $filledCount = (int) ($stat->filled_count ?? 0);
            $map[$stat->no_order] = $detailCount > 0 && $filledCount === $detailCount;
        }

        return $map;
    }

    private function mapOrderTypeSdSamplingStatus($dailyQsdRows): array
    {
        $noOrders = $dailyQsdRows->pluck('no_order')->filter()->unique()->values();
        $noQuotations = $dailyQsdRows->pluck('no_quotation')->filter()->unique()->values();

        if ($noOrders->isEmpty() && $noQuotations->isEmpty()) {
            return [];
        }

        if (!\Schema::hasTable('sampel_diantar')) {
            return [];
        }

        $sdQuery = \DB::table('sampel_diantar');
        $sdQuery->where(function ($q) use ($noOrders, $noQuotations) {
            if ($noOrders->isNotEmpty()) {
                $q->whereIn('no_order', $noOrders);
            }
            if ($noQuotations->isNotEmpty()) {
                $method = $noOrders->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                $q->{$method}('no_quotation', $noQuotations);
            }
        });

        $sdRows = $sdQuery->get(['no_order', 'no_quotation', 'periode_kontrak']);
        $map = [];
        foreach ($sdRows as $sd) {
            $order = trim((string) ($sd->no_order ?? ''));
            $qt = trim((string) ($sd->no_quotation ?? ''));
            $periode = trim((string) ($sd->periode_kontrak ?? ''));

            if ($order !== '') {
                $map[$order . '|' . $periode] = true;
                $map[$order . '|'] = true;
            }
            if ($qt !== '') {
                $map['qt:' . $qt . '|' . $periode] = true;
                $map['qt:' . $qt . '|'] = true;
            }
        }

        $result = [];
        foreach ($dailyQsdRows as $row) {
            $key = $this->sdSamplingKey($row);
            $order = trim((string) ($row->no_order ?? ''));
            $qt = trim((string) ($row->no_quotation ?? ''));
            $periode = trim((string) ($row->periode ?? ''));

            $result[$key] = ($order !== '' && (
                isset($map[$order . '|' . $periode]) || isset($map[$order . '|'])
            )) || ($qt !== '' && (
                isset($map['qt:' . $qt . '|' . $periode]) || isset($map['qt:' . $qt . '|'])
            ));
        }

        return $result;
    }

    private function sdSamplingKey($row): string
    {
        return trim((string) ($row->no_order ?? '')) . '|' . trim((string) ($row->periode ?? ''));
    }

}

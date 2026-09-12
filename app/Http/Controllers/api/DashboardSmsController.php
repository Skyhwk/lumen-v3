<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
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
                $table                         = $this->mergeHierarchyWithKpi($periode, $hierarchyRows);

                return response()->json([
                    'heading'  => $return,
                    'table'    => $table,
                    'rankings' => $this->buildDailyQsdRankings($periode, $bawahanIds),
                    'chart'    => $chart,
                    'piechart' => $piechart,
                ], 200);

            } else {
                $karyawanId = (int) $request->karyawan_id;
                $hierarchy  = app(SalesTeamHierarchyService::class);
                $member     = MasterKaryawan::find($karyawanId);
                $scopeIds   = ($member && $hierarchy->isSalesStaff($member))
                    ? [$karyawanId]
                    : $hierarchy->resolveDescendantIds($karyawanId);

                $isSalesStaff = $member && $hierarchy->isSalesStaff($member);

                $cek = $isSalesStaff
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

                [$hierarchyRows, $hierarchyIds] = $isSalesStaff
                    ? $this->prepareHierarchyRows($periode, null, [$karyawanId])
                    : $this->prepareHierarchyRows($periode, [$karyawanId]);
                $return                        = $this->applyForecastHeading($return, $periode, $hierarchyIds, $cek);
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
                $karyawanId = (int) $request->karyawan_id;
                $member     = MasterKaryawan::find($karyawanId);
                $salesIds   = ($member && $hierarchy->isSalesStaff($member))
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

        $revenueExpression = 'SUM(COALESCE(total_revenue, 0))';

        $topCustomers = $baseQuery()
            ->select(
                'pelanggan_ID as id_pelanggan',
                \DB::raw('MAX(nama_perusahaan) as nama_pelanggan'),
                \DB::raw('MAX(sales_nama) as sales_nama'),
                \DB::raw($revenueExpression . ' as revenue')
            )
            ->whereNotNull('pelanggan_ID')
            ->groupBy('pelanggan_ID')
            ->havingRaw($revenueExpression . ' > 0')
            ->orderByDesc('revenue')
            ->whereNull('konsultan')
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

}

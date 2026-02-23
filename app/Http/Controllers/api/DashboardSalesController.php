<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SalesKpi;
use App\Models\TargetSales;
use App\Services\GetBawahan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

Carbon::setLocale('id');

class DashboardSalesController extends Controller
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
        $karyawan_id = 890;
        $bawahanIds  = GetBawahan::where('id', $karyawan_id)->get()->pluck('id')->unique()->values()->toArray();

        $data = MasterKaryawan::where('is_active', true)
            ->where(function ($query) {
                $query->where('id', '14')
                    ->orWhere('jabatan', 'like', '%Manager%');
            })
        // ->where('jabatan', 'like', '%Manager%')
            ->where('id', '!=', $karyawan_id)
            ->whereIn('id', $bawahanIds)
            ->orderBy('jabatan', 'asc')
            ->select('id', 'nama_lengkap', 'jabatan')
            ->get();

        // dd($managers);

        foreach ($data as $mgr) {
            $mgr->bawahan = MasterKaryawan::where('is_active', true)
                ->whereIn('id', GetBawahan::where('id', $mgr->id)->get()->pluck('id')->toArray())
                ->where('id', '!=', $mgr->id)
                ->whereIn('id_jabatan', [21, 24, 148])
                ->select('id', 'nama_lengkap', 'jabatan')
                ->orderBy('jabatan', 'asc')
                ->get()
                ->values();
        }

        return response()->json([
            'sales'   => $data,
            'message' => 'Sales data retrieved successfully',
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
                    ["title" => "Forecast", "value" => "Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Total Forecast", "value" => "Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_new ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_kontrak_new ?? 0), 0, ',', '.')],
                    // [ "title" => "Ordered (By Sampling)", "value" => "Rp " . number_format(($cek->amount_bysampling_order_nonkontrak_new ?? 0)+($cek->amount_bysampling_order_nonkontrak_exist ?? 0)+($cek->amount_bysampling_order_kontrak_new ?? 0)+($cek->amount_bysampling_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "warning", "info" => "Exist : Rp " . number_format(($cek->amount_bysampling_order_nonkontrak_exist ?? 0)+($cek->amount_bysampling_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->amount_bysampling_order_nonkontrak_new ?? 0)+($cek->amount_bysampling_order_kontrak_new ?? 0), 0, ',', '.') ],
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

                $table = SalesKpi::leftJoin('master_karyawan', 'sales_kpi_monthly.karyawan_id', '=', 'master_karyawan.id')
                    ->where('periode', $periode)
                    ->where(function ($query) {
                        $query->where('qty_qt_order_kontrak_exist', '!=', 0)
                            ->orWhere('qty_qt_order_kontrak_new', '!=', 0)
                            ->orWhere('qty_qt_order_nonkontrak_exist', '!=', 0)
                            ->orWhere('qty_qt_order_nonkontrak_new', '!=', 0)
                            ->orWhere('amount_bysampling_order_nonkontrak_new', '!=', 0)
                            ->orWhere('amount_bysampling_order_nonkontrak_exist', '!=', 0)
                            ->orWhere('amount_bysampling_order_kontrak_new', '!=', 0)
                            ->orWhere('amount_bysampling_order_kontrak_exist', '!=', 0)
                            ->orWhere('revenue_bysampling_order_nonkontrak_new', '!=', 0)
                            ->orWhere('revenue_bysampling_order_nonkontrak_exist', '!=', 0)
                            ->orWhere('revenue_bysampling_order_kontrak_new', '!=', 0)
                            ->orWhere('revenue_bysampling_order_kontrak_exist', '!=', 0);
                    })
                    ->select(
                        'sales_kpi_monthly.*',
                        'master_karyawan.nama_lengkap',
                        'master_karyawan.is_active as karyawan_active',
                        // Revenue non-SP total (nonkontrak, new + exist)
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
                    ->orderBy('revenue_sp', 'desc')
                    ->get();

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
                    'chart'    => $chart,
                    'piechart' => $piechart,
                    'chartBar' => $result
                ], 200);

            } else if (strpos($request->mode, "team") !== false) {
                $bawahanIds = GetBawahan::where('id', str_replace('team_', '', $request->karyawan_id))->get()->pluck('id')->unique()->values()->toArray();

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
                    ["title" => "Total Forecast", "value" => "Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_new ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_kontrak_new ?? 0), 0, ',', '.')],
                    // ["title" => "Ordered (By Sampling)", "value" => "Rp " . number_format(($cek->amount_bysampling_order_nonkontrak_new ?? 0) + ($cek->amount_bysampling_order_nonkontrak_exist ?? 0) + ($cek->amount_bysampling_order_kontrak_new ?? 0) + ($cek->amount_bysampling_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "warning", "info" => "Exist : Rp " . number_format(($cek->amount_bysampling_order_nonkontrak_exist ?? 0) + ($cek->amount_bysampling_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->amount_bysampling_order_nonkontrak_new ?? 0) + ($cek->amount_bysampling_order_kontrak_new ?? 0), 0, ',', '.')],
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

                $table = SalesKpi::leftJoin('master_karyawan', 'sales_kpi_monthly.karyawan_id', '=', 'master_karyawan.id')
                    ->where('periode', $periode)
                    ->where(function ($query) {
                        $query->where('qty_qt_order_kontrak_exist', '!=', 0)
                            ->orWhere('qty_qt_order_kontrak_new', '!=', 0)
                            ->orWhere('qty_qt_order_nonkontrak_exist', '!=', 0)
                            ->orWhere('qty_qt_order_nonkontrak_new', '!=', 0)
                            ->orWhere('amount_bysampling_order_nonkontrak_new', '!=', 0)
                            ->orWhere('amount_bysampling_order_nonkontrak_exist', '!=', 0)
                            ->orWhere('amount_bysampling_order_kontrak_new', '!=', 0)
                            ->orWhere('amount_bysampling_order_kontrak_exist', '!=', 0)
                            ->orWhere('revenue_bysampling_order_nonkontrak_new', '!=', 0)
                            ->orWhere('revenue_bysampling_order_nonkontrak_exist', '!=', 0)
                            ->orWhere('revenue_bysampling_order_kontrak_new', '!=', 0)
                            ->orWhere('revenue_bysampling_order_kontrak_exist', '!=', 0);
                    })
                    ->whereIn('karyawan_id', $bawahanIds)
                    ->select(
                        'sales_kpi_monthly.*',
                        'master_karyawan.nama_lengkap',
                        'master_karyawan.is_active as karyawan_active',
                        // Revenue non-SP total (nonkontrak, new + exist)
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
                    ->orderBy('revenue_sp', 'desc')
                    ->get();

                return response()->json([
                    'heading'  => $return,
                    'table'    => $table,
                    'chart'    => $chart,
                    'piechart' => $piechart,
                ], 200);

            } else {
                $cek = SalesKpi::where('karyawan_id', $request->karyawan_id)->where('periode', $periode)->first();

                $return = [
                    ["title" => "DFUS Contacted", "value" => (($cek->dfus_call ?? 0) . " Calls"), "color" => "primary", "info" => (function ($d) {$d = (int) ($d ?? 0);if ($d >= 3600) {$h = floor($d / 3600); $m = floor(($d % 3600) / 60);return "{$h} Hours\n{$m} Minutes";} else { $m = floor($d / 60); $s = $d % 60;return "{$m} Minutes\n{$s} Seconds";}})($cek->duration ?? 0)],
                    ["title" => "Total Quote", "value" => (($cek->qty_qt_nonkontrak_new ?? 0) + ($cek->qty_qt_nonkontrak_exist ?? 0) + ($cek->qty_qt_kontrak_new ?? 0) + ($cek->qty_qt_kontrak_exist ?? 0)) . " Quotes", "color" => "info", "info" => "Exist : " . (($cek->qty_qt_nonkontrak_exist ?? 0) + ($cek->qty_qt_kontrak_exist ?? 0)) . " \nNew : " . (($cek->qty_qt_nonkontrak_new ?? 0) + ($cek->qty_qt_kontrak_new ?? 0))],
                    ["title" => "Quote Ordered", "value" => (($cek->qty_qt_order_nonkontrak_new ?? 0) + ($cek->qty_qt_order_nonkontrak_exist ?? 0) + ($cek->qty_qt_order_kontrak_new ?? 0) + ($cek->qty_qt_order_kontrak_exist ?? 0)) . " QS", "color" => "success", "info" => "Exist : " . (($cek->qty_qt_order_nonkontrak_exist ?? 0) + ($cek->qty_qt_order_kontrak_exist ?? 0)) . " \nNew : " . (($cek->qty_qt_order_nonkontrak_new ?? 0) + ($cek->qty_qt_order_kontrak_new ?? 0))],
                    ["title" => "Ordered (Amount)", "value" => "Rp " . number_format(($cek->amount_order_nonkontrak_new ?? 0) + ($cek->amount_order_nonkontrak_exist ?? 0) + ($cek->amount_order_kontrak_new ?? 0) + ($cek->amount_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "danger", "info" => "Exist : Rp " . number_format(($cek->amount_order_nonkontrak_exist ?? 0) + ($cek->amount_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->amount_order_nonkontrak_new ?? 0) + ($cek->amount_order_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Revenue", "value" => "Rp " . number_format(($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_new ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Forecast", "value" => "Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0), 0, ',', '.')],
                    ["title" => "Total Forecast", "value" => "Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_new ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "dark", "info" => "Exist : Rp " . number_format(($cek->revenue_forecast_nonkontrak_exist ?? 0) + ($cek->revenue_forecast_kontrak_exist ?? 0) + ($cek->revenue_order_nonkontrak_exist ?? 0) + ($cek->revenue_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_forecast_nonkontrak_new ?? 0) + ($cek->revenue_forecast_kontrak_new ?? 0) + ($cek->revenue_order_nonkontrak_new ?? 0) + ($cek->revenue_order_kontrak_new ?? 0), 0, ',', '.')],
                    // ["title" => "Ordered (By Sampling)", "value" => "Rp " . number_format(($cek->amount_bysampling_order_nonkontrak_new ?? 0) + ($cek->amount_bysampling_order_nonkontrak_exist ?? 0) + ($cek->amount_bysampling_order_kontrak_new ?? 0) + ($cek->amount_bysampling_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "warning", "info" => "Exist : Rp " . number_format(($cek->amount_bysampling_order_nonkontrak_exist ?? 0) + ($cek->amount_bysampling_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->amount_bysampling_order_nonkontrak_new ?? 0) + ($cek->amount_bysampling_order_kontrak_new ?? 0), 0, ',', '.')],
                    // ["title" => "Revenue (By Sampling)", "value" => "Rp " . number_format(($cek->revenue_bysampling_order_nonkontrak_new ?? 0) + ($cek->revenue_bysampling_order_nonkontrak_exist ?? 0) + ($cek->revenue_bysampling_order_kontrak_new ?? 0) + ($cek->revenue_bysampling_order_kontrak_exist ?? 0), 0, ',', '.'), "color" => "secondary", "info" => "Exist : Rp " . number_format(($cek->revenue_bysampling_order_nonkontrak_exist ?? 0) + ($cek->revenue_bysampling_order_kontrak_exist ?? 0), 0, ',', '.') . " \nNew : Rp " . number_format(($cek->revenue_bysampling_order_nonkontrak_new ?? 0) + ($cek->revenue_bysampling_order_kontrak_new ?? 0), 0, ',', '.')],
                ];

                $tahun  = explode('-', $periode)[0];
                $allKpi = SalesKpi::where('karyawan_id', $request->karyawan_id)
                    ->where('periode', 'like', $tahun . '-%')
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

                $sumall = SalesKpi::where('karyawan_id', $request->karyawan_id)
                    ->where('periode', $periode)
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

                return response()->json([
                    'heading'  => $return,
                    'table'    => null,
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

}
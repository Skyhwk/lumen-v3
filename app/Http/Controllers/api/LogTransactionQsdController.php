<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\QsdForecastTransactionLog;
use App\Models\QsdRevenueTransactionLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class LogTransactionQsdController extends Controller
{
    private const BULAN_LABEL = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember',
    ];

    private const MIN_PERIOD = '2026-06';

    public function summary(Request $request)
    {
        $periode = $this->resolvePeriode($request->input('periode'));

        $totalRevenue = (float) DB::table('daily_qsd')
            ->whereNotNull('tanggal_kelompok')
            ->whereRaw("DATE_FORMAT(tanggal_kelompok, '%Y-%m') = ?", [$periode])
            ->sum('total_revenue');

        $totalForecast = (float) DB::table('forecast_sp')
            ->whereNotNull('tanggal_sampling_min')
            ->whereRaw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m') = ?", [$periode])
            ->sum('revenue_forecast');
        return response()->json([
            'success'        => true,
            'periode'        => $periode,
            'periode_label'  => $this->formatPeriodeLabel($periode),
            'total_revenue'  => $totalRevenue,
            'total_forecast' => $totalForecast,
        ]);
    }

    public function revenueIndex(Request $request)
    {
        $periode = $this->resolvePeriode($request->input('periode'));

        $tahun = substr($periode, 0, 4); // Menghasilkan "2026"
        $bulan = substr($periode, 5, 2); // Menghasilkan "06"
        
        // Query Utama untuk Datatables (Menampilkan SEMUA jejak log)
        $data = QsdRevenueTransactionLog::query()
            ->whereYear('tanggal_kelompok', $tahun)
            ->whereMonth('tanggal_kelompok', $bulan)
            ->orderByDesc('created_at');

        // Perhitungan Grand Total yang Benar (Penambahan - Pengurangan)
        $grandTotal = QsdRevenueTransactionLog::query()
            ->whereYear('tanggal_kelompok', $tahun)
            ->whereMonth('tanggal_kelompok', $bulan)
            ->selectRaw('SUM(CASE WHEN status = "penambahan" THEN revenue ELSE -revenue END) as total_bersih')
            ->value('total_bersih') ?? 0; // Kasih default 0 jika null

        return Datatables::of($data)
            ->filterColumn('created_at', fn ($query, $keyword) => $this->filterDateColumn($query, 'created_at', $keyword))
            ->filterColumn('no_order', fn ($query, $keyword) => $this->filterLike($query, 'no_order', $keyword))
            ->filterColumn('periode', fn ($query, $keyword) => $this->filterPeriodeColumn($query, $keyword))
            ->filterColumn('revenue', fn ($query, $keyword) => $this->filterNumericColumn($query, 'revenue', $keyword))
            ->filterColumn('total', fn ($query, $keyword) => $this->filterNumericColumn($query, 'total', $keyword))
            ->filterColumn('status', fn ($query, $keyword) => $this->filterStatusColumn($query, $keyword))
            ->editColumn('periode', fn ($row) => $this->formatPeriodeLabel($row->periode))
            ->editColumn('revenue', fn ($row) => (float) $row->revenue)
            ->editColumn('total', fn ($row) => (float) $row->total)
            // Opsional: Bikin UI lebih jelas, pengurangan dikasih tanda minus di view
            ->editColumn('status', fn ($row) => ucfirst($row->status)) 
            ->with('grand_total', (float) $grandTotal)
            ->make(true);
    }

    public function forecastIndex(Request $request)
    {
        try {
            $periode = $this->resolvePeriode($request->input('periode')); // "2026-06"
            
            // 1. Pecah periode menjadi tahun dan bulan
            $tahun = substr($periode, 0, 4); // Menghasilkan "2026"
            $bulan = substr($periode, 5, 2); // Menghasilkan "06"

            // 2. Filter berdasarkan kolom periode (format YYYY-MM)
            $data = QsdForecastTransactionLog::query()
                ->where(function ($q) use ($tahun, $bulan) {
                    $q->where(function ($sub) use ($tahun, $bulan) {
                        $sub->whereYear('tanggal_sampling_min', $tahun)
                            ->whereMonth('tanggal_sampling_min', $bulan);
                    })->orWhere(function ($sub) use ($tahun, $bulan) {
                        $sub->whereNull('tanggal_sampling_min')
                            ->whereYear('created_at', $tahun)
                            ->whereMonth('created_at', $bulan);
                    });
                })
                ->orderByDesc('created_at');

            // Exclude forecast yang sudah jadi order (forecast_order = true)
            $grandTotal = QsdForecastTransactionLog::query()
                ->whereYear('tanggal_sampling_min', $tahun)
                ->whereMonth('tanggal_sampling_min', $bulan)
                ->where('forecast_order', 0)
                ->sum('revenue_forecast');

            return Datatables::of($data)
                ->filterColumn('created_at', fn ($query, $keyword) => $this->filterDateColumn($query, 'created_at', $keyword))
                ->filterColumn('no_penawaran', fn ($query, $keyword) => $this->filterLike($query, 'no_penawaran', $keyword))
                ->filterColumn('periode', fn ($query, $keyword) => $this->filterPeriodeColumn($query, $keyword))
                ->filterColumn('revenue_forecast', fn ($query, $keyword) => $this->filterNumericColumn($query, 'revenue_forecast', $keyword))
                ->filterColumn('total', fn ($query, $keyword) => $this->filterNumericColumn($query, 'total', $keyword))
                ->filterColumn('status', fn ($query, $keyword) => $this->filterStatusColumn($query, $keyword))
                ->editColumn('periode', fn ($row) => $this->formatPeriodeLabel($row->periode))
                ->editColumn('revenue_forecast', fn ($row) => (float) $row->revenue_forecast)
                ->editColumn('total', fn ($row) => (float) $row->total)
                ->editColumn('status', fn ($row) => ucfirst($row->status))
                ->with('grand_total', $grandTotal)
                ->make(true);

        } catch (\Throwable $e) {
            // Menangkap semua tipe error termasuk Fatal Error (Throwable)
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 200);
        }
    }

    private function resolvePeriode(?string $periode): string
    {
        $current = Carbon::now('Asia/Jakarta')->format('Y-m');

        if (empty($periode) || !preg_match('/^\d{4}-\d{2}$/', $periode)) {
            return $current;
        }

        if ($periode > $current) {
            return $current;
        }

        if ($periode < self::MIN_PERIOD) {
            return self::MIN_PERIOD;
        }

        return $periode;
    }

    private function formatPeriodeLabel(?string $periode): string
    {
        if (empty($periode) || !str_contains($periode, '-')) {
            return '-';
        }

        [$year, $month] = explode('-', $periode);

        return (self::BULAN_LABEL[$month] ?? $month) . ' ' . $year;
    }

    private function filterLike($query, string $column, ?string $keyword): void
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return;
        }

        $query->where($column, 'like', '%' . $keyword . '%');
    }

    private function filterNumericColumn($query, string $column, ?string $keyword): void
    {
        $keyword = preg_replace('/[^\d]/', '', (string) $keyword);
        if ($keyword === '') {
            return;
        }

        $query->where($column, 'like', '%' . $keyword . '%');
    }

    private function filterDateColumn($query, string $column, ?string $keyword): void
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return;
        }

        $query->where($column, 'like', '%' . $keyword . '%');
    }

    private function filterStatusColumn($query, ?string $keyword): void
    {
        $keyword = strtolower(trim((string) $keyword));
        if ($keyword === '') {
            return;
        }

        $normalized = str_replace(['+', '-'], '', $keyword);

        if (str_contains($normalized, 'tambah') || str_contains($normalized, 'plus')) {
            $query->where('status', 'penambahan');
            return;
        }

        if (str_contains($normalized, 'kurang') || str_contains($normalized, 'minus')) {
            $query->where('status', 'pengurangan');
            return;
        }

        $query->where('status', 'like', '%' . $normalized . '%');
    }

    private function filterPeriodeColumn($query, ?string $keyword): void
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return;
        }

        $query->where(function ($sub) use ($keyword) {
            $sub->where('periode', 'like', '%' . $keyword . '%');

            foreach (self::BULAN_LABEL as $monthNum => $label) {
                if (stripos($label, $keyword) !== false) {
                    $sub->orWhere('periode', 'like', '%-' . $monthNum);
                }
            }
        });
    }
}

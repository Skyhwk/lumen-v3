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

        // $totalForecast = (float) DB::table('forecast_sp')
        //     ->whereNotNull('tanggal_sampling_min')
        //     ->whereRaw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m') = ?", [$periode])
        //     ->sum('revenue_forecast');
        // 1. Tarik hanya array nilainya saja (jangan di-SUM di database)
        $rawForecastData = DB::table('forecast_sp')
            ->whereNotNull('tanggal_sampling_min')
            ->whereRaw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m') = ?", [$periode])
            ->pluck('revenue_forecast'); // Ini akan menghasilkan array string: ["1980000", "7.000.000", ...]

        $totalForecast = 0;

        // 2. Jumlahkan secara manual menggunakan fungsi pembersih Anda
        foreach ($rawForecastData as $rawRevenue) {
            $totalForecast += $this->parseRevenueForecast($rawRevenue);
        }

        // Coba bandingkan nilainya sekarang
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
            ->orderColumn('created_at', fn ($query, $order) => $query->orderBy('id', $order))
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

            // 2. BASE QUERY (Sekarang murni hanya menggunakan tanggal_sampling_min)
            $baseQuery = QsdForecastTransactionLog::query()
                ->whereNotNull('tanggal_sampling_min') // Samakan dengan logic $totalForecast Anda
                ->whereYear('tanggal_sampling_min', $tahun)
                ->whereMonth('tanggal_sampling_min', $bulan);

            // 3. Clone query untuk Datatables (Menampilkan semua riwayat)
            $data = (clone $baseQuery)->orderByDesc('created_at');

            // 4. Clone query untuk Grand Total dengan Logika Ledger (Penambahan - Pengurangan)
            $grandTotal = (clone $baseQuery)
                ->selectRaw('SUM(CASE WHEN status = "penambahan" THEN revenue_forecast ELSE -revenue_forecast END) as total_bersih')
                ->value('total_bersih') ?? 0;

            return Datatables::of($data)
                ->orderColumn('created_at', fn ($query, $order) => $query->orderBy('id', $order))
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
                ->with('grand_total', (float) $grandTotal)
                ->make(true);

        } catch (\Throwable $e) {
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

    private function parseRevenueForecast($raw): float
    {
        $str = trim((string) $raw);

        if ($str === '' || $str === null) {
            return 0.0;
        }

        // Hapus prefix non-numerik (Rp, IDR, $, dll)
        $str = preg_replace('/^[^0-9\-]+/', '', $str);

        // Deteksi format: apakah ada koma sebagai desimal (Indonesia) atau titik (International)
        // Contoh Indonesia: "2.000.000" atau "2.000.000,50"
        // Contoh International: "2,000,000" atau "2,000,000.50"

        if (preg_match('/,\d{1,2}$/', $str)) {
            // Koma di akhir dengan 1-2 digit = desimal (format Indonesia)
            // "2.000.000,50" → hapus titik → ganti koma jadi titik
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } else {
            // Format lain: hapus semua pemisah ribuan (titik dan koma)
            // "2.000.000" → "2000000"
            // "2,000,000" → "2000000"
            $str = str_replace(['.', ','], '', $str);
        }

        // Bersihkan sisa karakter non-numerik
        $str = preg_replace('/[^0-9.\-]/', '', $str);

        return round((float) $str, 2);
    }
}

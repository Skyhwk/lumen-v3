<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKategori;
use App\Models\Parameter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringParameterController extends Controller
{
    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', 1)->get(['id', 'nama_kategori']);

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data kategori berhasil diambil',
        ]);
    }

    public function getParameter(Request $request)
    {
        $kategoriId = $request->kategori_id;

        if (empty($kategoriId)) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori wajib dipilih',
                'data' => [],
            ], 422);
        }

        $data = Parameter::select('id', 'nama_lab')
            ->where('id_kategori', $kategoriId)
            ->where('is_active', true)
            ->orderBy('nama_lab')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data parameter berhasil diambil',
        ]);
    }

    public function index(Request $request)
    {
        $tahun = (int) ($request->tahun ?? 0);
        $bulan = (int) ($request->bulan ?? 0);
        $kategori = trim((string) ($request->kategori ?? ''));
        $parameter = trim((string) ($request->parameter ?? ''));

        if ($tahun < 2000 || $tahun > 2100) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun tidak valid',
                'data' => [],
            ], 422);
        }

        if ($bulan < 1 || $bulan > 12) {
            return response()->json([
                'success' => false,
                'message' => 'Bulan tidak valid',
                'data' => [],
            ], 422);
        }

        if ($kategori === '') {
            return response()->json([
                'success' => false,
                'message' => 'Kategori wajib dipilih',
                'data' => [],
            ], 422);
        }

        if ($parameter === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parameter wajib dipilih',
                'data' => [],
            ], 422);
        }

        $rows = $this->monitoringOrderScanQuery($kategori, $parameter)
            ->whereYear('ssa.created_at', $tahun)
            ->whereMonth('ssa.created_at', $bulan)
            ->select(
                DB::raw('DATE(ssa.created_at) as tanggal_scan'),
                DB::raw('COUNT(DISTINCT od.no_sampel) as total_analisa')
            )
            ->groupBy(DB::raw('DATE(ssa.created_at)'))
            ->orderBy('tanggal_scan')
            ->get();

        $totalsByDate = $rows->mapWithKeys(function ($row) {
            $key = Carbon::parse($row->tanggal_scan)->format('Y-m-d');

            return [$key => (int) $row->total_analisa];
        });

        $start = Carbon::createFromDate($tahun, $bulan, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $data = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $tanggal = $date->format('Y-m-d');
            $data->push([
                'tanggal_scan' => $tanggal,
                'hari' => $this->namaHariIndonesia($date),
                'total_analisa' => $totalsByDate->get($tanggal, 0),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data monitoring parameter berhasil diambil',
        ]);
    }

    public function detail(Request $request)
    {
        $kategori = trim((string) ($request->kategori ?? ''));
        $parameter = trim((string) ($request->parameter ?? ''));
        $tanggalScan = trim((string) ($request->tanggal_scan ?? ''));

        if ($kategori === '') {
            return response()->json([
                'success' => false,
                'message' => 'Kategori wajib dipilih',
                'data' => [],
            ], 422);
        }

        if ($parameter === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parameter wajib dipilih',
                'data' => [],
            ], 422);
        }

        if ($tanggalScan === '') {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal scan wajib diisi',
                'data' => [],
            ], 422);
        }

        try {
            $tanggal = Carbon::parse($tanggalScan)->format('Y-m-d');
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Format tanggal tidak valid',
                'data' => [],
            ], 422);
        }

        $rows = $this->monitoringOrderScanQuery($kategori, $parameter)
            ->whereDate('ssa.created_at', $tanggal)
            ->select(
                'od.no_sampel',
                DB::raw('MAX(ssa.created_at) as waktu_scan')
            )
            ->groupBy('od.no_sampel')
            ->orderByDesc('waktu_scan')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'message' => 'Detail nomor sampel berhasil diambil',
        ]);
    }

    private function monitoringOrderScanQuery(string $kategori, string $parameter)
    {
        $parameterNeedle = '%"' . str_replace(['%', '_'], ['\%', '\_'], $parameter) . '"%';

        return DB::table('order_detail as od')
            ->join('scan_sampel_analis as ssa', function ($join) {
                $join->on('od.no_sampel', '=', 'ssa.no_sampel')
                    ->where('ssa.is_active', 1);
            })
            ->where('od.is_active', 1)
            ->where('od.kategori_2', $kategori)
            ->where('od.parameter', 'like', $parameterNeedle);
    }

    private function namaHariIndonesia(Carbon $date): string
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return $days[$date->dayOfWeek] ?? '-';
    }
}

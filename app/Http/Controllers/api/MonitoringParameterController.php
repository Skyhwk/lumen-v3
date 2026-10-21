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

        $parameterNeedle = '%"' . str_replace(['%', '_'], ['\%', '\_'], $parameter) . '"%';

        $rows = DB::table('order_detail as od')
            ->join('scan_sampel_analis as ssa', function ($join) {
                $join->on('od.no_sampel', '=', 'ssa.no_sampel')
                    ->where('ssa.is_active', 1);
            })
            ->where('od.is_active', 1)
            ->where('od.kategori_2', $kategori)
            ->where('od.parameter', 'like', $parameterNeedle)
            ->whereYear('ssa.created_at', $tahun)
            ->whereMonth('ssa.created_at', $bulan)
            ->select(
                DB::raw('DATE(ssa.created_at) as tanggal_scan'),
                DB::raw('COUNT(DISTINCT od.no_sampel) as total_analisa')
            )
            ->groupBy(DB::raw('DATE(ssa.created_at)'))
            ->orderBy('tanggal_scan')
            ->get();

        $data = $rows->map(function ($row) {
            $date = Carbon::parse($row->tanggal_scan);

            return [
                'tanggal_scan' => $date->format('Y-m-d'),
                'hari' => $this->namaHariIndonesia($date),
                'total_analisa' => (int) $row->total_analisa,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data monitoring parameter berhasil diambil',
        ]);
    }

    private function namaHariIndonesia(Carbon $date): string
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return $days[$date->dayOfWeek] ?? '-';
    }
}

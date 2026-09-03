<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKategori;
use App\Models\LogAnalisa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

class MonitoringKeterlambatanAnalisaController extends Controller
{
    public function index(Request $request)
    {
        $kategori = $request->kategori ?: '1-Air';
        $tahun = (int) ($request->tahun ?: Carbon::now()->year);

        $data = LogAnalisa::where('kategori_2', $kategori)
            ->whereYear('tanggal_jadwal', $tahun)
            ->where('is_active', true)
            ->whereNull('input_analisa')
            ->select(
                'id_parameter',
                'nama_parameter',
                DB::raw('COUNT(*) as total_keterlambatan')
            )
            ->groupBy('id_parameter', 'nama_parameter')
            ->orderByDesc('total_keterlambatan')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data keterlambatan hasil analisa berhasil diambil',
        ]);
    }

    public function detail(Request $request)
    {
        $kategori = $request->kategori ?: '1-Air';
        $tahun = (int) ($request->tahun ?: Carbon::now()->year);
        $namaParameter = $request->nama_parameter;

        if (empty($namaParameter)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter wajib diisi',
            ], 422);
        }

        $data = LogAnalisa::where('kategori_2', $kategori)
            ->whereYear('tanggal_jadwal', $tahun)
            ->where('nama_parameter', $namaParameter)
            ->where('is_active', true)
            ->whereNull('input_analisa')
            ->select(
                'no_sampel',
                'id_parameter',
                'nama_parameter',
                'tanggal_jadwal',
                'ftc_verifier',
                'ftc_laboratory',
                'input_analisa'
            )
            ->orderBy('ftc_verifier', 'asc');

        return DataTables::of($data)
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', "%{$keyword}%");
            })
            ->filterColumn('id_parameter', function ($query, $keyword) {
                $query->where('id_parameter', 'like', "%{$keyword}%");
            })
            ->filterColumn('ftc_laboratory', function ($query, $keyword) {
                $query->where('ftc_laboratory', 'like', "%{$keyword}%");
            })
            ->filterColumn('ftc_verifier', function ($query, $keyword) {
                $query->where('ftc_verifier', 'like', "%{$keyword}%");
            })
            ->filterColumn('input_analisa', function ($query, $keyword) {
                $query->where('input_analisa', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_parameter', function ($query, $keyword) {
                $query->where('nama_parameter', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal_jadwal', function ($query, $keyword) {
                $query->where('tanggal_jadwal', 'like', "%{$keyword}%");
            })
            ->orderColumn('ftc_laboratory', 'ftc_laboratory $1')
            ->orderColumn('ftc_verifier', 'ftc_verifier $1')
            ->orderColumn('input_analisa', 'input_analisa $1')
            ->orderColumn('no_sampel', 'no_sampel $1')
            ->orderColumn('id_parameter', 'id_parameter $1')
            ->orderColumn('nama_parameter', 'nama_parameter $1')
            ->orderColumn('tanggal_jadwal', 'tanggal_jadwal $1')
            ->make(true);
    }

    public function indexPersentase(Request $request)
    {
        try {
            $kategori = $request->kategori ?: '1-Air';
            $tanggal = $request->tanggal ?: Carbon::today()->format('Y-m-d');

            $data = LogAnalisa::where('kategori_2', $kategori)
                ->where('tanggal_jadwal', $tanggal)
                ->where('is_active', true)
                ->select(
                    'id_parameter',
                    'nama_parameter',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN input_analisa IS NULL THEN 1 ELSE 0 END) as total_belum'),
                    DB::raw('SUM(CASE WHEN input_analisa IS NOT NULL THEN 1 ELSE 0 END) as total_sudah')
                )
                ->groupBy('id_parameter', 'nama_parameter')
                ->get()
                ->map(function ($item) {
                    $total = (int) $item->total;
                    $persentase = $total > 0
                        ? round(((int) $item->total_belum / $total) * 100, 2)
                        : 0;

                    $item->persentase = $persentase;
                    $item->indikator = $persentase >= 50 ? 'naik' : 'turun';

                    return $item;
                })
                ->sortByDesc('persentase')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Data persentase keterlambatan berhasil diambil',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data persentase: ' . $th->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function detailPersentase(Request $request)
    {
        $kategori = $request->kategori ?: '1-Air';
        $tanggal = $request->tanggal ?: Carbon::today()->format('Y-m-d');
        $namaParameter = $request->nama_parameter;

        if (empty($namaParameter)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter wajib diisi',
            ], 422);
        }

        $data = LogAnalisa::where('kategori_2', $kategori)
            ->where('tanggal_jadwal', $tanggal)
            ->where('nama_parameter', $namaParameter)
            ->where('is_active', true)
            ->when($request->status_analisa === 'belum', fn ($q) => $q->whereNull('input_analisa'))
            ->when($request->status_analisa === 'sudah', fn ($q) => $q->whereNotNull('input_analisa'))
            ->select(
                'no_sampel',
                'id_parameter',
                'ftc_laboratory',
                'ftc_verifier',
                'nama_parameter',
                'input_analisa'
            )
            ->orderBy('ftc_verifier', 'asc');

        return DataTables::of($data)
            ->addColumn('status_analisa', function ($row) {
                return $row->input_analisa ? 'sudah' : 'belum';
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', "%{$keyword}%");
            })
            ->filterColumn('ftc_laboratory', function ($query, $keyword) {
                $query->where('ftc_laboratory', 'like', "%{$keyword}%");
            })
            ->filterColumn('ftc_verifier', function ($query, $keyword) {
                $query->where('ftc_verifier', 'like', "%{$keyword}%");
            })
            ->orderColumn('ftc_laboratory', 'ftc_laboratory $1')
            ->orderColumn('ftc_verifier', 'ftc_verifier $1')
            ->orderColumn('no_sampel', 'no_sampel $1')
            ->make(true);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', 1)->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data kategori berhasil diambil',
        ]);
    }
}

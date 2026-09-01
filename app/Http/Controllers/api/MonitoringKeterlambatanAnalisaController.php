<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKategori;
use App\Models\MonitorKeterlambatanAnalisa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class MonitoringKeterlambatanAnalisaController extends Controller
{
    public function index(Request $request)
    {
        $kategori = $request->kategori ?: '1-Air';

        $data = MonitorKeterlambatanAnalisa::where('kategori_2', $kategori)
            ->where('is_active', true)
            ->select(
                'nama_parameter',
                DB::raw('COUNT(*) as total_keterlambatan')
            )
            ->groupBy('nama_parameter')
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
        $namaParameter = $request->nama_parameter;

        if (empty($namaParameter)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter wajib diisi',
            ], 422);
        }

        $data = MonitorKeterlambatanAnalisa::where('kategori_2', $kategori)
            ->where('nama_parameter', $namaParameter)
            ->where('is_active', true)
            ->select('no_sampel', 'ftc_laboratory', 'ftc_verifier', 'nama_parameter')
            ->orderBy('ftc_laboratory', 'asc');

        return DataTables::of($data)
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', "%{$keyword}%");
            })
            ->filterColumn('ftc_laboratory', function ($query, $keyword) {
                $query->where('ftc_laboratory', 'like', "%{$keyword}%");
            })
            ->filterColumn('ftc_verifier', function ($query, $keyword) {
                $query->where('ftc_verifier', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_parameter', function ($query, $keyword) {
                $query->where('nama_parameter', 'like', "%{$keyword}%");
            })
            ->orderColumn('ftc_laboratory', 'ftc_laboratory $1')
            ->orderColumn('ftc_verifier', 'ftc_verifier $1')
            ->orderColumn('no_sampel', 'no_sampel $1')
            ->orderColumn('nama_parameter', 'nama_parameter $1')
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

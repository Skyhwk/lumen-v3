<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

use App\Models\QuotationNonKontrak;
use App\Models\MasterCabang;
use App\Models\MasterKaryawan;
use App\Models\ParameterSar;
use App\Models\MasterRegulasi;

class QuotationSarController extends Controller
{
    public function index(Request $request)
    {
        $data = QuotationNonKontrak::where('is_active', true)->where('status_sampling', 'SAR')->where('id_cabang', $request->id_cabang)->whereYear('tanggal_penawaran', $request->periode);
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        switch ($jabatan) {
            case 24: // Sales Staff
                $data = $data->where('sales_id', $this->user_id);
                break;
            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('id')
                    ->toArray();
                array_push($bawahan, $this->user_id);
                $data = $data->whereIn('sales_id', $bawahan);
                break;
        }
        return Datatables::of($data)->make(true);
    }

    public function getCabang(Request $request)
    {
        $cabang = MasterCabang::where('is_active', true)->get();
        return response()->json($cabang);
    }

    public function getRegulasi(Request $request)
    {
        $query = MasterRegulasi::with('bakumutu')->where('is_active', true);

        if ($request->has('term') && $request->term !== null && $request->term !== "") {
            $searchTerm = $request->term;
            $query = $query->where(function ($q) use ($searchTerm) {
                $q->where('peraturan', 'like', "%{$searchTerm}%")
                  ->orWhere('deskripsi', 'like', "%{$searchTerm}%");
            });
        }

        $page = $request->get('page', 1);
        $perPage = 20;
        $results = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => $results->count() === $perPage
            ]
        ]);
    }

    public function getParameter(Request $request)
    {
        $regulasi = ParameterSar::with('hargaParameter')->where('is_active', true)->get();
        return response()->json($regulasi);
    }

    public function getKategori(Request $request)
    {
        return response()->json([
            'id' => "00",
            'nama_kategori' => "Quick Test Parameter"
        ]);
    }

}
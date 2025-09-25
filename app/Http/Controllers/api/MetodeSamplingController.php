<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\MasterKategori;
use App\Models\MasterSubKategori;
use App\Models\MetodeSampling;
use App\Models\Parameter;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class MetodeSamplingController extends Controller
{
    public function index()
    {
        $aksesMenus = MetodeSampling::where('is_active', true)->orderBy('created_at', 'desc');
        return Datatables::of($aksesMenus)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Pisahkan sub_kategori dan handle jika tidak ada tanda "-"
            $subKategoriParts = explode('-', $request->sub_kategori);
            $subKategori = isset($subKategoriParts[1])
                ? strtoupper($subKategoriParts[1])
                : strtoupper($request->sub_kategori);

            if ($request->id != '') {
                $metode = MetodeSampling::find($request->id);

                if ($metode) {
                    $metode->kategori = strtoupper($request->kategori);
                    $metode->sub_kategori = $subKategori;
                    $metode->status_sampling = strtoupper($request->status_sampling);
                    $metode->metode_sampling = strtoupper($request->metode_sampling);
                    $metode->status_akreditasi = strtoupper($request->status_akreditasi);
                    $metode->status_parameter = strtoupper($request->status_parameter);
                    $metode->updated_by = $this->karyawan;
                    $metode->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $metode->save();
                } else {
                    return response()->json(['message' => 'Metode Sampling tidak ditemukan'], 404);
                }
            } else {
                $metode = new MetodeSampling;
                $metode->kategori = strtoupper($request->kategori);
                $metode->sub_kategori = $subKategori;
                $metode->status_sampling = strtoupper($request->status_sampling);
                $metode->metode_sampling = strtoupper($request->metode_sampling);
                $metode->status_akreditasi = strtoupper($request->status_akreditasi);
                $metode->status_parameter = strtoupper($request->status_parameter);
                $metode->created_by = $this->karyawan;
                $metode->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $metode->save();
            }

            DB::commit();
            return response()->json(['message' => 'Data telah disimpan'], 201);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }


    public function delete(Request $request)
    {
        if ($request->id != '') {
            $data = MetodeSampling::where('id', $request->id)->first();
            if ($data) {
                $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->deleted_by = $this->karyawan;
                $data->is_active = false;
                $data->save();

                return response()->json(['message' => 'Metode Sampling successfully deleted'], 201);
            }

            return response()->json(['message' => 'Data Not Found.!'], 401);
        } else {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', true)->select('id', 'nama_kategori')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 201);
    }

    public function getSubKategori(Request $request)
    {
        $kategoriIdArray = explode('-', $request->id_kategori);
        $kategoriId = isset($kategoriIdArray[0]) ? $kategoriIdArray[0] : null;

        $data = MasterSubKategori::where('is_active', true)
            ->where('id_kategori', $kategoriId)
            ->select('id', 'nama_sub_kategori')
            ->get();

        return response()->json(['message' => 'Data has been shown', 'data' => $data], 200);
    }

    public function getParameter(Request $request)
    {
        $kategoriIdArray = explode('-', $request->id_kategori);
        $kategoriId = isset($kategoriIdArray[0]) ? $kategoriIdArray[0] : null;

        $data = Parameter::where('is_active', true)
            ->where('id_kategori', $kategoriId)
            ->select('id', 'nama_lab')
            ->get();

        return response()->json(['message' => 'Data has been shown', 'data' => $data], 200);
    }
}



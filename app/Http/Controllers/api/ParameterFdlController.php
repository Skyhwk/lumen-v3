<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Str;
use App\Models\Parameter;
use App\Models\ParameterFdl;
use App\Models\MasterKategori;

class ParameterFdlController extends Controller
{
    public function index()
    {
        DB::beginTransaction();
        try {
            $aksesMenus = ParameterFdl::where('is_active', true);
            return Datatables::of($aksesMenus)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(), 
                'line' => $e->getLine(), 
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function getAllKategori(Request $request){
        $kategori = MasterKategori::select('id', 'nama_kategori')->get();
        return response()->json($kategori, 200);
    }

    public function getParameter(Request $request){
        $kategoriIds = explode('-', $request->kategori);

        // Pastikan ada index 1 sebelum mengaksesnya
        if (!isset($kategoriIds[1])) {
            return response()->json(['error' => 'Kategori tidak valid'], 400);
        }

        $kategori = Parameter::select('id', 'nama_lab')->whereIn('id_kategori', [$kategoriIds[0]])->get();

        return response()->json($kategori, 200);
    }


    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id != '') {
                $parameter = ParameterFdl::find($request->id);

                if ($parameter) {
                    $parameter->nama_fdl = strtolower(str_replace(' ', '_', $request->nama_fdl));
                    $parameter->parameters = json_encode($request->parameter, JSON_UNESCAPED_UNICODE);
                    $parameter->kategori = $request->category;
                    $parameter->updated_by = $this->karyawan;
                    $parameter->updated_at = date('Y-m-d H:i:s');
                    $parameter->save();
                } else {
                    return response()->json(['message' => 'Data tidak ditemukan'], 404);
                }
            } else {
                $parameter = new ParameterFdl();
                $parameter->nama_fdl = strtolower(str_replace(' ', '_', $request->nama_fdl));
                $parameter->parameters = json_encode($request->parameter, JSON_UNESCAPED_UNICODE);
                $parameter->kategori = $request->category;
                $parameter->created_by = $this->karyawan;
                $parameter->created_at = date('Y-m-d H:i:s');
                $parameter->is_active = 1;
                $parameter->save();
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
        if($request->id !=''){
            $data = ParameterFdl::where('id', $request->id)->first();
            if($data){
                $data->deleted_at = date('Y-m-d H:i:s');
                $data->deleted_by = $this->karyawan;
                $data->is_active = false;
                $data->save();

                return response()->json(['message' => 'Parameter successfully deleted'], 201);
            }

            return response()->json(['message' => 'Data Not Found.!'], 401);
        } else {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }
    }
}



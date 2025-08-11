<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\MasterKategori;
use App\Models\Parameter;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;

class ParameterController extends Controller
{
    public function index()
    {
        $aksesMenus = Parameter::where('is_active', true);
        return Datatables::of($aksesMenus)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id != '') {
                $parameter = Parameter::find($request->id);

                if ($parameter) {

                    $parameter->nama_lab = $request->nama_lab;
                    $parameter->nama_lhp = $request->nama_lhp;
                    $parameter->nama_regulasi = $request->nama_regulasi;

                    if($request->method!='')$parameter->method = $request->method;
                    if($request->satuan!='')$parameter->satuan = $request->satuan;
                    if($request->status!='')$parameter->status = $request->status;

                    $parameter->updated_by = $this->karyawan;
                    $parameter->updated_at = DATE('Y-m-d H:i:s');
                    $parameter->save();
                } else {
                    return response()->json(['message' => 'Sub kategori tidak ditemukan'], 404);
                }
            } else {
                if ($request->id_kategori == '') {
                    return response()->json(['message' => 'Silahkan pilih kategori'], 400);
                }

                $cek_kategori = MasterKategori::where('id', $request->id_kategori)->first();
                if ($cek_kategori) {

                    $parameter = new Parameter;
                    $parameter->nama_kategori = $cek_kategori->nama_kategori;
                    $parameter->id_kategori = $request->id_kategori;

                    $parameter->nama_lab = $request->nama_lab;
                    $parameter->nama_lhp = $request->nama_lhp;
                    $parameter->nama_regulasi = $request->nama_regulasi;

                    if($request->method!='')$parameter->method = $request->method;
                    if($request->satuan!='')$parameter->satuan = $request->satuan;
                    if($request->status!='')$parameter->status = $request->status;

                    $parameter->created_by = $this->karyawan;
                    $parameter->created_at = DATE('Y-m-d H:i:s');
                    $parameter->save();
                } else {
                    return response()->json(['message' => 'Kategori tidak ditemukan'], 400);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Data telah disimpan'], 201);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function delete(Request $request){
        if($request->id !=''){
            $data = Parameter::where('id', $request->id)->first();
            if($data){
                $data->deleted_at = Date('Y:m:d H:i:s');
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

    public function getKategori(Request $request){
        $data = MasterKategori::where('is_active', true)->select('id', 'nama_kategori')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data'=>$data], 201);
    }

    public function getMethod(Request $request){
        $data = Parameter::where('is_active', true)->select('method')->groupBy('method')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data'=>$data], 201);
    }
}



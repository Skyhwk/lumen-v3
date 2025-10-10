<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KuotaAnalisaParameter;
use App\Models\MasterKategori;
use App\Models\OrderDetail;
use App\Models\Parameter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class KuotaAnalisaLabController extends Controller
{
    public function index(Request $request)
    {
        $data = KuotaAnalisaParameter::where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function getKategori(Request $request)
    {
        $kategori = MasterKategori::select('id', 'nama_kategori')->where('is_active', true)->get();
        return response()->json([
            'data' => $kategori
        ],200);
    }

    public function getParameter(Request $request)
    {
        $kategori = Parameter::select('id', 'nama_lab')->where('id_kategori', $request->kategori_id)->where('is_active', true)->get();
        return response()->json([
            'data' => $kategori
        ],200);
    }

    public function submitData(Request $request){
        DB::beginTransaction();
        try {
            if(isset($request->id)){
                $data = KuotaAnalisaParameter::find($request->id);
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');

                $parameter_id = $request->parameter_id;
                $parameter_name = $request->parameter_name;
            }else{
                $data = new KuotaAnalisaParameter;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');

                [$parameter_id , $parameter_name] = explode('-', $request->parameter);
            }


            $data->kategori = $request->kategori;
            $data->parameter_id = $parameter_id;
            $data->parameter_name = $parameter_name;
            $data->quota = $request->quota;
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Kuota analisa parameter berhasil disimpan'
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 500);
        }
    }

    public function delete(Request $request){
        DB::beginTransaction();
        try {
            $data = KuotaAnalisaParameter::find($request->id);
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->is_active = false;
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Kuota analisa parameter berhasil dihapus'
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 500);
        }
    }
}

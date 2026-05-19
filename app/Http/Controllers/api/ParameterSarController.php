<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\ParameterSar;
use App\Models\Parameter;

class ParameterSarController extends Controller
{
    public function index(Request $request)
    {
        $data = ParameterSar::where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function getListofParameters(Request $request)
    {
        $data = Parameter::where('is_active', true)->get();
        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        $cekParameter = ParameterSar::where('id_parameter', $request->id_parameter)->where('is_active', true)->first();
        if($cekParameter) {
            return response()->json([
                'message' => 'Parameter sudah ada',
            ], 400);
        }
        $parameter = Parameter::where('id', $request->id_parameter)->first();
        if(!$parameter) {
            return response()->json([
                'message' => 'Parameter tidak ditemukan',
            ], 400);
        }
        $parameterSar = new ParameterSar();
        $parameterSar->id_parameter = $request->id_parameter;
        $parameterSar->nama_lab = $parameter->nama_lab;
        $parameterSar->nama_regulasi = $parameter->nama_regulasi;
        $parameterSar->nilai_rujukan  = $request->nilai_rujukan;
        $parameterSar->created_by = $this->karyawan;
        $parameterSar->created_at = Carbon::now()->format('Y-m-d H:i:s');
        $parameterSar->save();

        return response()->json([
            'message' => 'Parameter berhasil disimpan'
        ], 200);
    }

    public function updateColumn(Request $request)
    {
        $parameterSar = ParameterSar::where('id', $request->id)->first();
        if(!$parameterSar) {
            return response()->json([
                'message' => 'Parameter tidak ditemukan',
            ], 400);
        }

        $column = $request->column;

        $parameterSar->$column = $request->value;
        $parameterSar->updated_by = $this->karyawan;
        $parameterSar->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $parameterSar->save();

        return response()->json([
            'message' => 'Parameter berhasil diubah'
        ], 200);
    }

    public function delete(Request $request)
    {
        $parameterSar = ParameterSar::where('id', $request->id)->first();
        if(!$parameterSar) {
            return response()->json([
                'message' => 'Parameter tidak ditemukan',
            ], 400);
        }

        $parameterSar->is_active = false;
        $parameterSar->deleted_by = $this->karyawan;
        $parameterSar->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
        $parameterSar->save();

        return response()->json([
            'message' => 'Parameter berhasil dihapus'
        ], 200);
    }

}
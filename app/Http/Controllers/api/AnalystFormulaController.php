<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AnalystFormula;
use App\Models\MasterKategori;
use App\Models\Parameter;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Str;

class AnalystFormulaController extends Controller
{
    public function index(Request $request)
    {
        $data = AnalystFormula::with('templete_analyst')
            ->where('is_active', 1);

        // if (isset($request->template_analyst->function) && $request->template_analyst->function != '') {
        //     $data->where('template_analyst->function', 'like', '%' . $request->template_analyst->function . '%');
        // }

        if (isset($request->created_by) && $request->created_by != '') {
            $data->where('created_by', $request->created_by);
        }

        return Datatables::of($data)->make(true);
    }

    public function getCategory()
    {
        $data = MasterKategori::where('is_active', true)->select('id', 'nama_kategori')->get();
        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        try {
            $data = Parameter::where('is_active', true)
                ->where('id_kategori', $request->id_kategori)
                ->select('id', 'nama_lab')
                ->get();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
                'status' => '500'
            ], 500);
        }
    }

    public function create(Request $request)
    {
        try {
            $data = new AnalystFormula;
            $data->id_parameter = explode(';',$request->parameter)[0];
            $data->parameter  = explode(';',$request->parameter)[1];
            $data->id_function = explode(';',$request->id_function)[0];
            $data->function = explode(';',$request->id_function)[1];
            $data->created_by = $this->karyawan;
            $data->created_at = DATE('Y-m-d H:i:s');
            $data->save();

            return response()->json([
                'message' => "Add Formula Success",
                'status' => '200'
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }


    public function update(Request $request)
    {
        try {
            $data = AnalystFormula::where('id', $request->id)->firstOrFail();
            $data->id_parameter = $request->id_parameter;
            $data->parameter = $request->parameter;
            $data->id_function = $request->id_function;
            $data->function = $request->function;
            $data->updated_by = $this->karyawan;
            $data->updated_at = DATE('Y-m-d H:i:s');
            $data->save();

            return response()->json([
                'message' => "Update Formula Success",
                'status' => '200'
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        try {
            $data = AnalystFormula::where('id', $request->id)->firstOrFail();
            $data->is_active = false;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = DATE('Y-m-d H:i:s');
            $data->save();
            return response()->json([
                'message' => "Delete Formula Success",
                'status' => '200'
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }
}
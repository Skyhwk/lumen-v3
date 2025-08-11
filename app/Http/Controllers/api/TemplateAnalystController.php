<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\TemplateAnalyst;
use App\Models\MasterKategori;
use App\Models\AnalystFormula;
use App\Models\Parameter;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Str;

class TemplateAnalystController extends Controller
{
    public function index(Request $request)
    {
        $data = TemplateAnalyst::where('is_active', 1);

        if (isset($request->function) && $request->function != '') {
            $data->where('function', 'like', '%' . $request->function . '%');
        }

        if (isset($request->created_by) && $request->created_by != '') {
            $data->where('created_by', $request->created_by);
        }

        return Datatables::of($data)->make(true);
    }

    public function getParameterFunction (Request $request){
        try {
            $data = AnalystFormula::with('param:id,nama_kategori')->where('id_function', $request->id_function)->where('is_active', true);
            return Datatables::of($data)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
                'status' => '500'
            ]);
        }
    }

    public function getAllParameters (Request $request){
        try {
            $data = AnalystFormula::with('param:id,nama_kategori')->where('is_active', true);
            return Datatables::of($data)->make(true);
        }catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
            ],500);
        }
    }

    public function getFunctions (Request $request){
        $data = TemplateAnalyst::where('is_active', true)->select('id', 'function')->get();
        return response()->json($data, 200);
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

    public function createTemplate(Request $request)
    {
        try {
            $data = new TemplateAnalyst;
            $data->function = $request->function;
            $data->created_by = $this->karyawan;
            $data->created_at = DATE('Y-m-d H:i:s');
            $data->save();

            return response()->json([
                'message' => "Add Template Success",
                'status' => '200'
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function deleteTemplate(Request $request)
    {
        try {
            $data = TemplateAnalyst::where('id', $request->id)->firstOrFail();
            $data->is_active = false;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = DATE('Y-m-d H:i:s');
            $data->save();
            return response()->json([
                'message' => "Delete Template Success",
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
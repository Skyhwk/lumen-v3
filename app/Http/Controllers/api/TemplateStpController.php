<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\TemplateStp;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use App\Models\Parameter;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class TemplateStpController extends Controller
{
    public function index(Request $request){
        $data = TemplateStp::with(['sample'])
            ->where('is_active', $request->active);

        if (isset($request->kategori) && $request->kategori != '') {
            $data->where('sampleName', 'like', '%' . $request->kategori . '%');
        }

        if(isset($request->name) && $request->name != '') {
            $data->where('name', 'like', '%' . $request->name . '%');
        }

        if(isset($request->created_by) && $request->created_by != '') {
            $data->where('created_by', $request->created_by);
        }

        return Datatables::of($data)
            ->addColumn('sampleName', function ($data) { 
                return $data->sample ? (string) $data->sample->nama_kategori : 'Nama tidak ditemukan'; 
            })
            ->rawColumns(['sampleName'])
            ->make(true);
    }

    public function getCategory()
    {
        $data = MasterKategori::where('is_active', true)->select('id','nama_kategori')->get();
        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        try {
            $data = Parameter::where('is_active', true)
            ->where('id_kategori', $request->id_kategori)
            ->select('id','nama_lab')
            ->get();
            return response()->json($data);
        }catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: '.$e->getMessage(),
                'status' => '500'
            ],500);
        }
    }

    public function createTemplate(Request $request){
        try {
            $param = json_encode($request->parameter);

            $data = new TemplateStp;
            $data->name = $request->name;
            $data->category_id = $request->id_kategori;
            $data->param = $param;
            $data->created_by = $this->karyawan;
            $data->created_at = DATE('Y-m-d H:i:s');
            $data->save();

            return response()->json([
                'message'=> "Add Template Success",
                'status' => '200'
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'message'=> $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function updateTemplate(Request $request){
        try {
            $param = json_encode($request->parameter);

            $data = TemplateStp::where('id', $request->id)->firstOrFail();
            $data->name = $request->name;
            $data->category_id = $request->id_kategori;
            $data->param = $param;
            $data->updated_by = $this->karyawan;
            $data->updated_at = DATE('Y-m-d H:i:s');
            $data->save();

            return response()->json([
                'message'=> "Update Template Success",
                'status' => '200'
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'message'=> $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }

    public function delete(Request $request){
        try {
            $data = TemplateStp::where('id', $request->id)->firstOrFail();
            $data->is_active = false;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = DATE('Y-m-d H:i:s');
            $data->save();
            return response()->json([
                'message'=> "Delete Template Success",
                'status' => '200'
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'message'=> $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
    }
}
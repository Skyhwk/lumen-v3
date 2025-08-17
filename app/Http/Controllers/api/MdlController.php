<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Mdl;
use App\Models\Parameter;
use App\Models\MasterKaryawan;
use App\Models\TemplateAnalyst;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use DB;

class MdlController extends Controller
{
    public function index(Request $request)
    {
        $data = Mdl::where('is_active', 1)->orderBy('id', 'DESC');

        return Datatables::of($data)
            ->filterColumn('parameter', function ($query, $keyword) {
                $query->where('parameter', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('value', function ($query, $keyword) {
                $query->where('value', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function getMdl(Request $request)
    {
        $data = Mdl::all();
        return response()->json($data);
    }

    public function getFunctionsTemplate(Request $request)
    {
        $data = TemplateAnalyst::where('is_active', 1)->orderBy('id', 'DESC');

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id == null || $request->id == '') {
                $dataMdl = new Mdl;
                $dataMdl->category_id = $request->category_id;
                $dataMdl->category_nama = $request->category_nama;
                $dataMdl->parameter_id = $request->parameter_id;
                $dataMdl->parameter_nama = $request->parameter_nama;
                $dataMdl->function = $request->function;
                $dataMdl->value = $request->value;
                $dataMdl->created_at = Carbon::now();
                $dataMdl->created_by = $this->karyawan;
                $dataMdl->save();
            }else {
                $dataMdl = Mdl::find($request->id);
                $dataMdl->category_id = $request->category_id;
                $dataMdl->category_nama = $request->category_nama;
                $dataMdl->parameter_id = $request->parameter_id;
                $dataMdl->parameter_nama = $request->parameter_nama;
                $dataMdl->function = $request->function;
                $dataMdl->value = $request->value;
                $dataMdl->save();
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
        

        return response()->json([
            'message' => 'Data hasbeen Save.!'
        ], 200);
    }

    public function getParameter(Request $request)
    {
        $idKategori = $request->input('id_kategori');

        $data = Parameter::where('is_active', 1)
            ->where('id_kategori', $idKategori)
            ->select('id', 'nama_lab')
            ->get();

        return response()->json([
            'message' => 'Data has been shown',
            'data' => $data,
        ], 200); // pakai 200 OK untuk GET-like response
    }

    public function delete($id) {
        $data = Mdl::find($id);
        $data->is_active = false;
        $data->save();
        return response()->json(['message' => 'Data berhasil dihapus'], 200);
    }
}
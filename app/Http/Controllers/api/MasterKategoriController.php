<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\MasterKategori;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use \Carbon\Carbon;

class MasterKategoriController extends Controller
{
    public function index()
    {
        $aksesMenus = MasterKategori::where('is_active', true);
        return Datatables::of($aksesMenus)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id != '') {
                // update data
                $kategori = MasterKategori::where('id', $request->id)->first();
                if($kategori){
                    $kategori->nama_kategori = $request->nama_kategori;
                    $kategori->updated_by = $this->karyawan;
                    $kategori->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $kategori->save();
                }
            } else {
                //create data
                $kategori = new MasterKategori;
                $kategori->nama_kategori = $request->nama_kategori;
                $kategori->created_by = $this->karyawan;
                $kategori->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $kategori->save();
            }

            DB::commit();
            return response()->json(['message' => 'Data Hasbeen Save'], 201);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['message' => $th->getMessage()], 401);
        } 
    }

    public function delete(Request $request){
        if($request->id !=''){
            $data = MasterKategori::where('id', $request->id)->first();
            if($data){
                $data->deleted_at = Date('Y:m:d H:i:s');
                $data->deleted_by = $this->karyawan;
                $data->is_active = false;
                $data->save();

                return response()->json(['message' => 'Data Kategori successfullycdeleted'], 201);
            }

            return response()->json(['message' => 'Data Not Found.!'], 401);
        } else {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }
    }
}
<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\MasterKategori;
use App\Models\MasterSubKategori;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;

class MasterSubKategoriController extends Controller
{
    public function index()
    {
        $aksesMenus = MasterSubKategori::where('is_active', true);
        return Datatables::of($aksesMenus)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id != '') {
                $kategori = MasterSubKategori::find($request->id);

                if ($kategori) {
                    $existingSubKategori = MasterSubKategori::where('nama_sub_kategori', $request->nama_sub_kategori)
                                                            ->where('id', '!=', $request->id)
                                                            ->where('is_active', true)
                                                            ->first();
                    if ($existingSubKategori) {
                        return response()->json(['message' => 'Nama sub kategori sudah ada'], 400);
                    }

                    $kategori->nama_sub_kategori = $request->nama_sub_kategori;
                    $kategori->updated_by = $this->karyawan;
                    $kategori->updated_at = DATE('Y-m-d H:i:s');
                    $kategori->save();
                } else {
                    return response()->json(['message' => 'Sub kategori tidak ditemukan'], 404);
                }
            } else {
                if ($request->id_kategori == '') {
                    return response()->json(['message' => 'Silahkan pilih kategori'], 400);
                }

                $cek_kategori = MasterKategori::where('id', $request->id_kategori)->first();
                if ($cek_kategori) {
                    $existingSubKategori = MasterSubKategori::where('nama_sub_kategori', $request->nama_sub_kategori)
                                                            ->where('id_kategori', $request->id_kategori)
                                                            ->where('is_active', true)
                                                            ->first();
                    if ($existingSubKategori) {
                        return response()->json(['message' => 'Nama sub kategori sudah ada dalam kategori ini'], 400);
                    }

                    $kategori = new MasterSubKategori;
                    $kategori->nama_sub_kategori = $request->nama_sub_kategori;
                    $kategori->nama_kategori = $cek_kategori->nama_kategori;
                    $kategori->id_kategori = $request->id_kategori;
                    $kategori->created_by = $this->karyawan;
                    $kategori->created_at = DATE('Y-m-d H:i:s');
                    $kategori->save();
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
            $data = MasterSubKategori::where('id', $request->id)->first();
            if($data){
                $data->deleted_at = Date('Y:m:d H:i:s');
                $data->deleted_by = $this->karyawan;
                $data->is_active = false;
                $data->save();

                return response()->json(['message' => 'Data Kategori successfully deleted'], 201);
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
}



<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\MasterCabang;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;

class MasterCabangController extends Controller
{
    public function index()
    {
        $aksesMenus = MasterCabang::where('is_active', true)->get();
        return Datatables::of($aksesMenus)->make(true);
    }

    public function store(Request $request)
    {
        if ($request->id != '') {
            $data = MasterCabang::where('id', $request->id)->first();
            if ($data) {
                $existingCabang = MasterCabang::where('kode_cabang', $request->kode_cabang)
                                            ->where('id', '!=', $request->id)
                                            ->first();
                if ($existingCabang) {
                    return response()->json(['message' => 'Kode Cabang already exists'], 401);
                }

                $data->nama_cabang = $request->nama_cabang;
                $data->kode_cabang = $request->kode_cabang;
                $data->alamat_cabang = $request->alamat_cabang;
                $data->tlp_cabang = $request->tlp_cabang;
                $data->updated_at = Date('Y-m-d H:i:s');
                $data->updated_by = $this->karyawan;
                $data->save();
                return response()->json(['message' => 'Cabang updated successfully'], 200);
            }
        } else {
            $existingCabang = MasterCabang::where('kode_cabang', $request->kode_cabang)->first();
            if ($existingCabang) {
                return response()->json(['message' => 'Kode Cabang already exists'], 401);
            }
            $data = MasterCabang::create([
                'nama_cabang' => $request->nama_cabang,
                'kode_cabang' => $request->kode_cabang,
                'alamat_cabang' => $request->alamat_cabang,
                'tlp_cabang' => $request->tlp_cabang,
                'created_at' => Date('Y-m-d H:i:s'),
                'created_by' => $this->karyawan
            ]);

            return response()->json(['message' => 'Cabang created successfully'], 201);
        }
    }

    public function delete(Request $request){
        if($request->id !=''){
            $data = MasterCabang::where('id', $request->id)->first();
            if($data){
                $data->deleted_at = Date('Y:m:d H:i:s');
                $data->deleted_by = $this->karyawan;
                $data->is_active = false;
                $data->save();

                return response()->json(['message' => 'Cabang Delete successfully'], 201);
            }

            return response()->json(['message' => 'Data Not Found.!'], 401);
        } else {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }
    }
}



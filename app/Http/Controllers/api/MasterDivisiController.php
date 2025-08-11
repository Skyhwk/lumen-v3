<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterDivisi;
use Validator;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;

class MasterDivisiController extends Controller
{
    public function index(Request $request)
    {
        $divisi = MasterDivisi::where('is_active', true)->get();
        return Datatables::of($divisi)->make(true);
    }

    public function store(Request $request)
    {
        if ($request->id != '') {
            $data = MasterDivisi::where('id', $request->id)->first();
            if ($data) {
                $existingCabang = MasterDivisi::where('kode_divisi', $request->kode_divisi)->where('id', '!=', $request->id)->first();
                if ($existingCabang) {
                    return response()->json(['message' => 'Kode Divisi already exists'], 401);
                }

                $data->kode_divisi = $request->kode_divisi;
                $data->nama_divisi = $request->nama_divisi;
                $data->updated_at = Date('Y-m-d H:i:s');
                $data->updated_by = $this->karyawan;
                $data->save();
                return response()->json(['message' => 'Divisi updated successfully'], 200);
            }
        } else {
            $existingCabang = MasterDivisi::where('kode_divisi', $request->kode_divisi)->first();
            if ($existingCabang) {
                return response()->json(['message' => 'Kode Divisi already exists'], 401);
            }
            $data = MasterDivisi::create([
                'kode_divisi' => $request->kode_divisi,
                'nama_divisi' => $request->nama_divisi,
                'created_at' => Date('Y-m-d H:i:s'),
                'created_by' => $this->karyawan
            ]);

            return response()->json(['message' => 'Divisi created successfully'], 201);
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

                return response()->json(['message' => 'Divisi Delete successfully'], 201);
            }

            return response()->json(['message' => 'Data Not Found.!'], 401);
        } else {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }
    }
}

<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterJabatan;
use App\Models\MasterDivisi;
use Validator;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;

class MasterJabatanController extends Controller
{
    public function index(Request $request)
    {
        $jabatan = MasterJabatan::query()
            ->active()
            ->leftJoin('master_divisi', function ($join) {
                $join->on('master_jabatan.id_divisi', '=', 'master_divisi.id')
                    ->where('master_divisi.is_active', 1);
            })
            ->select(
                'master_jabatan.*',
                'master_divisi.nama_divisi'
            )
            ->get();

        return Datatables::of($jabatan)->make(true);
    }

    public function getDivisi(Request $request)
    {
        $data = MasterDivisi::active()
            ->select('id', 'nama_divisi')
            ->orderBy('nama_divisi')
            ->get();

        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 200);
    }

    public function store(Request $request)
    {
        if ($request->id != '') {
            $data = MasterJabatan::active()->where('id', $request->id)->first();
            if ($data) {
                $existingCabang = MasterJabatan::active()
                    ->where('kode_jabatan', $request->kode_jabatan)
                    ->where('id', '!=', $request->id)
                    ->first();
                if ($existingCabang) {
                    return response()->json(['message' => 'Kode Jabatan already exists'], 401);
                }

                if ($request->id_divisi && !MasterDivisi::active()->where('id', $request->id_divisi)->exists()) {
                    return response()->json(['message' => 'Divisi tidak valid atau tidak aktif'], 401);
                }

                $data->kode_jabatan = $request->kode_jabatan;
                $data->nama_jabatan = $request->nama_jabatan;
                $data->id_divisi = $request->id_divisi ?: null;
                $data->updated_at = Date('Y-m-d H:i:s');
                $data->updated_by = $this->karyawan;
                $data->save();
                return response()->json(['message' => 'Jabatan updated successfully'], 200);
            }
        } else {
            $existingCabang = MasterJabatan::active()
                ->where('kode_jabatan', $request->kode_jabatan)
                ->first();
            if ($existingCabang) {
                return response()->json(['message' => 'Kode Jabatan already exists'], 401);
            }

            if ($request->id_divisi && !MasterDivisi::active()->where('id', $request->id_divisi)->exists()) {
                return response()->json(['message' => 'Divisi tidak valid atau tidak aktif'], 401);
            }

            $data = MasterJabatan::create([
                'kode_jabatan' => $request->kode_jabatan,
                'nama_jabatan' => $request->nama_jabatan,
                'id_divisi' => $request->id_divisi ?: null,
                'is_active' => 1,
                'created_at' => Date('Y-m-d H:i:s'),
                'created_by' => $this->karyawan
            ]);

            return response()->json(['message' => 'Jabatan created successfully'], 201);
        }
    }

    public function delete(Request $request){
        if($request->id !=''){
            $data = MasterJabatan::active()->where('id', $request->id)->first();
            if($data){
                $data->deleted_at = Date('Y:m:d H:i:s');
                $data->deleted_by = $this->karyawan;
                $data->is_active = false;
                $data->save();

                return response()->json(['message' => "Delete $data->nama_jabatan successfully"], 201);
            }

            return response()->json(['message' => 'Data Not Found.!'], 401);
        } else {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }
    }
}

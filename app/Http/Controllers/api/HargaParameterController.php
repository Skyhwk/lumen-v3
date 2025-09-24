<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\MasterKategori;
use App\Models\HargaParameter;
use App\Models\Parameter;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;

class HargaParameterController extends Controller
{
    public function index()
    {
        $data = HargaParameter::withHistory()
            ->where('master_harga_parameter.is_active', true);
        return Datatables::of($data)->make(true);

    }

    /* 2025-08-08 public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id != '') {
                $parameterOld = HargaParameter::find($request->id);

                if ($parameterOld) {

                    // Create New Param After Update
                    $newParam = new HargaParameter;
                    $newParam->id_kategori = $parameterOld->id_kategori;
                    $newParam->id_parameter = $parameterOld->id_parameter;
                    $newParam->nama_parameter = $parameterOld->nama_parameter;
                    $newParam->nama_kategori = $parameterOld->nama_kategori;
                    $newParam->harga = $parameterOld->harga;
                    $newParam->regen = $parameterOld->regen;
                    $newParam->volume = $parameterOld->volume;
                    $newParam->id_hist = $parameterOld->id;
                    $newParam->is_active = false;
                    $newParam->status = 1;
                    $newParam->created_by = $this->karyawan;
                    $newParam->created_at = DATE('Y-m-d H:i:s');
                    $newParam->save();

                    if ($request->harga != '')
                        $parameterOld->harga = str_replace('.', '', $request->harga);
                    if ($request->regen != '')
                        $parameterOld->regen = $request->regen;
                    if ($request->volume != '')
                        $parameterOld->volume = $request->volume;
                    $parameterOld->updated_by = $this->karyawan;
                    $parameterOld->updated_at = DATE('Y-m-d H:i:s');
                    $parameterOld->save();
                } else {
                    return response()->json(['message' => 'Parameter tidak ditemukan'], 404);
                }
            } else {
                if ($request->id_kategori == '') {
                    return response()->json(['message' => 'Silahkan pilih kategori'], 400);
                }

                $cek_kategori = MasterKategori::where('id', $request->id_kategori)->first();
                if ($cek_kategori) {
                    // cek parameter apakah id_parameter sudah ada di HargaParameter jika belum ada maka siap di create jika ada maka already exist

                    $cek_parameter = HargaParameter::where('id_parameter', $request->id_parameter)
                        ->where('is_active', true)
                        ->first();

                    if ($cek_parameter) {
                        return response()->json(['message' => 'Parameter sudah ada harganya.!'], 400);
                    }

                    $ambil_parameter = Parameter::where('id', $request->id_parameter)->first();

                    $parameter = new HargaParameter;

                    $parameter->nama_kategori = $cek_kategori->nama_kategori;
                    $parameter->id_kategori = $request->id_kategori;

                    $parameter->id_parameter = $request->id_parameter;
                    $parameter->nama_parameter = $ambil_parameter->nama_lab;

                    $parameter->harga = ($request->harga != '') ? str_replace('.', '', $request->harga) : '0.00';
                    if ($request->regen != '')
                        $parameter->regen = $request->regen;
                    if ($request->volume != '')
                        $parameter->volume = $request->volume;

                    $parameter->created_by = $this->karyawan;
                    $parameter->created_at = DATE('Y-m-d H:i:s');
                    $parameter->save();
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
    } */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {


            if ($request->id != '') {
                $parameterOld = HargaParameter::find($request->id);


                if ($parameterOld) {

                    // Create New Param After Update
                    $newParam = new HargaParameter;
                    $newParam->id_kategori = $parameterOld->id_kategori;
                    $newParam->id_parameter = $parameterOld->id_parameter;
                    $newParam->nama_parameter = $parameterOld->nama_parameter;
                    $newParam->nama_kategori = $parameterOld->nama_kategori;
                    $newParam->harga = $parameterOld->harga;
                    $newParam->regen = $parameterOld->regen;
                    $newParam->volume = $parameterOld->volume;
                    $newParam->id_hist = $parameterOld->id;
                    $newParam->is_active = false;
                    $newParam->status = 1;
                    $newParam->created_by = $this->karyawan;
                    $newParam->created_at = DATE('Y-m-d H:i:s');
                    $newParam->save();

                    if ($request->harga != '')
                        $parameterOld->harga = str_replace('.', '', $request->harga);
                    $parameterOld->updated_by = $this->karyawan;
                    $parameterOld->updated_at = DATE('Y-m-d H:i:s');
                    $parameterOld->save();
                } else {
                    return response()->json(['message' => 'Parameter tidak ditemukan'], 404);
                }
            } else {
                if ($request->id_kategori == '') {
                    return response()->json(['message' => 'Silahkan pilih kategori'], 400);
                }

                $cek_kategori = MasterKategori::where('id', $request->id_kategori)->first();
                if ($cek_kategori) {
                    // cek parameter apakah id_parameter sudah ada di HargaParameter jika belum ada maka siap di create jika ada maka already exist

                    $cek_parameter = HargaParameter::where('id_parameter', $request->id_parameter)
                        ->where('is_active', true)
                        ->first();

                    if ($cek_parameter) {
                        return response()->json(['message' => 'Parameter sudah ada harganya.!'], 400);
                    }

                    $ambil_parameter = Parameter::where('id', $request->id_parameter)->first();

                    $parameter = new HargaParameter;

                    $parameter->nama_kategori = $cek_kategori->nama_kategori;
                    $parameter->id_kategori = $request->id_kategori;

                    $parameter->id_parameter = $request->id_parameter;
                    $parameter->nama_parameter = $ambil_parameter->nama_lab;

                    $parameter->harga = ($request->harga != '') ? str_replace('.', '', $request->harga) : '0.00';
                    
                    
                    $parameter->created_by = $this->karyawan;
                    $parameter->created_at = DATE('Y-m-d H:i:s');
                    $parameter->save();
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

    public function delete(Request $request)
    {
        if ($request->id != '') {
            $data = HargaParameter::where('id', $request->id)->first();
            if ($data) {
                $data->deleted_at = Date('Y:m:d H:i:s');
                $data->deleted_by = $this->karyawan;
                $data->is_active = false;
                $data->save();

                return response()->json(['message' => 'Parameter successfully deleted'], 201);
            }

            return response()->json(['message' => 'Data Not Found.!'], 401);
        } else {
            return response()->json(['message' => 'Data Not Found.!'], 401);
        }
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', true)->select('id', 'nama_kategori')->get();
        return response()->json(['message' => 'Data hasbeen show', 'data' => $data], 201);
    }

    public function getParameterNonHarga(Request $request)
    {
        try {
            $data = DB::table('parameter as mp')
                ->leftJoin('master_harga_parameter as mhp', function ($join) {
                    $join->on('mp.id', '=', 'mhp.id_parameter');
                })
                ->whereNull('mhp.id')
                ->where('mp.is_active', true)
                ->select('mp.id', 'mp.nama_lab', 'mp.nama_regulasi', 'mp.nama_kategori');

            return Datatables::of($data)
                ->filterColumn('nama_lab', function ($query, $keyword) {
                    $query->where('mp.nama_lab', 'like', "%{$keyword}%");
                })
                ->filterColumn('nama_kategori', function ($query, $keyword) {
                    $query->where('mp.nama_kategori', 'like', "%{$keyword}%");
                })
                ->make(true);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getParameter(Request $request)
    {
        // $data = DB::table('parameter as mp')
        //     ->leftJoin('master_harga_parameter as mhp', function ($join) {
        //         $join->on('mp.id', '=', 'mhp.id_parameter')
        //         ->where('mhp.id_kategori', $request->id_kategori)
        //         ->where('mhp.is_active', true);
        //     })
        //     ->whereNull('mhp.id')
        //     ->where('mp.is_active', true)
        //     ->where('mp.id_kategori', $request->id_kategori)
        //     ->select('mp.id', 'mp.nama_lab', 'mp.nama_regulasi', 'mp.nama_kategori')
        //     ->get();

        $idKategori = $request->id_kategori;

        $data = DB::table('parameter as mp')
            ->leftJoin('master_harga_parameter as mhp', function ($join) use ($idKategori) {
                $join->on('mp.id', '=', 'mhp.id_parameter')
                    ->where('mhp.id_kategori', $idKategori)
                    ->where('mhp.is_active', true);
            })
            ->whereNull('mhp.id')
            ->where('mp.is_active', true)
            ->where('mp.id_kategori', $idKategori)
            ->select('mp.id', 'mp.nama_lab', 'mp.nama_regulasi', 'mp.nama_kategori')
            ->get();
            
        return response()->json([
            'data' => $data
        ], 201);
    }
}



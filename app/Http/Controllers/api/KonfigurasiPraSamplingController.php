<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\KonfigurasiPraSampling;
use App\Models\HargaParameter;
use App\Models\MasterKategori;
use App\Models\Parameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use Exception;

class KonfigurasiPraSamplingController extends Controller
{
    public function index()
    {
        $data = KonfigurasiPraSampling::with('kategori')->where('is_active', true)->latest();

        return Datatables::of($data)->make(true);
    }

    public function getAllKategori()
    {
        $data = MasterKategori::where('is_active', true)->where('is_active', true)->where('id', '!=', 1)->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Available kategori data retrieved successfully',
        ], 200);
    }

    public function getAllParameter()
    {
        $data = Parameter::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Available parameter data retrieved successfully',
        ], 200);
    }

    public function getParameterByKategoriId(Request $request)
    {
        $data = Parameter::where('id_kategori', $request->id)->where('is_active', true)->get();

        if ($data) {
            return response()->json([
                'data' => $data,
                'message' => 'Parameter data retrieved successfully',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Parameter data not found',
            ], 404);
        }
    }

    public function insert(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = new KonfigurasiPraSampling();
            $data->id_kategori = $request->id_kategori;
            $data->parameter = $request->parameter;
            $data->ketentuan = $request->ketentuan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->created_by = $this->karyawan;

            if ($data->save()) {
                DB::commit();
                return response()->json([
                    'message' => 'Konfigurasi Pra Sampling berhasil disimpan.!',
                    'status' => 'success',
                ], 201);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function update(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = KonfigurasiPraSampling::where('id', $request->id)->where('is_active', true);

            if ($data) {
                $data->update([
                    'id_kategori' => $request->id_kategori,
                    'parameter' => $request->parameter,
                    'ketentuan' => $request->ketentuan,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan,
                ]);

                DB::commit();
                return response()->json([
                    'message' => 'Konfigurasi Pra Sampling berhasil disimpan.!',
                    'status' => 'success',
                ], 202);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Konfigurasi Pra Sampling tidak ditemukan.!',
                    'status' => 'failed',
                ], 404);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = KonfigurasiPraSampling::where('id', $request->id)->where('is_active', true);

            if ($data) {
                $data->update([
                    'is_active' => false,
                    'deleted_at' => Carbon::now(),
                    'deleted_by' => $this->karyawan,
                ]);

                DB::commit();
                return response()->json([
                    'message' => 'Konfigurasi Pra Sampling berhasil disimpan.!',
                    'status' => 'success',
                ], 202);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Konfigurasi Pra Sampling tidak ditemukan.!',
                    'status' => 'failed',
                ], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }


    /* ardan 2025-08-08 */
    public function indexAir()
    {
       $data = HargaParameter::where('nama_kategori', 'Air')
        ->where('is_active', true)
        ->whereNotNull('regen')
        ->get();


        return Datatables::of($data)->make(true);
    }

    public function getParameterAir()
    {
        $kategoriId = MasterKategori::where('nama_kategori', 'Air')->value('id');
        if (!$kategoriId) {
            return response()->json([
                'message' => 'Kategori Air not found',
            ], 404);
        }
        $data = HargaParameter::where('id_kategori', $kategoriId)->where('is_active', true)->get();

        if ($data) {
            return response()->json([
                'data' => $data,
                'message' => 'Parameter data retrieved successfully',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Parameter data not found',
            ], 404);
        }
    }

    public function getParameterAirCreate()
    {

        $kategoriId = MasterKategori::where('nama_kategori', 'Air')->value('id');
        if (!$kategoriId) {
            return response()->json([
                'message' => 'Kategori Air not found',
            ], 404);
        }
       $data = HargaParameter::where('id_kategori', $kategoriId)
        ->where('is_active', true)
        ->whereNull('regen')
        ->get();

        if ($data) {
            return response()->json([
                'data' => $data,
                'message' => 'Parameter data retrieved successfully',
            ], 200);
        } else {
            return response()->json([
                'message' => 'Parameter data not found',
            ], 404);
        }
    }

    public function updateAir(Request $request)
    {
        DB::beginTransaction();
        try {

            $data = HargaParameter::where('id', $request->id)->first();


            if (!$data) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Data tidak ditemukan!',
                    'status' => 'error'
                ], 404);
            }

            $data->volume = $request->volume;
            $data->regen = $request->regen;
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Data berhasil diupdate!',
                'status' => 'success'
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
}

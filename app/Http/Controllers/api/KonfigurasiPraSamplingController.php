<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\KonfigurasiPraSampling;
use App\Models\KonfigurasiLabelParameter;
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

    public function indexBotol(){
        $botol = DB::table('master_botol')->get();

        return Datatables::of($botol)->make(true);
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

    public function getBotolOptions(){
        $botol = DB::table('master_botol')
            ->whereNotNull('nama_botol')
            ->where('nama_botol', '!=', '')
            ->select('id', 'nama_botol', 'volume')
            ->get();

        return response()->json($botol);
    }

    public function storeBotol(Request $request){
        // dd($request->all());
        DB::beginTransaction();
        try {
            DB::table('master_botol')->insert([
                'nama_botol' => $request->nama_botol,
                'volume' => $request->volume,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $this->karyawan
            ]);
            DB::commit();
            return response()->json(['message' => 'Data botol berhasil ditambahkan']);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return response()->json(['message' => 'Gagal menambahkan data botol', 'error' => $th->getMessage()], 500);
        }
    }

    public function updateBotol(Request $request){
        DB::beginTransaction();
        try {
            DB::table('master_botol')->where('id', $request->id)->update([
                'nama_botol' => $request->nama_botol,
                'volume' => $request->volume,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_by' => $this->karyawan
            ]);
            DB::commit();
            return response()->json(['message' => 'Data botol berhasil diupdate']);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengupdate data botol', 'error' => $th->getMessage()], 500);
        }
    }

    public function destroyBotol(Request $request){
        DB::beginTransaction();
        try {
            DB::table('master_botol')->where('id', $request->id)->delete();
            DB::commit();
            return response()->json(['message' => 'Data botol berhasil dihapus']);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghapus data botol', 'error' => $th->getMessage()], 500);
        }
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


    private function queryKonfigurasiLabelAir()
    {
        return KonfigurasiLabelParameter::query()
            ->join('parameter as p', 'konfigurasi_label_parameter.id_parameter', '=', 'p.id')
            ->where('konfigurasi_label_parameter.is_active', true)
            ->where('p.is_active', true)
            ->where('p.id_kategori', 1)
            ->select([
                'konfigurasi_label_parameter.id',
                'konfigurasi_label_parameter.id_parameter',
                'konfigurasi_label_parameter.nama_parameter',
                'konfigurasi_label_parameter.volume',
                'konfigurasi_label_parameter.regen',
                'konfigurasi_label_parameter.created_at',
                'konfigurasi_label_parameter.created_by',
                'konfigurasi_label_parameter.updated_at',
                'konfigurasi_label_parameter.updated_by',
                'p.nama_kategori as nama_kategori',
            ]);
    }

    public function indexAir()
    {
        $data = $this->queryKonfigurasiLabelAir()
            ->whereNotNull('konfigurasi_label_parameter.regen')
            ->where('konfigurasi_label_parameter.regen', '!=', '');

        return Datatables::of($data)->make(true);
    }

    public function getParameterAir()
    {
        $data = $this->queryKonfigurasiLabelAir()->get();

        return response()->json([
            'data' => $data,
            'message' => 'Parameter data retrieved successfully',
        ], 200);
    }

    public function getParameterAirCreate()
    {
        $configuredIds = KonfigurasiLabelParameter::query()
            ->where('is_active', true)
            ->pluck('id_parameter');

        $data = Parameter::query()
            ->where('id_kategori', 1)
            ->where('is_active', true)
            ->whereNotIn('id', $configuredIds)
            ->get()
            ->map(function ($parameter) {
                return [
                    'id' => null,
                    'id_parameter' => $parameter->id,
                    'nama_parameter' => $parameter->nama_regulasi,
                    'nama_kategori' => $parameter->nama_kategori,
                    'volume' => null,
                    'regen' => null,
                ];
            })
            ->values();

        return response()->json([
            'data' => $data,
            'message' => 'Parameter data retrieved successfully',
        ], 200);
    }

    public function updateAir(Request $request)
    {
        DB::beginTransaction();
        try {
            $parameter = Parameter::query()
                ->where('id', $request->id_parameter)
                ->where('id_kategori', 1)
                ->where('is_active', true)
                ->first();

            if (!$parameter) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Parameter air tidak ditemukan!',
                    'status' => 'error',
                ], 404);
            }

            $now = Carbon::now()->format('Y-m-d H:i:s');
            $payload = [
                'nama_parameter' => $parameter->nama_regulasi,
                'volume' => $request->volume,
                'regen' => $request->regen,
                'updated_at' => $now,
                'updated_by' => $this->karyawan,
            ];

            $config = KonfigurasiLabelParameter::query()
                ->where('id_parameter', $request->id_parameter)
                ->where('is_active', true)
                ->first();

            if ($config) {
                $config->update($payload);
            } else {
                KonfigurasiLabelParameter::create(array_merge($payload, [
                    'id_parameter' => $request->id_parameter,
                    'is_active' => true,
                    'created_at' => $now,
                    'created_by' => $this->karyawan,
                ]));
            }

            DB::commit();
            return response()->json([
                'message' => 'Data berhasil disimpan!',
                'status' => 'success',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'status' => 'error',
            ], 500);
        }
    }
}

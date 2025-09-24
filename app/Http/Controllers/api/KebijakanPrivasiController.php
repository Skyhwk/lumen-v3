<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Repository;
use App\Models\KebijakanPrivasi;

class KebijakanPrivasiController extends Controller
{
    public function index() {
        $data = KebijakanPrivasi::where('is_active', 1)->get();
        return DataTables::of($data)->make(true);
    }

    public function storeKebijakanPrivasi(Request $request) {
        DB::beginTransaction();
        try {
            $data = KebijakanPrivasi::where('id', $request->id)->first();
            if($data) {
                
                $data->update([
                    'content' => $request->content ?? $data->content,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan
                ]);
                
                DB::commit();
                return response()->json([
                    'message' => 'Kebijakan Privasi berhasil diupdate.',
                    'status' => '200'
                ], 200);
            }else {
                $data = KebijakanPrivasi::create([
                    'content' => $request->content,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]);
                
                if ($data) {
                    DB::commit();
                    return response()->json([
                        'message' => 'Kebijakan Privasi berhasil disimpan.',
                        'status' => '200'
                    ], 200);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Kebijakan Privasi gagal disimpan.',
                        'status' => '401'
                    ], 401);
                }
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'status' => '401'
            ], 401);
        }
    }

    public function deleteKebijakanPrivasi(Request $request) {
        DB::beginTransaction();
        try {
            $data = KebijakanPrivasi::find($request->id);
            if (!$data) {
                return response()->json([
                    'message' => 'Kebijakan Privasi tidak ditemukan.',
                    'status' => '404'
                ], 404);
            }

            $data->update([
                'is_active' => false,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'deleted_by' => $this->karyawan
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Kebijakan Privasi berhasil dinonaktifkan.',
                'status' => '200'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'status' => '500'
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Benefits;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BenefitsController extends Controller
{
    public function getBenefits()
    {
        $data = Benefits::where('is_active', true)->get();
        return DataTables::of($data)->make(true);
    }

    public function getBenefitslist()
    {
        $data = Benefits::where('is_active', true)->get();
        return response()->json($data, 200);
    }

    public function storeBenefits(Request $request)
    {
        DB::beginTransaction();
        try {
            

            $data = Benefits::where('id', $request->id)->first();
            if ($data) {
                $data->update([
                    'title' => $request->title ?? $data->title,
                    'description' => $request->description ?? $data->description,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan,
                ]);

                DB::commit();
                return response()->json([
                    'message' => 'Benefits berhasil diupdate.',
                    'status' => '200'
                ], 200);
            } else {
                $data = Benefits::create([
                    'title' => $request->title,
                    'description' => $request->description,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]);

                if ($data) {
                    DB::commit();
                    return response()->json([
                        'message' => 'Benefits berhasil disimpan.',
                        'status' => '200'
                    ], 200);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Benefits gagal disimpan.',
                        'status' => '401'
                    ], 401);
                }
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'status' => '500'
            ], 500);
        }
    }

    public function deleteBenefits(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Benefits::find($request->id);
            if (!$data) {
                return response()->json([
                    'message' => 'Benefits tidak ditemukan.',
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
                'message' => 'Benefits berhasil dinonaktifkan.',
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
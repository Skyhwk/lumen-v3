<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Sliders;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SlidersController extends Controller
{
    public function getSliders()
    {
        $data = Sliders::where('is_active', true)->get();
        $data->map(function ($item) {
            $item->image = env('APP_URL') . '/public/profile/sliders/image/' . $item->image;
            return $item;
        });
        return DataTables::of($data)->make(true);
    }

    public function getSliderslist()
    {
        $data = Sliders::where('is_active', true)->get();
        return response()->json($data, 200);
    }

    public function storeSliders(Request $request)
    {
        DB::beginTransaction();
        try {
            $year = Carbon::now()->format('y');
            $monthRoman = $this->romawi(Carbon::now()->format('n'));

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $originalExtension = $file->getClientOriginalExtension();
                $uniqueId = uniqid('IMG');
                $filename_img = "ISL-SLD-" . $year . "-" . $monthRoman . "-" . $uniqueId . "." . $originalExtension;
                $path = public_path('profile/sliders/image');
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }
                $file->move($path, $filename_img);
            }

            $data = Sliders::where('id', $request->id)->first();
            if ($data) {
                if($data->image && $request->hasFile('image')) {
                    $path = public_path('profile/sliders/image/' . $data->image);
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
                $data->update([
                    'title' => $request->title ?? $data->title,
                    'description' => $request->description ?? $data->description,
                    'image' => $filename_img ?? $data->image,
                    'color' => $request->color ?? $data->color,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan,
                ]);

                DB::commit();
                return response()->json([
                    'message' => 'Sliders berhasil diupdate.',
                    'status' => '200'
                ], 200);
            } else {
                $data = Sliders::create([
                    'title' => $request->title,
                    'description' => $request->description,
                    'image' => $filename_img,
                    'color' => $request->color,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]);

                if ($data) {
                    DB::commit();
                    return response()->json([
                        'message' => 'Sliders berhasil disimpan.',
                        'status' => '200'
                    ], 200);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Sliders gagal disimpan.',
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

    public function deleteSliders(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Sliders::find($request->id);
            if (!$data) {
                return response()->json([
                    'message' => 'Sliders tidak ditemukan.',
                    'status' => '404'
                ], 404);
            }

            if ($data->image) {
                $path = public_path('profile/sliders/image/' . $data->image);
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            $data->update([
                'is_active' => false,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'deleted_by' => $this->karyawan
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Sliders berhasil dinonaktifkan.',
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

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }
}
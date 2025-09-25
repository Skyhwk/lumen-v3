<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\CategoryService;
use App\Models\LingkupService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ServiceController extends Controller
{
    public function getService()
    {
        $data = Service::with(['category'])->where('is_active', true)->get();
        $data->map(function ($item) {
            $imagePath = public_path('profile/service/image/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = env('APP_URL') . '/public/profile/service/image/' . $item->image;
            }
            return $item;
        });
        return DataTables::of($data)->make(true);
    }

    public function getCategory()
    {
        $data = CategoryService::with(['lingkup'])->where('is_active', true)->get();
        return DataTables::of($data)->make(true);
    }

    public function getLingkup()
    {
        $data = LingkupService::with(['category'])->where('is_active', true)->get();
        $data->map(function ($item) {
            $item->image = env('APP_URL') . '/public/profile/service/image/' . $item->image;
            return $item;
        });
        return DataTables::of($data)->make(true);
    }

    public function getCategorylist()
    {
        $data = CategoryService::with(['lingkup'])->where('is_active', true)->get();
        return response()->json($data, 200);
    }

    public function getLingkuplist()
    {
        $data = LingkupService::where('is_active', true)->get();
        return response()->json($data, 200);
    }

    public function storeService(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $originalExtension = $file->getClientOriginalExtension();
                $year = Carbon::now()->format('y');
                $monthRoman = $this->romawi(Carbon::now()->format('n'));
                $uniqueId = uniqid('IMG');
                $filename_img = "ISL-SVC-" . $year . "-" . $monthRoman . "-" . $uniqueId . "." . $originalExtension;
                $path = public_path('profile/service/image');
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }
                $file->move($path, $filename_img);
            }

            $data = Service::where('id', $request->id)->first();
            if ($data) {
                if($data->image && $request->hasFile('image')) {
                    $imagePath = public_path('profile/service/image/' . $data->image);
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                $data->update([
                    'title' => $request->title ?? $data->title,
                    'description' => $request->description ?? $data->description,
                    'category_id' => $request->category_id ?? $data->category_id,
                    'image' => $filename_img ?? $data->image,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan,
                ]);

                DB::commit();
                return response()->json([
                    'message' => 'Service berhasil diupdate.',
                    'status' => '200'
                ], 200);
            } else {
                $data = Service::create([
                    'title' => $request->title,
                    'description' => $request->description,
                    'category_id' => $request->category_id,
                    'image' => $filename_img,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]);

                if ($data) {
                    DB::commit();
                    return response()->json([
                        'message' => 'Service berhasil disimpan.',
                        'status' => '200'
                    ], 200);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Service gagal disimpan.',
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

    public function deleteService(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = Service::find($request->id);
            if (!$data) {
                return response()->json([
                    'message' => 'Service tidak ditemukan.',
                    'status' => '404'
                ], 404);
            }

            if ($data->image) {
                $path = public_path('profile/service/image/' . $data->image);
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
                'message' => 'Service berhasil dinonaktifkan.',
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

    public function storeCategory(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = CategoryService::create([
                'title' => $request->title,
                'lingkup_service_id' => $request->lingkup_service_id,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $this->karyawan
            ]);

            if ($data) {
                DB::commit();
                return response()->json([
                    'message' => 'Category Service berhasil disimpan.',
                    'status' => '200'
                ], 200);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Category Service gagal disimpan.',
                    'status' => '401'
                ], 401);
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

    public function deleteCategory(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = CategoryService::find($request->id);
            if (!$data) {
                return response()->json([
                    'message' => 'Category Service tidak ditemukan.',
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
                'message' => 'Category Service berhasil dinonaktifkan.',
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

    public function storeLingkupService(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $originalExtension = $file->getClientOriginalExtension();
                $year = Carbon::now()->format('y');
                $monthRoman = $this->romawi(Carbon::now()->format('n'));
                $uniqueId = uniqid('IMG');
                $filename_img = "ISL-LKP-" . $year . "-" . $monthRoman . "-" . $uniqueId . "." . $originalExtension;
                $path = public_path('profile/service/image');
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }
                $file->move($path, $filename_img);
            }

            $data = LingkupService::where('id', $request->id)->first();
            if ($data) {
                if($data->image && $request->hasFile('image')) {
                    $path = public_path('profile/service/image/' . $data->image);
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
                $data->update([
                    'title' => $request->title ?? $data->title,
                    'description' => $request->description ?? $data->description,
                    'image' => $filename_img ?? $data->image,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan,
                ]);

                DB::commit();
                return response()->json([
                    'message' => 'Lingkup Service berhasil diupdate.',
                    'status' => '200'
                ], 200);
            } else {
                $data = LingkupService::create([
                    'title' => $request->title,
                    'description' => $request->description,
                    'image' => $filename_img,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]);

                if ($data) {
                    DB::commit();
                    return response()->json([
                        'message' => 'Lingkup Service berhasil disimpan.',
                        'status' => '200'
                    ], 200);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Lingkup Service gagal disimpan.',
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

    public function deleteLingkupService(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = LingkupService::find($request->id);
            if (!$data) {
                return response()->json([
                    'message' => 'Lingkup Service tidak ditemukan.',
                    'status' => '404'
                ], 404);
            }

            if($data->image) {
                $path = public_path('profile/service/image/' . $data->image);
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
                'message' => 'Lingkup Service berhasil dinonaktifkan.',
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

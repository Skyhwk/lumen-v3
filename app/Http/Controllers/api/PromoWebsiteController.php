<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Repository;
use App\Models\PromoWebsite;

class PromoWebsiteController extends Controller
{
    public function index() {
        $data = PromoWebsite::where('is_active', 1)->get();
        $data->map(function ($item) {
            $imagePath = public_path('profile/promo/' . $item->image);
            if (file_exists($imagePath)) {
                $item->image = env('APP_URL') . '/public/profile/promo/' . $item->image;
            }
            return $item;
        });
        return DataTables::of($data)->make(true);
    }

    public function storePromo(Request $request) {
        DB::beginTransaction();
        try {
            $year = Carbon::now()->format('y');
            $monthRoman = $this->romawi(Carbon::now()->format('n'));
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $originalExtension = $file->getClientOriginalExtension();
                $uniqueId = uniqid('IMG');
                $filename_img = "ISL-PROMO-" . $year . "-" . $monthRoman . "-" . $uniqueId . "." . $originalExtension;
                $path = public_path('profile/promo');
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }
                $file->move($path, $filename_img);
            }


            $data = PromoWebsite::where('id', $request->id)->first();
            if($data) {
                if ($request->image) {
                    $oldImagePath = public_path('profile/promo/image/' . $data->image);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                $data->update([
                    'image' => $filename_img ?? $data->image,
                    'description' => $request->description ?? $data->description,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan
                ]);
                
                DB::commit();
                return response()->json([
                    'message' => 'Promo berhasil diupdate.',
                    'status' => '200'
                ], 200);
            }else {
                $data = PromoWebsite::create([
                    'description' => $request->description,
                    'image' => $filename_img,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]);
                
                if ($data) {
                    DB::commit();
                    return response()->json([
                        'message' => 'Promo berhasil disimpan.',
                        'status' => '200'
                    ], 200);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Promo gagal disimpan.',
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

    public function deletePromo(Request $request) {
        DB::beginTransaction();
        try {
            $data = PromoWebsite::find($request->id);
            if (!$data) {
                return response()->json([
                    'message' => 'Promo tidak ditemukan.',
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
                'message' => 'Promo berhasil dinonaktifkan.',
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
    // public function getNews()
    // {
    //     $data = News::with(['category'])->where('is_active', true)->get();
    //     $data->map(function ($item) {
    //         $item->content = Repository::dir('news_content')->key(explode('.',$item->content)[0])->get();
    //         $imagePath = public_path('profile/news/' . $item->image);
    //         if (file_exists($imagePath)) {
    //             $item->image = env('APP_URL') . '/public/profile/news/' . $item->image;
    //         }
    //         return $item;
    //     });
    //     return DataTables::of($data)->make(true);
    // }

    // public function getNewsDetail(Request $request)
    // {
    //     $data = News::with(['category'])->find($request->id);
    //     $data->content = Repository::dir('news_content')->key(explode('.',$data->content)[0])->get();
    //     $imagePath = public_path('profile/news/' . $data->image);
    //     if (file_exists($imagePath)) {
    //         $data->image = env('APP_URL') . '/public/profile/news/' . $data->image;
    //     }
    //     return response()->json($data,200);
    // }

    // public function getNewsApi()
    // {
    //     $data = News::with(['category'])->where('is_active', true)->get();
    //     $data->map(function ($item) {
    //         $item->content = Repository::dir('news_content')->key(explode('.',$item->content)[0])->get();
    //         $imagePath = public_path('profile/news/' . $item->image);
    //         if (file_exists($imagePath)) {
    //             $item->image = env('APP_URL') . '/public/profile/news/' . $item->image;
    //         }
    //         return $item;
    //     });
    //     return response()->json($data,200);
    // }

    // public function storeNews(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $year = Carbon::now()->format('y');
    //         $monthRoman = $this->romawi(Carbon::now()->format('n'));
    //         if ($request->hasFile('image')) {
    //             $file = $request->file('image');
    //             $originalExtension = $file->getClientOriginalExtension();
    //             $uniqueId = uniqid('IMG');
    //             $filename_img = "ISL-NEWS-" . $year . "-" . $monthRoman . "-" . $uniqueId . "." . $originalExtension;
    //             $path = public_path('profile/news');
    //             if (!is_dir($path)) {
    //                 mkdir($path, 0777, true);
    //             }
    //             $file->move($path, $filename_img);
    //         }
            
    //         $uniqueText = uniqid('TXT');
    //         $filename_txt = "ISL-NEWS-" . $year . "-" . $monthRoman . "-" . $uniqueText;
    //         $content = $request->content;
    //         Repository::dir('news_content')->key($filename_txt)->save($content);
            
    //         $data = News::where('id', $request->id)->first();
            
    //         if ($data) {
    //             if ($request->content) {
    //                 $oldContentPath = public_path('profile/news/content/' . $data->content);
    //                 if (file_exists($oldContentPath)) {
    //                     unlink($oldContentPath);
    //                 }
    //             }
    
    //             if ($request->image) {
    //                 $oldImagePath = public_path('profile/news/image/' . $data->image);
    //                 if (file_exists($oldImagePath)) {
    //                     unlink($oldImagePath);
    //                 }
    //             }
    //             $data->update([
    //                 'title' => $request->title ?? $data->title,
    //                 'slug' => $request->slug ?? $data->slug,
    //                 'description' => $request->description ?? $data->description,
    //                 'category_news_id' => $request->category_news_id ?? $data->category_news_id,
    //                 'image' => $filename_img ?? $data->image,
    //                 'content' => $filename_txt . ".txt" ?? $data->content,
    //                 'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
    //                 'updated_by' => $this->karyawan,
    //             ]);
                
    //             DB::commit();
    //             return response()->json([
    //                 'message' => 'News berhasil diupdate.',
    //                 'status' => '200'
    //             ], 200);
    //         } else {
    //             $data = News::create([
    //                 'title' => $request->title,
    //                 'slug' => $request->slug,
    //                 'description' => $request->description,
    //                 'category_news_id' => $request->category_news_id,
    //                 'image' => $filename_img,
    //                 'content' => $filename_txt,
    //                 'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
    //                 'created_by' => $this->karyawan
    //             ]);
                
    //             if ($data) {
    //                 DB::commit();
    //                 return response()->json([
    //                     'message' => 'News berhasil disimpan.',
    //                     'status' => '200'
    //                 ], 200);
    //             } else {
    //                 DB::rollBack();
    //                 return response()->json([
    //                     'message' => 'News gagal disimpan.',
    //                     'status' => '401'
    //                 ], 401);
    //             }
    //         }
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => $e->getMessage(),
    //             'line' => $e->getLine(),
    //             'status' => '500'
    //         ], 500);
    //     }
    // }

    // public function deleteNews(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $data = News::find($request->id);
    //         if (!$data) {
    //             return response()->json([
    //                 'message' => 'News tidak ditemukan.',
    //                 'status' => '404'
    //             ], 404);
    //         }

    //         $data->update([
    //             'is_active' => false,
    //             'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
    //             'deleted_by' => $this->karyawan
    //         ]);

    //         DB::commit();
    //         return response()->json([
    //             'message' => 'News berhasil dinonaktifkan.',
    //             'status' => '200'
    //         ], 200);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => $e->getMessage(),
    //             'line' => $e->getLine(),
    //             'status' => '500'
    //         ], 500);
    //     }
    // }

    // public function storeCategoryNews(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $category = CategoryNews::create([
    //             'title' => $request->title,
    //             'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
    //             'created_by' => $this->karyawan
    //         ]);

    //         DB::commit();
    //         return response()->json([
    //             'message' => 'Category berhasil disimpan.',
    //             'status' => '200'
    //         ], 200);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => $e->getMessage(),
    //             'status' => '500'
    //         ], 500);
    //     }
    // }

    // public function deleteCategoryNews(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $category = CategoryNews::find($request->id);
    //         if (!$category) {
    //             return response()->json([
    //                 'message' => 'Category tidak ditemukan.',
    //                 'status' => '404'
    //             ], 404);
    //         }

    //         $category->update([
    //             'is_active' => 0,
    //             'deleted_at' => Carbon::now(),
    //             'deleted_by' => $this->karyawan
    //         ]);

    //         DB::commit();
    //         return response()->json([
    //             'message' => 'Category berhasil dinonaktifkan.',
    //             'status' => '200'
    //         ], 200);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => $e->getMessage(),
    //             'status' => '500'
    //         ], 500);
    //     }
    // }

    public function romawi($bulan = 0)
    {
        $satuan = (int) $bulan - 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $romawi[$satuan];
    }
}
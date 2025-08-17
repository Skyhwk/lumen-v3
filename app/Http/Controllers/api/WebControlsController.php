<?php

namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use App\Models\{WebControl,CompanyPageControl};
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use DB;

class WebControlsController extends Controller
{
    // Start Main Control Function
    public function indexMain()
    {
        $webControls = WebControl::get();
        $webControls->map(function ($item) {
            $item->stats = json_decode($item->stats);
            $item->logo = env('APP_URL') . '/public/profile/control/' . $item->logo;
            $item->favicon = env('APP_URL') . '/public/profile/control/' . $item->favicon;
            $item->meta = json_decode($item->meta);
            return $item;
        });
        return response()->json($webControls, 200);
    }

    public function indexMainApi()
    {
        $webControls = WebControl::get();
        $webControls->map(function ($item) {
            $item->stats = json_decode($item->stats);
            $item->logo = env('APP_URL') . '/public/profile/control/' . $item->logo;
            $item->favicon = env('APP_URL') . '/public/profile/control/' . $item->favicon;
            $item->meta = json_decode($item->meta);
            return $item;
        });
        return response()->json($webControls, 200);
    }

    public function storeMain(Request $request)
    {
        DB::beginTransaction();
        try {
            $stats = array_map(function ($label, $value) {
                return (object) [
                    'label' => $label,
                    'value' => $value,
                ];
            }, $request->label, $request->value);
            if($request->id == null) {
                $webControl = new WebControl();
            } else {
                $webControl = WebControl::find($request->id);
            }
            // $webControl = WebControl::find($request->id);
            // dd($webControl);
            $webControl->title = $request->title;
            $webControl->about = $request->about;
            $webControl->facebook = $request->facebook;
            $webControl->instagram = $request->instagram;
            $webControl->youtube = $request->youtube;
            $webControl->tiktok = $request->tiktok;
            $webControl->twitter = $request->twitter;
            $webControl->linkedin = $request->linkedin;
            $webControl->email = $request->email;
            $webControl->phone = $request->phone;
            $webControl->left_footer = $request->left_footer;
            $webControl->link_customer = $request->link_customer;
            $webControl->link_recruitment = $request->link_recruitment;
            $webControl->link_gmaps = $request->link_gmaps;
            $webControl->theme_slider = $request->theme_slider;
            $webControl->float_text = $request->float_text;
            $webControl->stats = json_encode($stats);

            if($request->hasFile('logo')) {
                if ($webControl->logo && file_exists(public_path('profile/control/' . $webControl->logo))) {
                    unlink(public_path('profile/control/' . $webControl->logo));
                }
                $file = $request->file('logo');
                $filename = "logo" . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('profile/control'), $filename);
                $webControl->logo = $filename;
            }

            // Primary Image For About Us
            if($request->hasFile('primary_image')) {
                if ($webControl->primary_about && file_exists(public_path('profile/control/' . $webControl->primary_about))) {
                    unlink(public_path('profile/control/' . $webControl->primary_about));
                }
                $file = $request->file('primary_image');
                $filename = "primary_image" . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('profile/control'), $filename);
                $webControl->primary_about = $filename;
            }

            // Secondary Image For About Us
            if($request->hasFile('secondary_image')) {
                if ($webControl->secondary_about && file_exists(public_path('profile/control/' . $webControl->secondary_about))) {
                    unlink(public_path('profile/control/' . $webControl->secondary_about));
                }
                $file = $request->file('secondary_image');
                $filename = "secondary_image" . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('profile/control'), $filename);
                $webControl->secondary_about = $filename;
            }


            if($request->hasFile('favicon')) {
                if ($webControl->favicon && file_exists(public_path('profile/control/' . $webControl->favicon))) {
                    unlink(public_path('profile/control/' . $webControl->favicon));
                }
                $file = $request->file('favicon');
                $filename = "favicon" . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('profile/control'), $filename);
                $webControl->favicon = $filename;
            }
            $webControl->save();
            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    // End Main Control Function

    // Start Page Control Function
    public function indexPage(Request $request)
    {
        $data = CompanyPageControl::get();
        $data->map(function ($item) {
            $item->image = env('APP_URL') . '/public/profile/page-control/' . $item->image;
            return $item;
        });
        return DataTables::of($data)->make(true);
    }

    public function indexPageApi(Request $request)
    {
        $data = CompanyPageControl::get();
        $data->map(function ($item) {
            $item->image = env('APP_URL') . '/public/profile/page-control/' . $item->image;
            return $item;
        });
        return response()->json($data,200);
    }

    public function storePage(Request $request)
    {
        DB::beginTransaction();
        try {
            // Cek apakah ID ada, jika tidak buat instance baru
            if (empty($request->id)) {
                $data = new CompanyPageControl;
            } else {
                $data = CompanyPageControl::find($request->id);
                if (!$data) {
                    return response()->json(['message' => 'Data tidak ditemukan'], 404);
                }
            }

            $destinationPath = public_path('profile/page-control');
            // Proses upload gambar jika ada
            if ($request->hasFile('image')) {
                // Hapus gambar lama jika ada
                if (!empty($data->image) && file_exists(public_path('profile/page-control/' . $data->image))) {
                    unlink(public_path('profile/page-control/' . $data->image));
                }

                // Simpan gambar baru
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }
                $file = $request->file('image');
                $filename = "BG_" . preg_replace('/\s+/', '_', $request->name) . '.' . $file->getClientOriginalExtension();
                $file->move($destinationPath, $filename);
                $data->image = $filename;
            }

            // Simpan nama halaman
            $data->name = $request->name;

            // Jika ID kosong, berarti insert data baru
            if (empty($request->id)) {
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s'); // Menggunakan helper Laravel
            } else {
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            }

            // Simpan data ke database
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            // // Hapus file baru jika gagal menyimpan
            if (isset($filename)) {
                $path = public_path($destinationPath . "/{$filename}");
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function deletePage(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = CompanyPageControl::find($request->id);
            if ($data->image && file_exists(public_path('profile/page-control/' . $data->image))) {
                unlink(public_path('profile/page-control/' . $data->image));
            }
            $data->delete();
            DB::commit();
            return response()->json(['message' => 'Data berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function storeAbout(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = WebControl::find(1);
            $data->about_us = $request->about_us;
            $data->save();
            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
    public function storeMeta(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = WebControl::find(1);
            $data->meta = $request->meta;
            $data->save();
            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}

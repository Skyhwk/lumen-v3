<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Career;
use App\Models\MasterCabang;
use Carbon\Carbon;
use DB;
use Yajra\Datatables\Datatables;
use Repository;

class CareersController extends Controller
{
    public function index(Request $request) {
        $careers = DB::table('company_profile.careers')
            ->leftJoin('intilab_produksi.master_cabang as cabang', 'careers.id_cabang', '=', 'cabang.id')
            ->where('careers.is_active', true)
            ->orderBy('careers.created_at', 'desc')
            ->select('careers.*', 'cabang.alamat_cabang', 'cabang.nama_cabang','cabang.id as cabang_id') // Menggunakan alias cabang
            ->get();

        $careers->map(function($item) {
            $item->image = env('APP_URL') . '/public/profile/career/image/' . $item->image;
            $item->requirement = Repository::dir('career-requirements')->key(strtoupper(str_replace(' ', '_', $item->title . '_' . $item->type)))->get();
            return $item;
        });
        return Datatables::of($careers)->make(true);
    }

    public function detail(Request $request) {
        $data = Career::find($request->id);
        $cabang = MasterCabang::find($data->id_cabang);
        $data->image = env('APP_URL') . '/public/profile/career/image/' . $data->image;
        $data->requirement = Repository::dir('career-requirements')->key(strtoupper(str_replace(' ', '_', $data->title . '_' . $data->type)))->get();
        $data->cabang = $cabang->nama_cabang;
        $data->alamat_cabang = $cabang->alamat_cabang;
        $data->type = ucfirst($data->type);
        return response()->json($data,200);
    }

    public function indexApi(Request $request) {
        $careers = DB::table('company_profile.careers')
            ->leftJoin('intilab_produksi.master_cabang as cabang', 'careers.id_cabang', '=', 'cabang.id')
            ->where('careers.is_active', true)
            ->orderBy('careers.created_at', 'desc')
            ->select('careers.*', 'cabang.alamat_cabang', 'cabang.nama_cabang','cabang.id as cabang_id') // Menggunakan alias cabang
            ->get();

        $careers->map(function($item) {
            $item->image = env('APP_URL') . '/public/profile/career/image/' . $item->image;
            $item->requirement = Repository::dir('career-requirements')->key(strtoupper(str_replace(' ', '_', $item->title . '_' . $item->type)))->get();
            return $item;
        });

        return response()->json($careers,200);
    }

    public function getTypeList(){
        $data = [
            [
                'id' => 'fulltime',
                'name' => 'Full Time'
            ],
            [
                'id' => 'training',
                'name' => 'Training'
            ],
            [
                'id' => 'internship',
                'name' => 'Internship'
            ],
            [
                'id' => 'contract',
                'name' => 'Contract'
            ]
        ];

        return response()->json($data,200);
    }

    public function getCabang()
    {
        $cabang = MasterCabang::where('is_active', true)->select('id', 'nama_cabang')->get();
        return response()->json($cabang,200);
    }

    public function store(Request $request) {
        DB::beginTransaction();
        try {
            $path = public_path('profile/career/image');

            // Pastikan direktori ada
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }

            // Jika ini adalah pembuatan data baru
            if($request->id == null || $request->id == '') {
                $filename_img = null;

                // Proses upload file jika ada
                if($request->hasFile('image') && $request->file('image')->isValid()) {
                    $file = $request->file('image');
                    $originalExtension = $file->getClientOriginalExtension();
                    $uniqueId = uniqid('IMG');
                    $filename_img = "ISL-CAREER-" . $uniqueId . "." . $originalExtension;
                    $file->move($path, $filename_img);
                }

                // Simpan file requirement
                $content = $request->requirement;
                $name = strtoupper(str_replace(' ', '_', $request->title . '_' . $request->type));
                Repository::dir('career-requirements')->key($name)->save($content);

                // Buat data baru
                $data = new Career;
                $data->title = $request->title;
                $data->type = $request->type;
                $data->id_cabang = $request->id_cabang;
                $data->requirement = $name;
                $data->image = $filename_img;
                $data->salary_start = is_numeric($request->salary_start) ? $request->salary_start : 0;
                $data->salary_end = is_numeric($request->salary_end) ? $request->salary_end : 0;
                $data->end_date = ($request->end_date !== null && $request->end_date !== "") ? $request->end_date : null;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->created_by = $this->karyawan;
                $data->save();
            } else {
                // Update data yang sudah ada
                $data = Career::findOrFail($request->id);
                $oldImage = $data->image; // Simpan nama file lama

                // Simpan file requirement
                $content = $request->requirement;
                $name = strtoupper(str_replace(' ', '_', $request->title . '_' . $request->type));
                Repository::dir('career-requirements')->key($name)->save($content);

                $data->title = $request->title;
                $data->type = $request->type;
                $data->id_cabang = $request->id_cabang;
                $data->requirement = $name;
                $data->salary_start = is_numeric($request->salary_start) ? $request->salary_start : 0;
                $data->salary_end = is_numeric($request->salary_end) ? $request->salary_end : 0;
                $data->end_date = ($request->end_date !== null && $request->end_date !== "") ? $request->end_date : null;

                // Proses upload file baru jika ada
                if($request->hasFile('image') && $request->file('image')->isValid()) {
                    $file = $request->file('image');
                    $originalExtension = $file->getClientOriginalExtension();
                    $uniqueId = uniqid('IMG');
                    $filename_img = "ISL-CAREER-" . $uniqueId . "." . $originalExtension;
                    $file->move($path, $filename_img);

                    // Hapus file lama jika ada
                    if($oldImage && file_exists($path . '/' . $oldImage)) {
                        unlink($path . '/' . $oldImage);
                    }

                    $data->image = $filename_img;
                }

                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->updated_by = $this->karyawan;
                $data->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Pekerjaan berhasil disimpan.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            // Jika terjadi error saat upload, pastikan tidak ada file yang tertinggal
            if(isset($filename_img) && file_exists($path . '/' . $filename_img)) {
                unlink($path . '/' . $filename_img);
            }

            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try {
            // Temukan data career berdasarkan ID
            $career = Career::findOrFail($id);

            // Hapus file gambar jika ada
            if ($career->image) {
                $imagePath = public_path('profile/career/image/' . $career->image);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            // Hapus data career dari database
            $career->is_active = 0; // Ubah status menjadi non-aktif;
            $career->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $career->deleted_by = $this->karyawan;
            $career->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pekerjaan berhasil dihapus.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}

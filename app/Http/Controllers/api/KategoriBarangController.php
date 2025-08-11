<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KategoriBarang;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class KategoriBarangController extends Controller
{
    public function index(Request $request){
        $data = KategoriBarang::where('id_cabang', $request->id_cabang)->where('is_active', $request->is_active);
        return DataTables::of($data)->make(true);
    }

    public function store(Request $request){
        try {
            $validator = Validator::make($request->all(), [
                'nama_kategori' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first()
                ], 422);
            }

            if ($request->id) {
                $kategori = KategoriBarang::find($request->id);
                if (!$kategori) {
                    return response()->json([
                        'message' => 'Data kategori tidak ditemukan'
                    ], 404);
                }

                $kategori->update([
                    'kategori' => $request->nama_kategori,
                    'updated_by' => $this->karyawan
                ]);

                return response()->json([
                    'message' => 'Data berhasil diupdate'
                ], 200);

            } else {
                KategoriBarang::create([
                    'kategori' => $request->nama_kategori,
                    'id_cabang' => $request->id_cabang,
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s')
                ]);

                return response()->json([
                    'message' => 'Data berhasil disimpan'
                ], 200);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteorrestore(Request $request){
        $kategori = KategoriBarang::find($request->id);
        $kategori->update   ([
            'is_active' => $request->is_active
        ]);
        return response()->json([
            'message' => 'Data berhasil dihapus'
        ], 200);
    }

    public function destroy(Request $request){
        $kategori = KategoriBarang::find($request->id);
        $kategori->delete();
        return response()->json([
            'message' => 'Data berhasil dihapus permanen'
        ], 200);
    }
}
<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\BarangMasuk;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class BarangController extends Controller
{
    public function index(Request $request){
        $barang = Barang::with('kategori')->where('id_cabang', $request->id_cabang)->where('is_active', true)->get();
        return DataTables::of($barang)->make(true);
    }

    public function getKategoriBarang(Request $request){
        $kategori = KategoriBarang::where('id_cabang', $request->id_cabang)->where('is_active', true)->get();
        return response()->json(['data' => $kategori], 200);
    }

    public function store(Request $request){
        try {
            if($request->has('id') && $request->id != ''){
                $barang = Barang::find($request->id);
                if($request->has('min')){
                    // Setting minimum stok
                    $barang->min = $request->min;
                    $barang->save();
                    
                    return response()->json([
                        'status' => true,
                        'message' => 'Berhasil mengatur minimum stok barang'
                    ], 200);
                } else {
                    // Update data barang
                    $cek = Barang::where('id', '!=', $request->id)->where('kode_barang', $request->kode_barang)->first();
                    if($cek){
                        return response()->json([
                            'message' => 'Kode barang sudah ada'
                        ], 400);
                    }

                    $barang->kode_barang = $request->kode_barang;
                    $barang->nama_barang = $request->nama_barang;
                    $barang->id_kategori = $request->kategori;
                    $barang->merk = $request->merk;
                    $barang->ukuran = $request->ukuran;
                    $barang->satuan = $request->satuan;
                    $barang->save();

                    return response()->json([
                        'status' => true,
                        'message' => 'Berhasil mengubah data barang'
                    ], 200);
                }
            } else {
                // dd('masuk');
                // Create data barang baru
                $cek = Barang::where('kode_barang', $request->kode_barang)->first();
                if($cek){
                    return response()->json([
                        'message' => 'Kode barang sudah ada'
                    ], 400);
                }
                $barang = new Barang();
                $barang->kode_barang = $request->kode_barang;
                $barang->nama_barang = $request->nama_barang;
                $barang->id_kategori = $request->kategori;
                $barang->id_cabang = $request->id_cabang;
                $barang->merk = $request->merk;
                $barang->ukuran = $request->ukuran;
                $barang->satuan = $request->satuan;
                $barang->min = $request->min;
                $barang->awal = $request->awal;
                $barang->akhir = $request->akhir;
                $barang->is_active = true;
                $barang->save();

                return response()->json([
                    'status' => true,
                    'message' => 'Berhasil menambahkan data barang'
                ], 200);
            }
        } catch(\Exception $e){
            // dd($e);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request){
        $barang = Barang::find($request->id);
        $barang->is_active = false;
        $barang->save();
        return response()->json([
            'message' => 'Berhasil menghapus data barang'
        ], 200);
    }
}
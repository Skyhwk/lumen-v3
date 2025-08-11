<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RecordPermintaanBarang;
use App\Models\MasterKaryawan;
use App\Models\KategoriBarang;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class PerlengkapanKantorController extends Controller
{
    public function index(Request $request){
        $data = RecordPermintaanBarang::where('id_cabang', $request->idcabang)
        ->where('flag', 0)
        ->where('status', '!=', 'waiting')
        ->where('id_cabang', $request->idcabang)
        ->whereYear('timestamp', $request->tahun);
        
        if($request->id_kategori != 0){
            $data = $data->where('id_kategori', $request->id_kategori);
        }
        if($request->iduser!=null){
            $data = $data->where('id_user', $request->iduser);
        }

        $data = $data->select('kode_barang', 'nama_barang'
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '01' THEN jumlah ELSE null END)) as januari")
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '02' THEN jumlah ELSE null END)) as februari")
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '03' THEN jumlah ELSE null END)) as maret")
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '04' THEN jumlah ELSE null END)) as april")
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '05' THEN jumlah ELSE null END)) as mei")
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '06' THEN jumlah ELSE null END)) as juni")
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '07' THEN jumlah ELSE null END)) as juli")
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '08' THEN jumlah ELSE null END)) as agustus")
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '09' THEN jumlah ELSE null END)) as september")
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '10' THEN jumlah ELSE null END)) as oktober")
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '11' THEN jumlah ELSE null END)) as november")
            , DB::raw("(SUM(CASE WHEN month(timestamp) = '12' THEN jumlah ELSE null END)) as desember")
        )->groupBy('kode_barang', 'nama_barang');

        if($request->show_type == 'karyawan' && $request->iduser != null){
            return DataTables::of($data)->make(true);
        } else if($request->show_type == 'karyawan' && $request->iduser == null){
            return DataTables::of([])->make(true);
        } else if($request->show_type == 'barang'){
            return DataTables::of($data)->make(true);
        } else {
            return DataTables::of([])->make(true);
        }
        
    }
    
    public function getKategoriBarang(Request $request){
        $kategori = KategoriBarang::where('id_cabang', $request->id_cabang)->where('is_active', true)->get();
        return response()->json(['data' => $kategori], 200);
    }

    public function getKaryawan(Request $request){
        $data = MasterKaryawan::where('id_cabang', $request->idcabang)->where('is_active', true)->select('id', 'nama_lengkap')->orderBy('nama_lengkap', 'ASC');
        return $request->show_type == 'karyawan' 
            ? DataTables::of($data)->make(true) 
            : DataTables::of([])->make(true);

    }
}

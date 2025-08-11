<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;



class MonitorAbsenController extends Controller{
    // Tested - Clear
    public function indexMonitor(Request $request){
        $data = DB::table('absensi')->leftJoin('master_karyawan', 'absensi.karyawan_id', '=', 'master_karyawan.id')
        ->leftJoin('mesin_absen', 'absensi.kode_mesin', '=', 'mesin_absen.kode_mesin')
        ->leftJoin('master_cabang', 'mesin_absen.id_cabang', '=', 'master_cabang.id')
        ->leftJoin('master_divisi', 'master_karyawan.id_department', '=', 'master_divisi.id')
        ->select('master_karyawan.nama_lengkap', 'master_karyawan.nik_karyawan', 'master_divisi.nama_divisi',  'absensi.kode_kartu', 'master_cabang.nama_cabang', 'absensi.tanggal', 'absensi.hari', 'absensi.jam', 'absensi.status')
        ->whereIn('mesin_absen.id_cabang', $this->privilageCabang)
        ->orderBy('absensi.id', 'DESC');
        if($this->department == '10'){
            $data = $data->limit('50');
        } else {
            $data = $data->limit('6');
        }
        $data = $data->get();

        return datatables()->of($data)->make(true);
    }
}

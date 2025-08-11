<?php

namespace App\Http\Controllers\api;

use App\Models\RecordPermintaanBarang;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Barang;
use App\Models\MasterKaryawan;
use App\Models\KategoriBarang;
use App\Models\BarangMasuk;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Http;

class PengambilanBarangController extends Controller
{
    public function index(Request $request)
    {
        $data = RecordPermintaanBarang::with('barang', 'karyawan')
            ->whereYear('timestamp', $request->year)
            ->where('submited', 0)
            ->where('flag', 0)
            ->orderBy('id', 'desc')
            ->orderBy('status', 'asc');

        return Datatables::of($data)
            ->filterColumn('request_id', function ($query, $keyword) {
                $query->where('request_id', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('timestamp', function ($query, $keyword) {
                $query->where('timestamp', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_karyawan', function ($query, $keyword) {
                $query->where('nama_karyawan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('divisi', function ($query, $keyword) {
                $query->where('divisi', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('kode_barang', function ($query, $keyword) {
                $query->where('kode_barang', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_barang', function ($query, $keyword) {
                $query->where('nama_barang', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('barang.merk', function ($query, $keyword) {
                $query->whereHas('barang', function ($q) use ($keyword) {
                    $q->where('merk', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('barang.ukuran', function ($query, $keyword) {
                $query->whereHas('barang', function ($q) use ($keyword) {
                    $q->where('ukuran', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('barang.satuan', function ($query, $keyword) {
                $query->whereHas('barang', function ($q) use ($keyword) {
                    $q->where('satuan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('jumlah', function ($query, $keyword) {
                $query->where('jumlah', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('status', function ($query, $keyword) {
                $query->where('status', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('keterangan', function ($query, $keyword) {
                $query->where('keterangan', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function ProsesPermintaan(Request $request)
    {
        $data = RecordPermintaanBarang::where('id', $request->id)->first();
        $data->status = 'process';
        $data->process_time = date('Y-m-d H:i:s');
        $data->save();

        $barang = Barang::where('id', $data->id_barang)->first();
        $barang->akhir = $barang->akhir - $data->jumlah;
        $barang->save();

        $response = Http::post('https://apps.intilab.com/v4/public/api/notif', [
            'user_id' => $data->id_user,
            'title' => 'Pengambilan Barang di prosess',
            'body' => 'Silahkan Ambil di purchasing 30 menit yg akan datang, barang anda sedang dipersiapkan'
        ]);

        return response()->json([
            'message' => 'Data hasbeen Proses.!'
        ], 200);
    }

    public function RejectPermintaan(Request $request)
    {
        $data = RecordPermintaanBarang::where('id', $request->id)->first();
        $data->flag = 1;
        $data->note = $request->note;
        $data->save();

        $response = Http::post('https://apps.intilab.com/v4/public/api/notif', [
            'user_id' => $data->id_user,
            'title' => 'Pengambilan Barang void',
            'body' => $request->note
        ]);

        return response()->json([
            'message' => 'Data hasbeen Reject.!'
        ], 200);
    }
}

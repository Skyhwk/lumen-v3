<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\BarangMasuk;
use App\Models\MasterKaryawan;
use App\Models\Barang;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class BarangMasukController extends Controller
{
    public function index(Request $request)
    {
        $data = BarangMasuk::with('kategori', 'barang')
            ->where('barang_masuk.id_cabang', $request->idcabang)
            ->whereYear('barang_masuk.created_at', $request->tahun)
            ->orderBy('barang_masuk.id', 'DESC');

        return Datatables::of($data)
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('kode_barang', function ($query, $keyword) {
                $query->where('kode_barang', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('barang.nama_barang', function ($query, $keyword) {
                $query->whereHas('barang', function ($q) use ($keyword) {
                    $q->where('nama_barang', 'like', '%' . $keyword . '%');
                });
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
            ->filterColumn('harga_satuan', function ($query, $keyword) {
                $query->where('harga_satuan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function getBarang(Request $request)
    {
        $data = Barang::where('id_cabang', $request->idcabang)->where('is_active', 1)->get();
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $dataBarang = Barang::where('id', $request->id_barang)->where('is_active', 1)->first();
        // dd($dataBarang);
        // Periksa apakah data barang ditemukan
        if ($dataBarang) {
            $dataBarang->update([
                'akhir' => $dataBarang->akhir + $request->jumlah
            ]);

            $dataBarangMasuk = new BarangMasuk;
            $dataBarangMasuk->id_cabang = $request->id_cabang;
            $dataBarangMasuk->id_barang = $request->id_barang;
            $dataBarangMasuk->id_kategori = $dataBarang->id_kategori;
            $dataBarangMasuk->kode_barang = $request->kode_barang;
            $dataBarangMasuk->jumlah = $request->jumlah;
            $dataBarangMasuk->harga_satuan = $request->harga_satuan;
            $dataBarangMasuk->harga_total = $request->harga_satuan * $request->jumlah;
            $dataBarangMasuk->created_at = Carbon::now();
            $dataBarangMasuk->created_by = $this->karyawan;
            $dataBarangMasuk->save();
            
            return response()->json(['status' => 'success', 'message' => 'Data barang masuk berhasil disimpan']);
        } else {
            // Jika barang tidak ditemukan, kembalikan respons error
            return response()->json(['status' => 'error', 'message' => 'Barang tidak ditemukan atau tidak aktif'], 404);
        }
    }
}
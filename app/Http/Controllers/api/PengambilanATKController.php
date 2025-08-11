<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RecordPermintaanBarang;
use App\Models\MasterKaryawan;
use App\Models\KategoriBarang;
use App\Models\Barang;

use Yajra\Datatables\Datatables;
use DB;

class PengambilanATKController extends Controller
{
    public function index(Request $request)
    {
        $data = RecordPermintaanBarang::with('barang', 'karyawan')
        ->where('submited', 0)
        ->where('flag', 0)
        ->where('id_user', $this->user_id)
        ->orderBy('id', 'desc')
        ->orderBy('status', 'asc');

        return Datatables::of($data)->make(true);
    }

    public function save(Request $request)
    {
        $formFIeld = [];
        DB::beginTransaction();
        try {
            if(is_array($request->items)){
                $batch = \str_replace('.', '/', microtime(true));
                foreach($request->items as $key => $value) {
                    if (is_array($value)) {
                        $value = (object) $value;
                    }
                    $barang = Barang::where('id', $value->nama_barang)->first();
                    $payload = [
                        'request_id' => $batch,
                        'timestamp' => \date('Y-m-d H:i:s'),
                        'id_cabang' => $barang->id_cabang,
                        'id_user' => $this->user_id,
                        'nama_karyawan' => $this->karyawan,
                        'divisi' => $request->attributes->get('user')->karyawan->department,
                        'id_kategori' => $barang->id_kategori,
                        'id_barang' => $barang->id,
                        'kode_barang' => $barang->kode_barang,
                        'nama_barang' => $barang->nama_barang,
                        'jumlah' => $value->qty,
                        'keterangan' => $request->keterangan
                    ];
    
                    RecordPermintaanBarang::create($payload);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Pengajuan Pengambilang Barang Berhasil'], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(['message' => 'Pengajuan Pengambilang Barang Gagal :'. $th->getMessage()], 500);
        }
    }

    public function void (Request $request) {
        try {
            $data = RecordPermintaanBarang::where('id', $request->id)->firstOrFail();
            $data->flag = 1;
            $data->note = $request->note;
            $data->save();
            
            return response()->json([
                'message'=> 'Data berhasil divoid dengan keterangan : '. $request->note 
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'message'=> 'Terjadi Kesalahan : ' . $th
            ]);
        }
    }


    public function getBarangList(Request $request)
    {
        $data = Barang::where('is_active', 1)->where('akhir', '>',  0)->get();
        return response()->json(['data' => $data]);
    }
}
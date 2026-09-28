<?php

namespace App\Http\Controllers\Greatday;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

use App\Models\Greatday\{Barang, RecordPermintaanBarang};
use App\Models\{MasterKaryawan};

class SupplyRequestsController extends Controller
{
    public function index()
    {
        $data = RecordPermintaanBarang::where('id_user', $this->user_id)->where('flag', 0)->get();

        return response()->json([
            'data' => $data,
            'message' => 'Supply requests retrieved successfully',
        ], 200);
    }

    public function savePengajuanBarang(Request $request)
    {
        $tanggal = Carbon::now();
        $request_id = \str_replace(".", "/", microtime(true));
        $user = MasterKaryawan::with(['cabang', 'department'])->where('id', $this->user_id)->first();
        $data = [];
        foreach ($request->items as $item) {
            $item = (object) $item;
            $barang = Barang::find($item->item);
            $data[] = [
                'id_cabang' => $user->cabang->id,
                'request_id' => $request_id,
                'timestamp' => $tanggal,
                'id_user' => $user->id,
                'nama_karyawan' => $user->nama_lengkap,
                'divisi' => $user->department,
                'id_kategori' => $barang->id_kategori,
                'id_barang' => $item->item,
                'nama_barang' => $barang->nama_barang,
                'kode_barang' => $barang->kode_barang,
                'jumlah' => $item->quantity,
                'keterangan' => $request->keperluan
            ];
        }

        DB::beginTransaction();
        RecordPermintaanBarang::insert($data);
        DB::commit();

        // ============================SEND NOTIF TO USER========================================
        // $tokenCurrent = $request->attributes->get('userAccess')->token;
        // $notifUser = new Notification();
        // $notifUser->index($tokenCurrent, 'Permintaan Barang', 'permintaan anda telah dikirim', ['screen' => 'Main', 'params' => []]);
        // =========================END SEND NOTIF TO USER========================================

        return response()->json(['message' => 'Your supply request has been submitted successfully',], 201);
    }

    public function getBarang()
    {
        $dataUser = MasterKaryawan::with(['jabatan', 'department'])
            ->where('user_id', $this->user_id)
            ->select('id_cabang', 'user_id', 'nama_lengkap', 'nik_karyawan', 'id_department', 'id_jabatan')
            ->first();

        $dataBarang = Barang::where('is_active', true)
            ->where('akhir', '>', 0)
            ->select('id', 'nama_barang', 'kode_barang', 'merk', 'ukuran', 'satuan')
            ->get();

        return response()->json([
            'message' => 'Barangs retrieved successfully',
            'karyawan' => $dataUser,
            'barang' => $dataBarang,
        ], 200);
    }
}

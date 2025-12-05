<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
use Yajra\Datatables\Datatables;

use App\Models\MasterKuotaTarget;

class MasterKuotaTargetController extends Controller
{
    public function index()
    {
        $data = MasterKuotaTarget::where([
            'created_by' => $this->karyawan,
            'is_active' => true
        ]);

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $kuota = [];
            foreach ($request->KATEGORI as $key => $value) {
                $kuota[$value] = (int) $request->VALUE[$key];
            }

            if ($request->id) {
                $data = MasterKuotaTarget::where('id', $request->id)->first();
                if ($data) {
                    if ($request->nama_master && $request->nama_master != $data->nama_master) $data->nama_master = $request->nama_master;
                    if (count($kuota) > 0 && $data->kuota != $kuota) $data->kuota = $kuota;
                    $data->total_target = (int) $request->total_target;
                    $data->updated_by = $this->karyawan;
                    $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                }
            } else {
                $data = new MasterKuotaTarget();
                $data->nama_master = $request->nama_master;
                $data->kuota = $kuota;
                $data->total_target = (int) $request->total_target;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
            }

            DB::commit();
            return response()->json(['message' => 'Saved Successfully'], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 401);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = MasterKuotaTarget::where('id', $request->id)->first();
            if ($data) {
                $data->is_active = false;
                $data->save();
            }

            DB::commit();
            return response()->json(['message' => 'Deleted Successfully'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 401);
        }
    }

    public function getKategori()
    {
        $kategori = [
            'AIR' => [
                'AIR LIMBAH', //-> limbah, domestik, industri
                'AIR BERSIH',
                'AIR MINUM',
                'AIR SUNGAI',
                'AIR LAUT',
                'AIR LAINNYA'
            ],
            'UDARA' => [
                'UDARA AMBIENT',
                'UDARA LINGKUNGAN KERJA',
                'KEBISINGAN',
                'PENCAHAYAAN',
                'GETARAN',
                'IKLIM KERJA',
                'UDARA LAINNYA'
            ],
            'EMISI' => [
                'EMISI SUMBER BERGERAK', // Emisi Kendaraan (Bensin), Emisi Kendaraan (Solar), Emisi Kendaraan (Gas)
                'EMISI SUMBER TIDAK BERGERAK',
                'EMISI ISOKINETIK'
            ]
        ];

        $harga = [
            'AIR LIMBAH' => config('harga_kategori.HARGA_AIR_LIMBAH'), //-> limbah, domestik, industri
            'AIR BERSIH' => config('harga_kategori.HARGA_AIR_BERSIH'),
            'AIR MINUM' => config('harga_kategori.HARGA_AIR_MINUM'),
            'AIR SUNGAI' => config('harga_kategori.HARGA_AIR_SUNGAI'),
            'AIR LAUT' => config('harga_kategori.HARGA_AIR_LAUT'),
            'AIR LAINNYA' => config('harga_kategori.HARGA_AIR_LAINNYA'),
            'UDARA AMBIENT' => config('harga_kategori.HARGA_UDARA_AMBIENT'),
            'UDARA LINGKUNGAN KERJA' => config('harga_kategori.HARGA_UDARA_LINGKUNGAN_KERJA'),
            'KEBISINGAN' => config('harga_kategori.HARGA_KEBISINGAN'),
            'PENCAHAYAAN' => config('harga_kategori.HARGA_PENCAHAYAAN'),
            'GETARAN' => config('harga_kategori.HARGA_GETARAN'),
            'IKLIM KERJA' => config('harga_kategori.HARGA_IKLIM_KERJA'),
            'UDARA LAINNYA' => config('harga_kategori.HARGA_UDARA_LAINNYA'),
            'EMISI SUMBER BERGERAK' => config('harga_kategori.HARGA_EMISI_SUMBER_BERGERAK'), // Emisi Kendaraan (Bensin), Emisi Kendaraan (Solar), Emisi Kendaraan (Gas)
            'EMISI SUMBER TIDAK BERGERAK' => config('harga_kategori.HARGA_EMISI_SUMBER_TIDAK_BERGERAK'),
            'EMISI ISOKINETIK' => config('harga_kategori.HARGA_EMISI_ISOKINETIK')
        ];

        return response()->json(['kategori' => $kategori, 'harga' => $harga], 200);
    }
}

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
        $data = MasterKuotaTarget::where('is_active', true)->where('created_by', $this->karyawan);

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
                    $data->updated_by = $this->karyawan;
                    $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                }
            } else {
                $data = new MasterKuotaTarget();
                $data->nama_master = $request->nama_master;
                $data->kuota = $kuota;
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
        $array = [
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

        return response()->json(['data' => $array], 200);
    }
}

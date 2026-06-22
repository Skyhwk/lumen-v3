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
        $rawKategori = config('kategori.non_id');

        $kategori = [
            'AIR' => [],
            'UDARA' => [],
            'EMISI' => []
        ];

        foreach (array_keys($rawKategori) as $key) {
            if (strpos($key, 'AIR') === 0) {
                $kategori['AIR'][] = $key;
            } elseif (strpos($key, 'EMISI') === 0) {
                $kategori['EMISI'][] = $key;
            } else {
                $kategori['UDARA'][] = $key;
            }
        }

        $harga = [];
        foreach (config('harga_kategori') as $k => $v) {
            $cleanedKey = trim(str_replace('_', ' ', str_replace('HARGA', '', $k)));
            $harga[$cleanedKey] = $v;
        }

        return response()->json(['kategori' => $kategori, 'harga' => $harga], 200);
    }
}

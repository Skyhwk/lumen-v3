<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;

use App\Services\GetBawahan;

use App\Models\MasterKaryawan;
use App\Models\MasterTargetSales;
use App\Models\MasterKuotaTarget;

class MasterTargetSalesController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterTargetSales::with('sales')->where('tahun', $request->tahun)->where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $masterTargetSales = MasterTargetSales::where([
                'karyawan_id' => $request->karyawan_id,
                'tahun' => $request->tahun,
                'is_active' => true,
            ])->when($request->id, fn($q) => $q->where('id', '!=', $request->id))->first();

            if ($masterTargetSales) return response()->json(['message' => 'Data Sudah Ada untuk Sales dan Tahun tersebut'], 401);

            $kuota = [];
            if ($request->KATEGORI) {
                foreach ($request->KATEGORI as $key => $value) {
                    $kuota[$value] = (int) $request->VALUE[$key];
                }
            }

            if ($request->id) {
                $data = MasterTargetSales::find($request->id);
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');

                $target = json_decode($data->target, true);
            } else {
                $data = new MasterTargetSales();
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');

                $target = [];
            }

            $periodeArr = array_values(array_unique($request->periode ?? []));
            $syncAllPeriods = filter_var(
                $request->sync_all_periods ?? !$request->id,
                FILTER_VALIDATE_BOOLEAN
            );

            $data->karyawan_id  = $request->karyawan_id;
            $data->tahun        = $request->tahun;

            $monthMap = [
                '01' => 'januari',
                '02' => 'februari',
                '03' => 'maret',
                '04' => 'april',
                '05' => 'mei',
                '06' => 'juni',
                '07' => 'juli',
                '08' => 'agustus',
                '09' => 'september',
                '10' => 'oktober',
                '11' => 'november',
                '12' => 'desember',
            ];

            foreach ($monthMap as $k => $v) {
                $checkPeriod = $request->tahun . '-' . $k;
                if (in_array($checkPeriod, $periodeArr, true)) {
                    $data->{$v} = $kuota;
                    $target[$checkPeriod] = (int) $request->total_target;
                } elseif ($syncAllPeriods) {
                    $data->{$v} = null;
                    unset($target[$checkPeriod]);
                }
            }

            $data->target = json_encode($target);

            $data->save();

            DB::commit();
            return response()->json(['message' => 'Saved Successfully'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 401);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = MasterTargetSales::where('id', $request->id)->first();
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

    /** Hapus target satu bulan (kolom bulan + entri JSON target), tanpa mengubah bulan lain. */
    public function clearMonth(Request $request)
    {
        $id = (int) $request->id;
        $period = (string) $request->period;

        if (!$id || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return response()->json(['message' => 'Parameter id dan period (YYYY-MM) wajib valid'], 422);
        }

        $monthMap = [
            '01' => 'januari',
            '02' => 'februari',
            '03' => 'maret',
            '04' => 'april',
            '05' => 'mei',
            '06' => 'juni',
            '07' => 'juli',
            '08' => 'agustus',
            '09' => 'september',
            '10' => 'oktober',
            '11' => 'november',
            '12' => 'desember',
        ];

        [, $month] = explode('-', $period);
        $column = $monthMap[$month] ?? null;
        if (!$column) {
            return response()->json(['message' => 'Bulan tidak valid'], 422);
        }

        DB::beginTransaction();
        try {
            $data = MasterTargetSales::where(['id' => $id, 'is_active' => true])->first();
            if (!$data) {
                DB::rollBack();

                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }

            $data->{$column} = null;
            $target = json_decode($data->target, true) ?? [];
            unset($target[$period]);
            $data->target = json_encode($target);
            $data->updated_by = $this->karyawan;
            $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();

            return response()->json(['message' => 'Target bulan dihapus'], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json(['message' => $th->getMessage()], 401);
        }
    }

    public function getTemplate()
    {
        $template = MasterKuotaTarget::where([
            'created_by' => $this->karyawan,
            'is_active' => true
        ])->get();

        return response()->json(['data' => $template], 200);
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

        $idBawahan = GetBawahan::where('id', 890)->get()->pluck('id')->toArray();

        $sales = MasterKaryawan::where('is_active', true)
            ->whereIn('id', $idBawahan)
            ->whereIn('id_jabatan', [24, 148])
            ->orWhere('nama_lengkap', 'Novva Novita Ayu Putri Rukmana')
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        return response()->json([
            'data' => $kategori,
            'harga' => $harga,
            'sales' => $sales
        ], 200);
    }
}

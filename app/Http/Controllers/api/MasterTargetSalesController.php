<?php

namespace App\Http\Controllers\api;

use App\Models\MasterKuotaTarget;
use App\Models\MasterTargetSales;
use App\Models\MasterKaryawan;

use App\Services\GetBawahan;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class MasterTargetSalesController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterTargetSales::with('sales')->where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request){
        DB::beginTransaction();
        try {
            $kuota = [];
            foreach($request->KATEGORI as $key => $value){
                $kuota = [...$kuota, $value => (int)$request->VALUE[$key]];
            }

            $cek = MasterTargetSales::where('karyawan_id', $request->karyawan_id)->where('tahun', $request->tahun)->first();
            if($cek){
                return response()->json([
                    'message' => 'Data Sudah Ada'
                ], 401);
            }

            $data = new MasterTargetSales();
            $data->karyawan_id  = $request->karyawan_id;
            $data->tahun        = $request->tahun;
            $data->januari      = in_array($request->tahun . '-01', $request->periode) ? $kuota : null;
            $data->februari     = in_array($request->tahun . '-02', $request->periode) ? $kuota : null;
            $data->maret        = in_array($request->tahun . '-03', $request->periode) ? $kuota : null;
            $data->april        = in_array($request->tahun . '-04', $request->periode) ? $kuota : null;
            $data->mei          = in_array($request->tahun . '-05', $request->periode) ? $kuota : null;
            $data->juni         = in_array($request->tahun . '-06', $request->periode) ? $kuota : null;
            $data->juli         = in_array($request->tahun . '-07', $request->periode) ? $kuota : null;
            $data->agustus      = in_array($request->tahun . '-08', $request->periode) ? $kuota : null;
            $data->september    = in_array($request->tahun . '-09', $request->periode) ? $kuota : null;
            $data->oktober      = in_array($request->tahun . '-10', $request->periode) ? $kuota : null;
            $data->november     = in_array($request->tahun . '-11', $request->periode) ? $kuota : null;
            $data->desember     = in_array($request->tahun . '-12', $request->periode) ? $kuota : null;
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Data Hasbeen Save'
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 401);
        }
    }

    public function delete(Request $request){
        DB::beginTransaction();
        try {
            $data = MasterKuotaTarget::where('id', $request->id)->first();
            if($data){
                $data->is_active = false;
                $data->save();
            }
            DB::commit();
            return response()->json([
                'message' => 'Data Hasbeen Deleted'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 401);
        }
    }

    public function getTemplate(Request $request){
        $template = MasterKuotaTarget::where('is_active', true)->where('created_by', $this->karyawan)->get();
        return response()->json([
            'data' => $template
        ], 200);
    }

    public function getKategori(Request $request){
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
                'EMISI SUMBER BERGERAK',
                'EMISI SUMBER TIDAK BERGERAK',
                'EMISI ISOKINETIK'
            ]
        ];

        // $idBawahan = GetBawahan::where('nama_lengkap', $this->karyawan)->get()->pluck('id')->toArray();
        $idBawahan = GetBawahan::where('id', 890)->get()->pluck('id')->toArray();

        $sales = MasterKaryawan::where('is_active', true)
        ->whereIn('id', $idBawahan)
        ->whereIn('id_jabatan', [24, 21])
        ->get();

        return response()->json([
            'data' => $array,
            'sales' => $sales
        ], 200);
    }
}
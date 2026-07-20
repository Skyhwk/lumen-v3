<?php

namespace App\Http\Controllers\api;

use App\Models\LingkunganHeader;
use App\Models\DebuPersonalHeader;
use App\Models\OrderDetail;
use App\Models\WsValueLingkungan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\ApproveAnalystService;
use App\Models\DustFallHeader;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class LimsGravimetriUdaraController extends Controller
{
    public function index(Request $request){
        $limsDb = DB::connection('lims')->getDatabaseName();

        $queryLingkungan = LingkunganHeader::with('ws_udara', 'ws_value')
            ->join(DB::raw("{$limsDb}.order_detail as order_detail"),'order_detail.no_sampel','=','lingkungan_header.no_sampel')
            ->where('lingkungan_header.is_approved', $request->approve)
            ->where('lingkungan_header.is_active', true)
            ->where('lingkungan_header.template_stp', $request->template_stp)
            ->select('lingkungan_header.*', 'order_detail.tanggal_terima as od_tanggal_terima', 'order_detail.kategori_3 as od_kategori_3','order_detail.tanggal_sampling as od_tanggal_sampling')
            ->orderByRaw("
                CASE 
                    WHEN order_detail.tanggal_terima IS NULL THEN 1
                    ELSE 0
                END,
                order_detail.tanggal_terima DESC
            ");

        $queryDustfall = DustFallHeader::with('ws_udara', 'ws_value')
            ->join(DB::raw("{$limsDb}.order_detail as order_detail"),'order_detail.no_sampel','=','dustfall_header.no_sampel')
            ->where('dustfall_header.is_approved', $request->approve)
            ->where('dustfall_header.is_active', true)
            ->where('dustfall_header.template_stp', $request->template_stp)
            ->select('dustfall_header.*', 'order_detail.tanggal_terima as od_tanggal_terima', 'order_detail.kategori_3 as od_kategori_3','order_detail.tanggal_sampling as od_tanggal_sampling')
            ->orderByRaw("
                CASE 
                    WHEN order_detail.tanggal_terima IS NULL THEN 1
                    ELSE 0
                END,
                order_detail.tanggal_terima DESC
            ");

        $queryDebu = DebuPersonalHeader::with('ws_udara', 'ws_value')
            ->join(DB::raw("{$limsDb}.order_detail as order_detail"),'order_detail.no_sampel','=','debu_personal_header.no_sampel')
            ->where('debu_personal_header.is_approved', $request->approve)
            ->where('debu_personal_header.is_active', true)
            ->where('debu_personal_header.template_stp', $request->template_stp)
            ->select('debu_personal_header.*', 'order_detail.tanggal_terima as od_tanggal_terima', 'order_detail.kategori_3 as od_kategori_3','order_detail.tanggal_sampling as od_tanggal_sampling')
            ->orderByRaw("
                CASE 
                    WHEN order_detail.tanggal_terima IS NULL THEN 1
                    ELSE 0
                END,
                order_detail.tanggal_terima DESC
            ");

        if ($request->filled('periode')) {
            $periode = explode('-', $request->periode);
            if (count($periode) == 2) {
                $queryLingkungan->whereYear('lingkungan_header.created_at', $periode[0])
                                ->whereMonth('lingkungan_header.created_at', $periode[1]);
                $queryDustfall->whereYear('dustfall_header.created_at', $periode[0])
                              ->whereMonth('dustfall_header.created_at', $periode[1]);
                $queryDebu->whereYear('debu_personal_header.created_at', $periode[0])
                          ->whereMonth('debu_personal_header.created_at', $periode[1]);
            }
        }

        $data = collect()
            ->concat($queryLingkungan->get())
            ->concat($queryDustfall->get())
            ->concat($queryDebu->get())
            ->values();
            
        return Datatables::of($data)
            ->editColumn('data_pershift', function ($data) {
                return $data->data_pershift ? json_decode($data->data_pershift, true) : null;
            })
            ->editColumn('data_shift', function ($data) {
                return $data->data_shift ? json_decode($data->data_shift, true) : null;
            })
            ->editColumn('inputan_analis', function ($data) {
                if ($data instanceof DustFallHeader) {
                    return $data->inputan_analis
                        ? json_decode($data->inputan_analis, true)
                        : null;
                }
                return null;
            })
            ->addColumn('tanggal_terima', function ($item) {
                return $item->od_tanggal_terima ?? '-';
            })
            ->addColumn('tanggal_sampling', function ($item) {
                return $item->od_tanggal_sampling ?? '-';
            })
            ->addColumn('kategori_3', function ($item) {
                return $item->od_kategori_3 ?? '-';
            })
        ->make(true);
    }

    public function approveData(Request $request){

        DB::beginTransaction();
        try {
            $data = LingkunganHeader::where('id', $request->id)->where('is_active', true)->first();
            if(is_null($data) || $data->is_approved == 1){
                $data = DebuPersonalHeader::where('id', $request->id)->where('is_active', true)->first();
            }
            if(is_null($data) || $data->is_approved == 1){
                $data = DustFallHeader::where('id', $request->id)->where('is_active', true)->first();
            }
            if($data->is_approved == 1){
                return response()->json([
                    'status' => false,
                    'message' => 'Data udara gravimetri no sample ' . $data->no_sampel . ' sudah di approve'
                ],401);
            }
            $data->is_approved = 1;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->approved_by = $this->karyawan;
            $data->save();

            ApproveAnalystService::noSampel($data->no_sampel)
                ->approvedBy($this->karyawan)
                ->menu('Analysis');

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Data Udara gravimetri no sample ' . $data->no_sampel . ' berhasil di approve'
            ],200);

        } catch (\Throwable $th) {
            DB::rollBack();
            // dd($th);
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan! ' . $th->getMessage()
            ],401);
        }
    }

    public function deleteData(Request $request){
        DB::beginTransaction();
        try {
            $data = LingkunganHeader::where('id', $request->id)->where('is_active', true)->first();
            if(is_null($data)){
                $data = DebuPersonalHeader::where('id', $request->id)->where('is_active', true)->first();

            }
            if(is_null($data)){
                $data = DustFallHeader::where('id', $request->id)->where('is_active', true)->first();
            }
            $data->is_active = false;
            $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->save();

            $ws_value = WsValueLingkungan::where('lingkungan_header_id', $request->id)->where('is_active', true)->first();
            if($ws_value){
                $ws_value->is_active = false;
                $ws_value->save();
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Data Udara gravimetri no sample ' . $data->no_sampel . ' berhasil dihapus .!'
            ],200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan! ' . $th->getMessage()
            ],401);
        }
    }
}

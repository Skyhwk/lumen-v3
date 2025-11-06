<?php

namespace App\Http\Controllers\api;

use App\Models\HoldHp;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\WsValueAir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class HoldHpController extends Controller
{
    public function index(Request $request){
        if($request->type == 'Hold'){
            $data = HoldHp::with('orderHeader', 'orderHeader.quotationKontrakH', 'orderHeader.quotationNonKontrak')->where('is_hold', 1)->orderBy('hold_at', 'desc')->get();
        }else {
            $data = HoldHp::with('orderHeader', 'orderHeader.quotationKontrakH', 'orderHeader.quotationNonKontrak')->where('is_hold', 0)->orderBy('hold_at', 'desc')->get();
        }
        
        return Datatables::of($data)->make(true);
    }

    public function getListOrder(Request $request)
    {
        $listData = OrderHeader::with(['holdHp' => function ($query) {
            $query->where('is_hold', 0);
        }])
            ->select(['id', 'no_order', 'nama_perusahaan', 'konsultan'])
            ->where(function ($query) use ($request) {
                $query->where('no_order', 'LIKE', "%{$request->term}%")
                    ->orWhere('nama_perusahaan', 'LIKE', "%{$request->term}%");
            })
            ->where(function ($query) {
                $query->whereDoesntHave('holdHp') // belum punya holdHp
                    ->orWhereHas('holdHp', function ($q) {
                        $q->where('is_hold', 0); // punya tapi masih 0
                    });
            })
            ->take(20)
            ->get();

        return response()->json($listData, 200);
    }


    public function getDetailOrder(Request $request)
    {
        $data = OrderHeader::where('no_order', $request->no_order)
            ->first();
        
        $data->quotation_final = $data->quotation_final;

        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        $data = OrderHeader::where('no_order', $request->order)->first();

        if(!$data) {
            return response()->json(['message' => 'Tidak ada data order Tersebut', 'status' => '404'], 404);
        }
        HoldHp::updateOrCreate(
            ['no_order' => $request->order], 
            [
                'keterangan' => $request->keterangan,
                'is_hold' => 1,
                'hold_by' => $this->karyawan,
                'hold_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]
        );
        
        return response()->json(['message' => 'Data berhasil disimpan', 'status' => '200'], 200);
    }

    public function hold(Request $request) {
        $data = HoldHp::find($request->id);
        $data->is_hold = 1;
        $data->keterangan = $request->keterangan ?? null;
        $data->hold_by = $this->karyawan;
        $data->hold_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->save();
        return response()->json(['message' => "Data hasil pengujian $data->no_order Berhasil di hold ", 'status' => '200'], 200);
    }

    public function unhold(Request $request) {
        $data = HoldHp::find($request->id);
        $data->is_hold = 0;
        $data->keterangan_unhold = $request->keterangan ?? null;
        $data->un_hold_by = $this->karyawan;
        $data->un_hold_at = Carbon::now()->format('Y-m-d H:i:s');
        $data->save();
        return response()->json(['message' => "Data hasil pengujian $data->no_order Berhasil di un-hold ", 'status' => '200'], 200);
    }
}
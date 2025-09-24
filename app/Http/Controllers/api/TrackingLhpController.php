<?php

namespace App\Http\Controllers\api;

use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Services\TrackingLhpService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use App\Services\GetBawahan;
use Carbon\Carbon;

class TrackingLhpController extends Controller
{
    public function index()
    {
        if($this->department == 9) {
            $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
            $data = OrderHeader::where('is_active', true)->whereIn('created_by', $getBawahan)->orderBy('created_at', 'desc');
        } else {
            $data = OrderHeader::where('is_active', true)->orderBy('created_at', 'desc');
        };

        return Datatables::of($data)->make(true);
    }

    public function getDetails(Request $request) {
        $id = $request->id;
        $details = OrderDetail::where('id_order_header', $id)->where('is_active', true)->get();
        $orderDate = OrderHeader::where('id', $id)->first()->created_at;
        $dataReturn = [];
        try {
            if(count($details) > 0) {
            foreach($details as $detail) {
                $trackingService = new TrackingLhpService($detail->no_sampel, $orderDate);
                $dataReturn[] = (object)[
                    'no_sampel' => $detail->no_sampel,
                    'sub_kategori' => explode('-', $detail->kategori_3)[1],
                    'penamaan_titik' => $detail->keterangan_1,
                    'tracking' => $trackingService->track()
                ];
            }
            
            return response()->json($dataReturn);
        } else{
            return response()->json([
                'message' => 'Tidak ada data sampel pada order ini'
            ], 400);
        }
        } catch (\Exception $th) {
            dd($th);
            return response()->json($th);
        }

    }

}
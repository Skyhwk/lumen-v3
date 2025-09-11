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

class TrackingSampleTdlController extends Controller
{
    public function index()
    {
        $data = OrderDetail::with('orderHeader')
            ->where('is_active', true)
            ->where('status',  3)
            ->where('kategori_1', '!=', 'SD')
            ->orderBy('created_at', 'desc');

        return Datatables::of($data)
            ->filterColumn('status', function($query, $keyword) {
                if (strtolower($keyword) === 'done') {
                    $query->where('status', 3);
                } elseif (strtolower($keyword) === 'on-going' || strtolower($keyword) === 'ongoing') {
                    $query->where('status', '!=', 3);
                }
            })
            ->make(true);
    }

    public function getDetails(Request $request) {
        $id = $request->id;
        $detail = OrderDetail::with('orderHeader')->where('id', $id)->where('is_active', true)->first();
        $dataReturn = (object)[];
        try {
            $trackingService = new TrackingLhpService($detail->no_sampel, $detail->orderHeader->created_at);
            $dataReturn = (object)[
                'no_sampel' => $detail->no_sampel,
                'sub_kategori' => explode('-', $detail->kategori_3)[1],
                'penamaan_titik' => $detail->keterangan_1,
                'tracking' => $trackingService->track()
            ];
            return response()->json($dataReturn);
        } catch (\Exception $th) {
            dd($th);
            return response()->json($th);
        }

    }

}
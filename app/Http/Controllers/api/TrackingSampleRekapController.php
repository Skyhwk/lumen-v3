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

class TrackingSampleRekapController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::with('orderHeader')
            ->where('is_active', true)
            ->where('status', '=',  3)
            ->where(function ($query) use ($request) {
                if($request->mode == 'SD') {
                    $query->where('kategori_1', 'SD');
                } else {
                    $query->where('kategori_1', '!=', 'SD');
                }
            })
            ->orderBy('created_at', 'desc');

        return Datatables::of($data)
            ->filterColumn('kategori_1', function($query, $keyword) {
                $keyword = strtolower($keyword);

                if ($keyword === 'sampling') {
                    $query->where('kategori_1', 'S');
                } elseif ($keyword === 'sampling 24 jam' || $keyword === 'samping 24 jam') {
                    $query->where('kategori_1', 'S24');
                } elseif ($keyword === 're - sampling' || $keyword === 'resampling' || $keyword === 're sampling') {
                    $query->where('kategori_1', 'RS');
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
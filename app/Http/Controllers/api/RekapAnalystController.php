<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\MasterKategori;
use App\Models\WsValueAir;
use App\Models\MasterSubKategori;
use App\Models\MasterRegulasi;
use Carbon\Carbon;
use DataTables;
use Exception;

class RekapAnalystController extends Controller
{
    public function index(Request $request)
    {
        // dd(\explode('-', $request->date)[1]);
        list($year, $month) = explode('-', $request->date);
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        if($request->kategori == '') {
            $kategori = "1-Air";
        }else {
            $kategori = $request->kategori;
        }

        $data = OrderDetail::with(['TrackingSatu', 'wsValueAir'])->where('is_active', true)
            // ->whereHas('TrackingSatu')
            ->where('kategori_2', $kategori)
            ->whereMonth('tanggal_terima', $month)
            ->whereYear('tanggal_terima', $year)
            ->orderBy('id', 'desc');
        // dd($data);
        return DataTables::of($data)
            ->filterColumn('tanggal_sampling', function ($query, $keyword) {
                $query->where('tanggal_sampling', 'like', "%$keyword%");
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', "%$keyword%");
            })
            ->filterColumn('no_order', function ($query, $keyword) {
                $query->where('no_order', 'like', "%$keyword%");
            })
            ->filterColumn('tracking_satu', function ($query, $keyword) {
                $query->whereHas('TrackingSatu', function ($q) use ($keyword) {
                    $q->where('ftc_laboratory', 'like', "%$keyword%");
                });
            })
            ->filterColumn('tanggal_terima', function ($query, $keyword) {
                $query->where('tanggal_terima', 'like', "%$keyword%");
            })
            ->filterColumn('kategori_2', function ($query, $keyword) {
                $query->where('kategori_2', 'like', "%$keyword%");
            })
            ->make(true);
    }

    public function detail(Request $request)
    {
        $data = WsValueAir::with(['gravimetri', 'titrimetri', 'colorimetri'])
            ->where('no_sampel', $request->no_sampel)
            ->where('status', 0)
            ->where('is_active', 1);
            // ->where(function ($query) {
            //     $query->whereHas('colorimetri', function ($q) {
            //         $q->where('is_approved', 1);
            //     })
            //     ->orWhereHas('gravimetri', function ($q) {
            //         $q->where('is_approved', 1);
            //     })
            //     ->orWhereHas('titrimetri', function ($q) {
            //         $q->where('is_approved', 1);
            //     });
            // });
    
        return Datatables::of($data)->make(true);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', 1)->get();
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Available data category retrieved successfully',
        ], 201);
    }

    public function getSubKategori(Request $request)
    {
        $data = MasterSubKategori::where('id_kategori', $request->id_kategori)->get();
        return response()->json($data);
    }
    
    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::with(['bakumutu'])->where('id_kategori', $request->id_kategori)->get();
        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        $data = Parameter::where('id_kategori', $request->id_kategori)
        ->where('is_active', 1)
        ->get();
        return response()->json($data);
    }

}

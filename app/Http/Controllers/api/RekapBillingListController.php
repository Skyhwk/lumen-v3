<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class RekapBillingListController extends Controller
{
    public function index(Request $request)
    {
        $data = DB::table('billing_list_header')
            ->where('is_complete', $request->is_complete);

        $page = $request->start > 29 ? "lanjut" : "awal";

        return DataTables::of($data)
            ->addColumn('nilai_piutang', function ($data) {
                $tagihan  = $data->nilai_tagihan ?? 0;
                $terbayar = $data->terbayar ?? 0;
                $piutang  = $tagihan - $terbayar;

                return max(0, $piutang);
            })
            ->with([
                'sum_nilai_tagihan'  => function ($query) {
                    return $query->sum('nilai_tagihan');
                },
                'sum_nilai_terbayar' => function ($query) {
                    return $query->sum('terbayar');
                },
                'sum_nilai_piutang'  => function ($query) {
                    $terbayar = $query->sum('terbayar');
                    $tagihan  = $query->sum('nilai_tagihan');
                    $piutang  = $tagihan - $terbayar;

                    return max(0, $piutang);
                },
                'page'               => function () use ($page) {
                    return $page;
                },
            ])
            ->make(true);
    }

    public function getDetail(Request $request)
    {
        $data = DB::table('billing_list_detail')
            ->where('billing_header_id', $request->id_header);
        $page = $request->start > 29 ? "lanjut" : "awal";


        return DataTables::of($data)
            ->addColumn('nilai_piutang', function ($data) {
                $tagihan  = $data->nilai_tagihan ?? 0;
                $terbayar = $data->terbayar ?? 0;
                $piutang  = $tagihan - $terbayar;

                return max(0, $piutang);
            })
            ->with([
                'sum_nilai_tagihan'  => function ($query) {
                    return $query->sum('nilai_tagihan');
                },
                'sum_nilai_terbayar' => function ($query) {
                    return $query->sum('terbayar');
                },
                'sum_nilai_piutang'  => function ($query) {
                    $terbayar = $query->sum('terbayar');
                    $tagihan  = $query->sum('nilai_tagihan');
                    $piutang  = $tagihan - $terbayar;

                    return max(0, $piutang);
                },
                'page'               => function () use ($page) {
                    return $page;
                },
            ])->make(true);

    }

}

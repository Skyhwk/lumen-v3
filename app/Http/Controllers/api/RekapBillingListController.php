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
            ->select(
                'id',
                'id_pelanggan',
                'nama_pelanggan',
                'nilai_tagihan',
                'terbayar',
                DB::raw('nilai_tagihan - terbayar as nilai_piutang'),
                'is_complete',
                DB::raw("
                    CASE
                        WHEN sales_penanggung_jawab = 'Dedi Wibowo'
                        THEN '-'
                        ELSE sales_penanggung_jawab
                    END as sales_penanggung_jawab
                ")
            )
            ->where('is_complete', $request->is_complete);

        $page = $request->start > 29 ? "lanjut" : "awal";

        return DataTables::of($data)
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
            ->select(
                'billing_list_detail.id',
                'billing_list_detail.billing_header_id',
                'billing_list_detail.no_invoice',
                'billing_list_detail.no_quotation',
                'billing_list_detail.no_order',
                'billing_list_detail.periode',
                'billing_list_detail.tgl_sampling',
                'billing_list_detail.tgl_invoice',
                'billing_list_detail.tgl_jatuh_tempo',
                'billing_list_detail.nilai_tagihan',
                'billing_list_detail.terbayar',
                DB::raw('billing_list_detail.nilai_tagihan - billing_list_detail.terbayar as nilai_piutang') ,
                'billing_list_detail.is_complete',
                'master_karyawan.nama_lengkap as sales_penanggung_jawab'
            )
            ->join('master_karyawan', 'master_karyawan.id', '=', 'billing_list_detail.sales_id')
            ->where('billing_header_id', $request->id_header);
        $page = $request->start > 29 ? "lanjut" : "awal";

        return DataTables::of($data)
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

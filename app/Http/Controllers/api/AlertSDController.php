<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\Datatables\Datatables;

class AlertSDController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now()->format('Y-m-d');
        if ($this->user_id == 1 || $this->user_id == 127 || $this->user_id == 152) {
            $orderSD = OrderDetail::select(
                'order_detail.no_order',
                'order_detail.no_quotation',
                'order_detail.nama_perusahaan',
                DB::raw('MAX(order_header.nama_pic_sampling) as nama_pic_sampling'),
                DB::raw('MAX(order_header.no_tlp_pic_sampling) as no_tlp_pic_sampling'),
                DB::raw('COUNT(order_detail.id) as total_sample'),
                DB::raw('MAX(order_detail.tanggal_sampling) as tanggal_sampling')
            )
                ->join('order_header', 'order_header.id', '=', 'order_detail.id_order_header')
                ->where('order_detail.is_active', true)
                ->where('order_detail.kategori_1', 'SD')
                ->whereNull('order_detail.tanggal_terima')
                ->where('order_detail.tanggal_sampling', '<=', $now)
                ->groupBY('order_detail.no_order', 'order_detail.no_quotation', 'order_detail.nama_perusahaan');
        } else {
            $orderSD = OrderDetail::select(
                'order_detail.no_order',
                'order_detail.no_quotation',
                'order_detail.nama_perusahaan',
                DB::raw('MAX(order_header.nama_pic_sampling) as nama_pic_sampling'),
                DB::raw('MAX(order_header.no_tlp_pic_sampling) as no_tlp_pic_sampling'),
                DB::raw('COUNT(order_detail.id) as total_sample'),
                DB::raw('MAX(order_detail.tanggal_sampling) as tanggal_sampling')
            )
                ->join('order_header', 'order_header.id', '=', 'order_detail.id_order_header')
                ->where('order_detail.is_active', true)
                ->where('order_detail.kategori_1', 'SD')
                ->whereNull('order_detail.tanggal_terima')
                ->where('order_detail.tanggal_sampling', '<=', $now)
                ->where('order_header.sales_id', $this->user_id)
                ->groupBY('order_detail.no_order', 'order_detail.no_quotation', 'order_detail.nama_perusahaan');

        }

        $orderSD = $orderSD->orderBy('tanggal_sampling', 'desc');
        return Datatables::of($orderSD)
            ->make(true);
    }

    public function getCountSample(Request $request)
    {
        $now = Carbon::now()->format('Y-m-d');
        if ($this->user_id == 1 || $this->user_id == 127 || $this->user_id == 152) {
            $orderSD = OrderDetail::select('order_detail.no_quotation', 'order_detail.tanggal_sampling', 'order_detail.nama_perusahaan')
                ->join('order_header', 'order_header.id', '=', 'order_detail.id_order_header')
                ->where('order_detail.is_active', true)
                ->where('order_detail.kategori_1', 'SD')
                ->where('order_detail.tanggal_sampling', '<=', $now)
                ->whereNull('order_detail.tanggal_terima');
        } else {
            $orderSD = OrderDetail::select('order_detail.no_quotation', 'order_detail.tanggal_sampling', 'order_detail.nama_perusahaan')
                ->join('order_header', 'order_header.id', '=', 'order_detail.id_order_header')
                ->where('order_detail.is_active', true)
                ->where('order_detail.kategori_1', 'SD')
                ->where('order_detail.tanggal_sampling', '<=', $now)
                ->where('order_header.sales_id', $this->user_id)
                ->whereNull('order_detail.tanggal_terima');

        }

        return response()->json([
            'message' => 'get count sample success',
            'data'    => $orderSD->count(),
        ]);
    }

    public function getDetail(Request $request)
    {
        $now = Carbon::now()->format('Y-m-d');

        $orderSD = OrderDetail::select(
            '*',
            DB::raw("SUBSTRING_INDEX(kategori_3, '-', -1) as kategori")
        )
            ->where('no_order', $request->no_order)
            ->where('is_active', true)
            ->where('order_detail.kategori_1', 'SD')
            ->where('order_detail.tanggal_sampling', '<=', $now)
            ->whereNull('order_detail.tanggal_terima');
        return Datatables::of($orderSD)->make(true);
    }

}

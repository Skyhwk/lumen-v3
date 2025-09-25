<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Datatables;

use App\Models\OrderDetail;
use App\Models\WsValueUdara;
use App\Models\KebisinganHeader;
use App\Models\LhpsKebisinganHeader;
use App\Models\LhpsKebisinganDetail;

class TqcKebisinganController extends Controller
{
    public function index()
    {
        $data = OrderDetail::select(
            'cfr',
            DB::raw('MAX(id) as max_id'),
            'nama_perusahaan',
            'no_quotation',
            'no_order',
            DB::raw('GROUP_CONCAT(DISTINCT no_sampel SEPARATOR ", ") as no_sampel'),
            DB::raw('GROUP_CONCAT(DISTINCT kategori_3 SEPARATOR ", ") as kategori_3'),
            DB::raw('GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ", ") as tanggal_sampling'),
            DB::raw('GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ", ") as tanggal_terima'),
            'kategori_1',
            'konsultan',
            'regulasi',
            'parameter',
        )
            ->where('is_active', true)
            ->where('status', 1)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', ["23-Kebisingan", "24-Kebisingan (24 Jam)", "25-Kebisingan (Indoor)", "26-Kualitas Udara Dalam Ruang"])
            ->groupBy('cfr', 'nama_perusahaan', 'no_quotation', 'no_order', 'kategori_1', 'konsultan', "regulasi", "parameter")
            ->orderBy('max_id', 'desc');

        return Datatables::of($data)
            ->filter(function ($query) {
                foreach (request('columns', []) as $col) {
                    $name = $col['data'] ?? null;
                    $search = $col['search']['value'] ?? null;

                    if ($search && in_array($name, [
                        'no_sampel',
                        'kategori_3',
                        'tanggal_sampling',
                        'tanggal_terima',
                    ])) {
                        $query->whereRaw("EXISTS (
                            SELECT 1 FROM order_detail od
                            WHERE od.cfr = order_detail.cfr
                            AND od.{$name} LIKE ?
                        )", ["%{$search}%"]);
                    }
                }
            })
            ->make(true);
    }

    public function getTrend(Request $request)
    {
        $orderDetails = OrderDetail::where('cfr', $request->cfr)
            ->where('status', 1)
            ->where('is_active', 1)
            ->get();

        $lhpsKebisinganHeader = LhpsKebisinganHeader::where('no_lhp', $request->cfr)->first();

        $data = [];
        foreach ($orderDetails as $orderDetail) {
            $kebisinganHeader = KebisinganHeader::where('no_sampel', $orderDetail->no_sampel)->first();
// dd($kebisinganHeader);
            $lhpsKebisinganHeader = LhpsKebisinganHeader::where('nama_pelanggan', $orderDetail->nama_perusahaan)->first();
            $lhpsKebisinganDetail = LhpsKebisinganDetail::where('lokasi_keterangan', $orderDetail->keterangan_1)
                ->pluck('hasil_uji')
                ->toArray();

            $data[] = [
                'no_sampel' => $orderDetail->no_sampel,
                'titik' => $orderDetail->keterangan_1,
                'history' => $lhpsKebisinganDetail,
                'hasil' => optional(WsValueUdara::where('no_sampel', $orderDetail->no_sampel)->orderByDesc('id')->first())->hasil1,
                'leq_ls' => optional($kebisinganHeader)->leq_ls,
                'leq_lm' => optional($kebisinganHeader)->leq_lm,
                // 'analyst' => optional($lhpsKebisinganHeader)->created_by,
                // 'approved_by' => optional($lhpsKebisinganHeader)->approved_by
                'analyst' => optional($kebisinganHeader)->created_by,
                'approved_by' => optional($kebisinganHeader)->approved_by
            ];
        }

        return response()->json([
            'data' => $data,
            'message' => 'Data retrieved successfully',
        ], 200);
    }

    public function handleApproveSelected(Request $request)
    {
        OrderDetail::whereIn('no_sampel', $request->no_sampel_list)->update(['status' => 2]);

        return response()->json([
            'message' => 'Data berhasil diapprove.',
            'success' => true,
        ], 200);
    }

    public function handleRejectSelected(Request $request)
    {
        OrderDetail::whereIn('no_sampel', $request->no_sampel_list)->update(['status' => 0]);

        return response()->json([
            'message' => 'Data berhasil direject.',
            'success' => true,
        ], 200);
    }
}

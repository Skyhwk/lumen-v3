<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LinkLhp;
use App\Models\OrderDetail;
use Illuminate\Http\Request;

use Datatables;
use App\Models\OrderHeader;
use App\Services\GroupedCfrByLhp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RekapOrderController extends Controller
{
    // public function index(Request $request)
    // {
    //     $rekapOrder = OrderHeader::where('is_active', true)
    //         ->with(['orderDetail.trackingSatu', 'orderDetail.trackingDua'])
    //         ->withCount('orderDetail')
    //         ->whereDate('tanggal_order', $request->tanggal_order);

    //     return Datatables::of($rekapOrder)
    //         ->filterColumn('tipe_quotation', function ($query, $keyword) {
    //             if (stripos('kontrak', $keyword) !== false) {
    //                 $query->where('no_document', 'like', '%QTC%');
    //             } elseif (stripos('non kontrak', $keyword) !== false || stripos('non-kontrak', $keyword) !== false) {
    //                 $query->where('no_document', 'not like', '%QTC%');
    //             }
    //         })->make(true);
    // }

    public function index(Request $request)
    {
        // Subquery link_lhp
        $linkLhpQuery = LinkLhp::query();

        // Query utama
        $rekapOrder = DB::table('order_detail')
            ->selectRaw('
                order_detail.no_order,
                order_detail.no_quotation,
                GROUP_CONCAT(DISTINCT order_detail.cfr SEPARATOR ",") as cfr,
                COUNT(DISTINCT order_detail.cfr) AS total_cfr,
                order_detail.nama_perusahaan,
                order_detail.konsultan,
                order_detail.periode,
                order_detail.kontrak,
                link_lhp.is_completed,
                link_lhp.jumlah_lhp_rilis,
                MIN(order_detail.tanggal_sampling) as tanggal_sampling_min,
                MAX(order_detail.tanggal_sampling) as tanggal_sampling_max
            ')
            ->where('order_detail.is_active', true);

        if ($request->filled('is_completed')) {

            // Ambil kolom lengkap dari link_lhp
            $linkLhpQuery = LinkLhp::select(
                'no_order',
                'is_completed',
                'jumlah_lhp_rilis',
                'periode'
            );

            $rekapOrder = $rekapOrder->leftJoinSub($linkLhpQuery, 'link_lhp', function ($join) {
                $join->on('order_detail.no_order', '=', 'link_lhp.no_order');
            });

            if ($request->is_completed == 'true' || $request->is_completed == 1) {

                // Completed hanya yang completed
                $rekapOrder->where('link_lhp.is_completed', true);

            } else {

                // NOT completed → boleh punya link_lhp atau tidak
                $rekapOrder->where(function ($q) {
                    $q->whereNull('link_lhp.is_completed')
                    ->orWhere('link_lhp.is_completed', false);
                });
            }
        }

        // Hanya yang sudah ada LHP rilis
        $rekapOrder->where(function ($q) {
            $q->whereNotNull('link_lhp.jumlah_lhp_rilis')
            ->where('link_lhp.jumlah_lhp_rilis', '>', 0);
        });

        /** 
         * ===============================
         *         FILTER LOGIC
         * ===============================
         * Jika kontrak = C → filter by PERIODE
         * Jika kontrak != C → filter by tanggal_sampling_min LIKE
         */

        $rekapOrder->when($request->filled('tanggal_sampling'), function ($q) use ($request) {

            $periode = $request->tanggal_sampling;

            $q->where(function ($sub) use ($periode) {

                // Kontrak = C → filter periode
                $sub->where(function ($f) use ($periode) {
                    $f->where('order_detail.kontrak', 'C')
                    ->where('order_detail.periode', $periode)
                    ->where('link_lhp.periode', $periode);
                });

                // Kontrak != C → filter tanggal_sampling_min (HARUS HAVING)
                $sub->orWhere(function ($f) use ($periode) {
                    $f->where('order_detail.kontrak', '!=', 'C');
                });

            });
        });

        // Grouping
        $rekapOrder->groupByRaw('
            order_detail.no_order,
            order_detail.no_quotation,
            order_detail.nama_perusahaan,
            order_detail.konsultan,
            order_detail.periode,
            order_detail.kontrak,
            link_lhp.is_completed,
            link_lhp.jumlah_lhp_rilis
        ');

        // HAVING untuk tanggal_sampling_min
        if ($request->filled('tanggal_sampling')) {
            $rekapOrder->having('tanggal_sampling_min', 'like', '%' . $request->tanggal_sampling . '%');
        }

        // ORDER BY menggunakan nama alias yang benar
        $rekapOrder->orderBy('tanggal_sampling_min', 'asc');

        return DataTables::of($rekapOrder)
            ->addColumn('cfr_list', function ($data) {
                return explode(',', $data->cfr);
            })
            ->addColumn('is_completed_auto', function ($data) {
                return (int) $data->jumlah_lhp_rilis === (int) $data->total_cfr;
            })
            ->filterColumn('no_order', function ($query, $keyword) {
                $query->where('order_detail.no_order', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_quotation', function ($query, $keyword) {
                $query->where('order_detail.no_quotation', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where('order_detail.nama_perusahaan', 'like', '%' . $keyword . '%')
                    ->orWhere('order_detail.konsultan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('tanggal_sampling_min', function ($query, $keyword) {
                $query->where('order_detail.tanggal_sampling', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('tipe_quotation', function ($query, $keyword) {
                if (stripos('kon', strtolower($keyword)) !== false) {
                    $query->where('order_detail.kontrak', 'C');
                } elseif (stripos('non', strtolower($keyword)) !== false || stripos('non-kontrak', $keyword) !== false) {
                    $query->where('order_detail.kontrak', '!=', 'C');
                }
            })
            ->make(true);
    }

    public function getGroupedCFR(Request $request)
    {
        $orderHeader = OrderHeader::where('is_active', true)
            ->where('no_order', $request->no_order)
            ->first();
        if (is_null($orderHeader)) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $groupedCFRs = (new GroupedCfrByLhp($orderHeader, $request->periode))->get();
        return response()->json([
            'no_order' => $orderHeader->no_order,
            'no_document' => $orderHeader->no_document,
            'nama_perusahaan' => $orderHeader->nama_perusahaan,
            'konsultan' => $orderHeader->konsultan,
            'tanggal_penawaran' => $orderHeader->tanggal_penawaran,
            'tanggal_order' => $orderHeader->tanggal_order,
            'groupedCFRs' => $groupedCFRs
        ], 200);
    }
}

<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{LinkLhp, MasterKaryawan, QuotationKontrakH, QuotationNonKontrak};
use App\Services\{GetAtasan, SendEmail};

class RekapHasilPengujianController extends Controller
{
    public function index()
    {
        $linkLhp = LinkLhp::with([
            'token',
            'order' => function ($query) {
                $query->select('id', 'no_order');
            },
            'order.orderDetail' => function ($query) {
                $query->select('id', 'id_order_header', 'tanggal_terima', 'tanggal_sampling', 'periode');
            }
        ])
            ->where('is_emailed', true)
            ->latest()
            ->get()
            ->map(function ($item) {
                if ($item->order && $item->order->orderDetail) {
                    $details = $item->order->orderDetail;

                    $item->order->order_detail = [
                        'periode'          => $details->pluck('periode')->filter()->values(),
                        'tanggal_sampling' => $details->pluck('tanggal_sampling')->filter()->values(),
                        'tanggal_terima'   => $details->pluck('tanggal_terima')->filter()->values(),
                    ];

                    // hilangkan data orderDetail mentah biar gak dobel
                    unset($item->order->orderDetail);
                }

                return $item;
            });

        return Datatables::of($linkLhp)->make(true);
    }


    public function reject(Request $request)
    {
        $linkLhp = LinkLhp::find($request->id);
        $linkLhp->update(['is_emailed' => false]);

        return response()->json(['message' => 'Data berhasil direject'], 200);
    }
}

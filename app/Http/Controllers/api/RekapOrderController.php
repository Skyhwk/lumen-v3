<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Datatables;
use App\Models\OrderHeader;

class RekapOrderController extends Controller
{
    public function index(Request $request)
    {
        $rekapOrder = OrderHeader::where('is_active', true)
            ->with(['orderDetail.trackingSatu', 'orderDetail.trackingDua'])
            ->withCount('orderDetail')
            ->whereDate('tanggal_order', $request->tanggal_order);

        return Datatables::of($rekapOrder)
            ->filterColumn('tipe_quotation', function ($query, $keyword) {
                if (stripos('kontrak', $keyword) !== false) {
                    $query->where('no_document', 'like', '%QTC%');
                } elseif (stripos('non kontrak', $keyword) !== false || stripos('non-kontrak', $keyword) !== false) {
                    $query->where('no_document', 'not like', '%QTC%');
                }
            })->make(true);
    }
}

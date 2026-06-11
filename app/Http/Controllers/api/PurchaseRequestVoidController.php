<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Services\KaryawanProfileService;
use DataTables;
use Illuminate\Http\Request;

class PurchaseRequestVoidController extends Controller
{
    public function index(Request $request)
    {
        $purchaseRequests = PurchaseRequest::with(['items', 'employee.jabatan', 'employee.divisi'])
            ->where('is_active', true)
            ->where('is_goods_voided', true)
            ->latest('goods_voided_at');

        return DataTables::of($purchaseRequests)
            ->addColumn('item_name', fn($row) => optional($row->items->first())->item_name)
            ->addColumn('quantity', fn($row) => optional($row->items->first())->quantity)
            ->addColumn('unit', fn($row) => optional($row->items->first())->unit)
            ->addColumn('requester_name', fn($row) => $row->created_by ?: '-')
            ->addColumn('requester_jabatan', fn($row) => KaryawanProfileService::resolveJabatan($row->employee))
            ->addColumn('requester_divisi', fn($row) => KaryawanProfileService::resolveDivisi($row->employee))
            ->filterColumn('item_name', function ($query, $keyword) {
                $query->whereHas('items', function ($sub) use ($keyword) {
                    $sub->where('item_name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('requester_name', function ($query, $keyword) {
                $query->where('created_by', 'like', "%{$keyword}%");
            })
            ->filterColumn('goods_void_note', function ($query, $keyword) {
                $query->where('goods_void_note', 'like', "%{$keyword}%");
            })
            ->filterColumn('goods_voided_by', function ($query, $keyword) {
                $query->where('goods_voided_by', 'like', "%{$keyword}%");
            })
            ->make(true);
    }
}

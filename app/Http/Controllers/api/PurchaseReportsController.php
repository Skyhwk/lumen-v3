<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrderDocument;
use App\Models\PurchaseRequest;
use DataTables;
use Illuminate\Http\Request;

class PurchaseReportsController extends Controller
{
    public function index(Request $request)
    {
        $purchaseRequests = PurchaseRequest::with(['items', 'employee'])
            ->where('is_active', true)
            ->where('finance_status', 'Distributed')
            ->latest();

        return DataTables::of($purchaseRequests)
            ->addColumn('item_name', fn($row) => optional($row->items->first())->item_name)
            ->addColumn('quantity', fn($row) => optional($row->items->first())->quantity)
            ->addColumn('unit', fn($row) => optional($row->items->first())->unit)
            ->addColumn('vendor_receipt_qty', fn($row) => $row->vendor_receipt_qty)
            ->addColumn('finance_display_status', fn() => 'Completed')
            ->make(true);
    }

    public function show(Request $request)
    {
        $purchaseRequest = PurchaseRequest::with(['items', 'employee'])->findOrFail($request->id);
        $poDocument = PurchaseOrderDocument::where('purchase_request_id', $purchaseRequest->id)
            ->latest('id')
            ->first();

        return response()->json([
            'data' => [
                'purchase_request' => $purchaseRequest,
                'po_document' => $poDocument,
            ],
            'message' => 'Detail laporan pembelian berhasil diambil',
        ], 200);
    }
}

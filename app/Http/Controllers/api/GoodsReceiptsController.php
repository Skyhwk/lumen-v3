<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrderDocument;
use App\Models\PurchaseRequest;
use App\Services\Notification;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GoodsReceiptsController extends Controller
{
    public function index(Request $request)
    {
        $scope = $request->input('scope', 'pending');

        $purchaseRequests = PurchaseRequest::with(['items', 'employee'])
            ->where('is_active', true)
            ->whereIn('status', ['Approved', 'Partially Approved'])
            ->latest();

        if ($scope === 'pending') {
            $purchaseRequests = $purchaseRequests->where('finance_status', 'Waiting Vendor Receipt');
        } else {
            $purchaseRequests = $purchaseRequests->where('finance_status', 'Waiting User Receipt');
        }

        return DataTables::of($purchaseRequests)
            ->addColumn('item_name', fn($row) => optional($row->items->first())->item_name)
            ->addColumn('quantity', fn($row) => optional($row->items->first())->quantity)
            ->addColumn('unit', fn($row) => optional($row->items->first())->unit)
            ->addColumn('finance_display_status', fn($row) => $this->resolveDisplayStatus($row))
            ->make(true);
    }

    public function saveVendorReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'vendor_delivery_note' => 'nullable|string|max:255',
            'vendor_receipt_qty' => 'required|numeric|min:0.01',
            'vendor_receipt_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::findOrFail($request->id);

        if ($purchaseRequest->finance_status !== 'Waiting Vendor Receipt') {
            return response()->json(['message' => 'Permintaan tidak dalam status menunggu tanda terima vendor'], 422);
        }

        $employee = $request->attributes->get('user')->karyawan;
        $now = date('Y-m-d H:i:s');

        $purchaseRequest->finance_status = 'Waiting User Receipt';
        $purchaseRequest->vendor_receipt_at = $now;
        $purchaseRequest->vendor_receipt_by = $this->karyawan;
        $purchaseRequest->vendor_delivery_note = $request->vendor_delivery_note;
        $purchaseRequest->vendor_receipt_qty = $request->vendor_receipt_qty;
        $purchaseRequest->vendor_receipt_note = $request->vendor_receipt_note;
        $purchaseRequest->save();

        $processorName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;

        Notification::where('nama_lengkap', $purchaseRequest->created_by)
            ->title('Barang dari Vendor Diterima!')
            ->message("Barang untuk permintaan {$purchaseRequest->request_number} (PO {$purchaseRequest->po_number}) telah diterima dari vendor oleh {$processorName} pada " . date('d-m-Y'))
            ->url('/request/purchase-requests')
            ->send();

        return response()->json(['message' => 'Tanda terima barang dari vendor berhasil disimpan'], 200);
    }

    public function saveUserReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'user_receipt_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::findOrFail($request->id);

        if ($purchaseRequest->finance_status !== 'Waiting User Receipt') {
            return response()->json(['message' => 'Permintaan tidak dalam status menunggu tanda terima user'], 422);
        }

        $employee = $request->attributes->get('user')->karyawan;
        $now = date('Y-m-d H:i:s');

        $purchaseRequest->finance_status = 'Distributed';
        $purchaseRequest->status = 'Done';
        $purchaseRequest->user_receipt_at = $now;
        $purchaseRequest->user_receipt_by = $this->karyawan;
        $purchaseRequest->user_receipt_note = $request->user_receipt_note;
        $purchaseRequest->completed_by = $this->karyawan;
        $purchaseRequest->completed_at = $now;
        $purchaseRequest->save();

        $processorName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;

        Notification::where('nama_lengkap', $purchaseRequest->created_by)
            ->title('Barang Telah Diterima!')
            ->message("Barang untuk permintaan {$purchaseRequest->request_number} telah diterima oleh {$processorName} pada " . date('d-m-Y'))
            ->url('/request/purchase-requests')
            ->send();

        return response()->json(['message' => 'Tanda terima barang untuk user berhasil disimpan'], 200);
    }

    private function resolveDisplayStatus($row): string
    {
        if ($row->finance_status === 'Waiting Vendor Receipt') {
            return 'Waiting Vendor Receipt';
        }

        if ($row->finance_status === 'Waiting User Receipt') {
            return 'Waiting User Receipt';
        }

        return $row->finance_status ?: '-';
    }
}

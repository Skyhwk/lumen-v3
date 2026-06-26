<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\{PurchaseOrderDocument, PurchaseRequest};
use App\Services\{KaryawanProfileService, Notification};
use DataTables;
use Illuminate\Http\Request;

class PurchaseRequestApprovalController extends Controller
{
    public function initialize(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;

        return response()->json([
            'data' => [
                'employee' => $employee,
                'can_approve_purchasing' => $this->isPurchasingApprover($employee),
            ],
            'message' => 'Purchase request approval initialized successfully',
        ], 200);
    }

    public function index(Request $request)
    {
        $scope = $request->input('scope', 'pending');

        $purchaseRequests = PurchaseRequest::with(['items', 'employee.jabatan', 'employee.divisi'])
            ->where('is_active', true)
            ->whereIn('status', ['Approved', 'Partially Approved'])
            ->latest();

        if ($scope === 'pending') {
            $purchaseRequests = $purchaseRequests->where('finance_status', 'Waiting to Delegate');
        } else {
            $purchaseRequests = $purchaseRequests->whereNotIn('finance_status', ['Waiting to Delegate', 'Rejected']);
        }

        return DataTables::of($purchaseRequests)
            ->addColumn('item_name', fn($row) => optional($row->items->first())->item_name)
            ->addColumn('quantity', fn($row) => optional($row->items->first())->quantity)
            ->addColumn('unit', fn($row) => optional($row->items->first())->unit)
            ->addColumn('requester_divisi', fn($row) => KaryawanProfileService::resolveDivisi($row->employee))
            ->addColumn('finance_display_status', fn($row) => $this->resolveFinanceDisplayStatus($row))
            ->filterColumn('item_name', function ($query, $keyword) {
                $query->whereHas('items', function ($sub) use ($keyword) {
                    $sub->where('item_name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('quantity', function ($query, $keyword) {
                $query->whereHas('items', function ($sub) use ($keyword) {
                    $sub->where('quantity', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('unit', function ($query, $keyword) {
                $query->whereHas('items', function ($sub) use ($keyword) {
                    $sub->where('unit', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('requester_divisi', fn($query, $keyword) => KaryawanProfileService::applyRequesterDivisiFilter($query, $keyword))
            ->make(true);
    }

    public function process(Request $request)
    {
        $purchaseRequest = PurchaseRequest::with('items')->findOrFail($request->data['parent_id']);
        $employee = $request->attributes->get('user')->karyawan;

        if (!$this->isPurchasingApprover($employee)) {
            return response()->json(['message' => 'Anda tidak memiliki otoritas untuk approval'], 403);
        }

        if ($purchaseRequest->finance_status !== 'Waiting to Delegate') {
            return response()->json(['message' => 'Permintaan tidak dapat diproses pada tahap ini'], 422);
        }

        $item = $purchaseRequest->items->first();

        if ($request->action === 'approve') {
            $hasActivePoDocuments = PurchaseOrderDocument::where('purchase_request_id', $purchaseRequest->id)
                ->where(function ($query) {
                    $query->where('is_voided', false)->orWhereNull('is_voided');
                })
                ->whereIn('po_status', ['draft', 'active'])
                ->exists();

            $purchaseRequest->finance_status = $hasActivePoDocuments
                ? 'On Process'
                : 'Waiting to Create PO';
            $purchaseRequest->delegated_by = $this->karyawan;
            $purchaseRequest->delegated_at = date('Y-m-d H:i:s');
            $purchaseRequest->rejection_finance_note = null;
            $purchaseRequest->rejected_finance_by = null;
            $purchaseRequest->rejected_finance_at = null;

            if ($item) {
                $item->rejection_finance_note = null;
                $item->rejected_finance_by = null;
                $item->rejected_finance_at = null;
                $item->save();
            }

            Notification::where('nama_lengkap', $purchaseRequest->created_by)
                ->title('Purchase Request Disetujui Purchasing')
                ->message("Purchase request anda telah disetujui purchasing oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/request/purchase-requests')
                ->send();

            Notification::whereIn('id_jabatan', [56, 57])
                ->title('Permintaan Pembelian Barang Siap Dibuat PO!')
                ->message("Terdapat Permintaan Pembelian Barang siap dibuat PO yang disetujui oleh {$employee->nama_lengkap} pada " . date('d-m-Y'))
                ->url('/finance/purchasing/purchase-order')
                ->send();
        }

        if ($request->action === 'reject') {
            $reason = $request->data['reason'] ?? '';

            if (!trim($reason)) {
                return response()->json(['message' => 'Alasan penolakan wajib diisi'], 422);
            }

            $purchaseRequest->finance_status = 'Rejected';
            $purchaseRequest->rejection_finance_note = $reason;
            $purchaseRequest->rejected_finance_by = $this->karyawan;
            $purchaseRequest->rejected_finance_at = date('Y-m-d H:i:s');

            if ($item) {
                $item->rejection_finance_note = $reason;
                $item->rejected_finance_by = $this->karyawan;
                $item->rejected_finance_at = date('Y-m-d H:i:s');
                $item->save();
            }

            Notification::where('nama_lengkap', $purchaseRequest->created_by)
                ->title('Permintaan Pembelian Barang Ditolak Purchasing!')
                ->message("Permintaan Pembelian Barang yang anda ajukan telah ditolak purchasing oleh {$employee->nama_lengkap} pada " . date('d-m-Y') . " dengan alasan: {$reason}")
                ->url('/request/purchase-requests')
                ->send();
        }

        $purchaseRequest->save();

        return response()->json(['message' => "Permintaan pembelian barang berhasil di{$request->action}"], 201);
    }

    private function isPurchasingApprover($employee): bool
    {
        // if (in_array((int) $employee->id_jabatan, [45, 48], true)) {
        //     return true;
        // }

        // return $employee->grade === 'MANAGER' && (int) $employee->id_department === 5;
        return $employee->grade === 'MANAGER';
    }

    private function resolveFinanceDisplayStatus($row): string
    {
        if ($row->finance_status === 'Rejected') {
            return 'Ditolak';
        }

        if ($row->finance_status === 'Waiting to Delegate') {
            return 'Menunggu Persetujuan';
        }

        if ($row->finance_status === 'Waiting to Create PO') {
            return 'Waiting to Create PO';
        }

        if ($row->finance_status === 'PO Created') {
            return 'PO Created';
        }

        if (in_array($row->finance_status, ['Waiting Process', 'On Process', 'Pending'])) {
            return $row->finance_status;
        }

        if ($row->finance_status === 'Distributed' || $row->status === 'Done') {
            return 'Distributed';
        }

        return $row->finance_status ?: '-';
    }
}

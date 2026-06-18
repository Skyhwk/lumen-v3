<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\{
    MasterCabang,
    MasterSupplier,
    PurchaseOrderDocument,
    PurchaseOrderDocumentRevision,
    PurchaseReceiptBatch,
    PurchaseRequest,
};
use App\Services\{GenerateQrDocumentPo, KaryawanProfileService, Notification, PurchaseReceiptService};
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseOrdersController extends Controller
{
    private const ROMAN_MONTHS = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV',
        '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
        '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII',
    ];

    public function index(Request $request)
    {
        $scope = $request->input('scope', 'pending');

        if ($scope === 'po_list') {
            return $this->indexPoDocuments();
        }

        $purchaseRequests = PurchaseRequest::with(['items', 'employee.jabatan', 'employee.divisi'])
            ->where('is_active', true)
            ->whereIn('status', ['Approved', 'Partially Approved'])
            ->where('finance_status', '!=', 'Rejected')
            ->latest();

        if ($scope === 'pending') {
            $purchaseRequests = $purchaseRequests
                ->whereRaw($this->remainingPoAllocationSql('>'))
                ->whereNotIn('finance_status', ['Rejected', 'Void', 'Distributed']);
        } else {
            $purchaseRequests = $purchaseRequests->where('finance_status', 'On Process');
        }

        return DataTables::of($purchaseRequests)
            ->addColumn('item_name', fn($row) => optional($row->items->first())->item_name)
            ->filterColumn('item_name', function($query, $keyword) {
                $query->whereHas('items', function($q) use ($keyword) {
                    $q->where('item_name', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('quantity', fn($row) => optional($row->items->first())->quantity)
            ->filterColumn('quantity', function($query, $keyword) {
                $query->whereHas('items', function($q) use ($keyword) {
                    $q->where('quantity', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('unit', fn($row) => optional($row->items->first())->unit)
            ->filterColumn('unit', function($query, $keyword) {
                $query->whereHas('items', function($q) use ($keyword) {
                    $q->where('unit', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('requester_divisi', fn($row) => KaryawanProfileService::resolveDivisi($row->employee))
            ->filterColumn('requester_divisi', function($query, $keyword) {
                $query->whereHas('employee.divisi', function($q) use ($keyword) {
                    $q->where('nama_divisi', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('finance_display_status', fn($row) => $this->resolveFinanceDisplayStatus($row))
            ->addColumn('allocated_po_qty', fn($row) => $this->getAllocatedPoQty($row))
            ->addColumn('remaining_po_qty', fn($row) => $this->getRemainingPoQty($row))
            ->filterColumn('remaining_po_qty', function($query, $keyword) {
                $itemQtySql = '(SELECT COALESCE(pri.quantity, 0) FROM purchase_request_items pri WHERE pri.purchase_request_id = purchase_requests.id ORDER BY pri.id ASC LIMIT 1)';
                $allocatedQtySql = '(SELECT COALESCE(SUM(pod.quantity), 0) FROM purchase_order_documents pod WHERE pod.purchase_request_id = purchase_requests.id AND (pod.is_voided = 0 OR pod.is_voided IS NULL) AND pod.po_status IN (\'draft\', \'active\'))';
                $query->whereRaw("({$itemQtySql} - {$allocatedQtySql}) like ?", ["%{$keyword}%"]);
            })
            ->addColumn('active_po_count', fn($row) => $this->countActivePoDocuments($row->id))
            ->addColumn('can_create_po', fn($row) => $this->canCreateAdditionalPo($row))
            ->addColumn('has_po', fn($row) => $this->countActivePoDocuments($row->id) > 0)
            ->make(true);
    }

    private function indexPoDocuments()
    {
        $poDocuments = PurchaseOrderDocument::with(['purchaseRequest.items', 'purchaseRequest.employee.jabatan', 'purchaseRequest.employee.divisi'])
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
            ->whereIn('po_status', ['draft', 'active'])
            ->latest('id');

        return DataTables::of($poDocuments)
            ->addColumn('purchase_request_id', fn($row) => $row->purchase_request_id)
            ->addColumn('request_number', fn($row) => optional($row->purchaseRequest)->request_number)
            ->filterColumn('request_number', function($query, $keyword) {
                $query->whereHas('purchaseRequest', function($q) use ($keyword) {
                    $q->where('request_number', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('item_name', fn($row) => $row->item_name ?: optional(optional($row->purchaseRequest)->items->first())->item_name)
            ->filterColumn('item_name', function($query, $keyword) {
                $query->where(function($q) use ($keyword) {
                    $q->where('purchase_order_documents.item_name', 'like', "%{$keyword}%")
                      ->orWhereHas('purchaseRequest.items', function($subQ) use ($keyword) {
                          $subQ->where('item_name', 'like', "%{$keyword}%");
                      });
                });
            })
            ->addColumn('pr_quantity', fn($row) => optional(optional($row->purchaseRequest)->items->first())->quantity)
            ->filterColumn('pr_quantity', function($query, $keyword) {
                $query->whereHas('purchaseRequest.items', function($q) use ($keyword) {
                    $q->where('quantity', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('unit', fn($row) => $row->unit ?: optional(optional($row->purchaseRequest)->items->first())->unit)
            ->filterColumn('unit', function($query, $keyword) {
                $query->where(function($q) use ($keyword) {
                    $q->where('purchase_order_documents.unit', 'like', "%{$keyword}%")
                      ->orWhereHas('purchaseRequest.items', function($subQ) use ($keyword) {
                          $subQ->where('unit', 'like', "%{$keyword}%");
                      });
                });
            })
            ->addColumn('purpose', fn($row) => optional($row->purchaseRequest)->purpose)
            ->filterColumn('purpose', function($query, $keyword) {
                $query->whereHas('purchaseRequest', function($q) use ($keyword) {
                    $q->where('purpose', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('priority', fn($row) => optional($row->purchaseRequest)->priority)
            ->filterColumn('priority', function($query, $keyword) {
                $query->whereHas('purchaseRequest', function($q) use ($keyword) {
                    $q->where('priority', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('created_by', fn($row) => optional($row->purchaseRequest)->created_by)
            ->filterColumn('created_by', function($query, $keyword) {
                $query->whereHas('purchaseRequest', function($q) use ($keyword) {
                    $q->where('created_by', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('requester_divisi', fn($row) => KaryawanProfileService::resolveDivisi(optional($row->purchaseRequest)->employee))
            ->filterColumn('requester_divisi', function($query, $keyword) {
                $query->whereHas('purchaseRequest.employee.divisi', function($q) use ($keyword) {
                    $q->where('nama_divisi', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('po_display_status', fn($row) => $this->resolvePoDisplayStatus($row))
            ->addColumn('revision_no', fn($row) => $row->revision_no ?? 1)
            ->filterColumn('revision_no', function($query, $keyword) {
                $query->whereHas('purchaseRequest', function($q) use ($keyword) {
                    $q->where('revision_no', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('can_update', fn($row) => $row->po_status === 'draft')
            ->addColumn('can_process', fn($row) => $row->po_status === 'draft')
            ->addColumn('can_revise', fn($row) => $this->canRevisePoDocument($row))
            ->addColumn('can_void', fn($row) => in_array($row->po_status, ['draft', 'active'], true))
            ->addColumn('po_created_at', fn($row) => $row->created_at)
            ->filterColumn('po_created_at', function($query, $keyword) {
                $query->where('purchase_order_documents.created_at', 'like', "%{$keyword}%");
            })
            ->make(true);
    }

    public function initialize(Request $request)
    {
        $employee = $request->attributes->get('user')->karyawan;
        $headOffice = MasterCabang::where('id', 1)->where('is_active', true)->first();

        $shippingAddress = $headOffice
            ? trim("PT INTI SURYA LABORATORIUM, {$headOffice->alamat_cabang}")
            : 'PT INTI SURYA LABORATORIUM';

        $defaultPic = trim(($employee->nama_lengkap ?? '') . ' / Purchasing', ' /');

        return response()->json([
            'data' => [
                'employee' => $employee,
                'default_pic' => $defaultPic,
                'approver' => [
                    'nama_lengkap' => $employee->nama_lengkap ?? '',
                    'jabatan' => $employee->jabatan ?? '',
                ],
                'shipping_address' => $shippingAddress,
                'suppliers' => MasterSupplier::where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'address', 'phone']),
            ],
            'message' => 'Purchase order initialized successfully',
        ], 200);
    }

    public function getSuppliers()
    {
        return response()->json(
            MasterSupplier::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'address', 'phone']),
            200
        );
    }

    public function getPo(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => 'required']);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with(['items', 'employee'])->findOrFail($request->id);
        $poDocument = $request->po_document_id
            ? $this->getPoDocumentById($purchaseRequest->id, $request->po_document_id)
            : $this->getActivePoDocument($purchaseRequest->id);

        if (!$poDocument) {
            return response()->json(['message' => 'Dokumen PO tidak ditemukan'], 404);
        }

        $revisions = PurchaseOrderDocumentRevision::where('purchase_order_document_id', $poDocument->id)
            ->orderByDesc('revision_no')
            ->get();

        return response()->json([
            'data' => [
                'purchase_request' => $purchaseRequest,
                'po_document' => $poDocument,
                'remaining_po_qty' => $this->getRemainingPoQty($purchaseRequest),
                'allocated_po_qty' => $this->getAllocatedPoQty($purchaseRequest),
                'revisions' => $revisions,
            ],
            'message' => 'Detail PO berhasil diambil',
        ], 200);
    }

    public function createPo(Request $request)
    {
        $validator = Validator::make($request->all(), $this->poFormRules());

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with('items')->findOrFail($request->id);

        if (!$this->canCreateAdditionalPo($purchaseRequest)) {
            return response()->json(['message' => 'Permintaan tidak dalam status siap dibuat PO'], 422);
        }

        $remainingQty = $this->getRemainingPoQty($purchaseRequest);

        if ((float) $request->quantity > $remainingQty) {
            return response()->json([
                'message' => "Qty PO tidak boleh melebihi sisa qty PR ({$remainingQty})",
            ], 422);
        }

        return $this->storePoDocument($request, $purchaseRequest, true);
    }

    public function updatePo(Request $request)
    {
        $validator = Validator::make($request->all(), $this->poFormRules());

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with('items')->findOrFail($request->id);

        if (!$request->po_document_id) {
            return response()->json(['message' => 'PO document wajib dipilih'], 422);
        }

        $poDocument = $this->getPoDocumentById($purchaseRequest->id, $request->po_document_id);

        if (!$poDocument || $poDocument->po_status !== 'draft') {
            return response()->json(['message' => 'PO hanya dapat diubah saat status draft (belum diproses)'], 422);
        }

        $remainingQty = $this->getRemainingPoQty($purchaseRequest) + (float) $poDocument->quantity;
        if ((float) $request->quantity > $remainingQty) {
            return response()->json([
                'message' => "Qty PO tidak boleh melebihi sisa qty PR ({$remainingQty})",
            ], 422);
        }

        return $this->storePoDocument($request, $purchaseRequest, false, $poDocument);
    }

    public function revisePo(Request $request)
    {
        $validator = Validator::make($request->all(), array_merge($this->poFormRules(), [
            'po_document_id' => 'required',
            'revision_reason' => 'required|string|max:1000',
        ]));

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with('items')->findOrFail($request->id);
        $poDocument = $this->getPoDocumentById($purchaseRequest->id, $request->po_document_id);

        if (!$poDocument || !$this->canRevisePoDocument($poDocument)) {
            return response()->json([
                'message' => 'PO hanya dapat direvisi setelah diproses dan sebelum ada penerimaan vendor',
            ], 422);
        }

        $remainingQty = $this->getRemainingPoQty($purchaseRequest) + (float) $poDocument->quantity;
        if ((float) $request->quantity > $remainingQty) {
            return response()->json([
                'message' => "Qty PO tidak boleh melebihi sisa qty PR ({$remainingQty})",
            ], 422);
        }

        return $this->storePoDocument($request, $purchaseRequest, false, $poDocument, true, trim($request->revision_reason));
    }

    public function voidPo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::findOrFail($request->id);

        if (!$request->po_document_id) {
            return response()->json(['message' => 'PO document wajib dipilih'], 422);
        }

        $poDocument = $this->getPoDocumentById($purchaseRequest->id, $request->po_document_id);

        if (!$poDocument || !in_array($poDocument->po_status, ['draft', 'active'], true)) {
            return response()->json(['message' => 'PO tidak dapat di-void'], 422);
        }

        if ($poDocument->po_status === 'active' && $this->hasVendorReceiptActivity($purchaseRequest)) {
            return response()->json(['message' => 'PO tidak dapat di-void setelah ada penerimaan vendor'], 422);
        }

        $employee = $request->attributes->get('user')->karyawan;
        $now = date('Y-m-d H:i:s');
        $voidFromStatus = $poDocument->po_status;
        $voidedPoNumber = $poDocument->po_number;

        DB::beginTransaction();

        try {
            $poDocument->is_voided = true;
            $poDocument->po_status = 'voided';
            $poDocument->voided_by = $this->karyawan;
            $poDocument->voided_at = $now;
            $poDocument->void_reason = trim($request->reason);
            $poDocument->void_from_finance_status = $voidFromStatus;
            $poDocument->save();

            $this->syncPurchaseRequestFromPos($purchaseRequest);
            $purchaseRequest->refresh();

            $voidedByName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;

            Notification::where('nama_lengkap', $purchaseRequest->created_by)
                ->title('Purchase Order Di-void')
                ->message("PO {$voidedPoNumber} untuk permintaan {$purchaseRequest->request_number} telah di-void oleh {$voidedByName} pada " . date('d-m-Y') . ". Alasan: {$poDocument->void_reason}")
                ->url('/request/purchase-requests')
                ->send();

            Notification::whereIn('id_jabatan', [56, 57])
                ->title('PO Di-void — Siap Dibuat Ulang')
                ->message("PO {$voidedPoNumber} ({$purchaseRequest->request_number}) di-void oleh {$voidedByName}. Permintaan kembali ke antrian pembuatan PO.")
                ->url('/finance/purchasing/purchase-order')
                ->send();

            DB::commit();

            return response()->json([
                'message' => 'Purchase Order berhasil di-void. Permintaan kembali ke antrian pembuatan PO.',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal void Purchase Order: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function processPo(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => 'required']);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with('items')->findOrFail($request->id);

        if (!$request->po_document_id) {
            return response()->json(['message' => 'PO document wajib dipilih'], 422);
        }

        $poDocument = $this->getPoDocumentById($purchaseRequest->id, $request->po_document_id);

        if (!$poDocument || $poDocument->po_status !== 'draft') {
            return response()->json(['message' => 'PO hanya dapat diproses saat status draft'], 422);
        }

        $employee = $request->attributes->get('user')->karyawan;
        $now = date('Y-m-d H:i:s');

        $poDocument->po_status = 'active';
        $poDocument->processed_by = $this->karyawan;
        $poDocument->processed_at = $now;
        $poDocument->save();

        $this->syncPurchaseRequestFromPos($purchaseRequest);
        $purchaseRequest->refresh();

        $processorName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;

        Notification::where('nama_lengkap', $purchaseRequest->created_by)
            ->title('Purchase Order Diproses!')
            ->message("PO {$poDocument->po_number} untuk permintaan {$purchaseRequest->request_number} telah diproses oleh {$processorName} dan menunggu penerimaan barang.")
            ->url('/request/purchase-requests')
            ->send();

        return response()->json([
            'message' => 'Purchase Order berhasil diproses. Data dipindahkan ke Goods Receipt.',
        ], 200);
    }

    private function poFormRules(): array
    {
        return [
            'id' => 'required',
            'supplier_name' => 'required|string|max:255',
            'supplier_address' => 'nullable|string',
            'supplier_id' => 'nullable|integer',
            'item_name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'ppn_percent' => 'nullable|numeric|min:0|max:100',
            'other_cost' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string',
            'phone_fax' => 'nullable|string|max:100',
            'pic' => 'nullable|string|max:255',
            'payment_term' => 'nullable|string|max:255',
            'item_status' => 'nullable|string|max:100',
            'delivery_time' => 'required|date',
            'delivery_type' => 'nullable|string|max:100',
            'offer_ref' => 'nullable|string|max:255',
            'shipping_address' => 'nullable|string',
            'approval_date' => 'required|date',
            'approval_name' => 'required|string|max:255',
            'approval_jabatan' => 'nullable|string|max:255',
        ];
    }

    private function storePoDocument(
        Request $request,
        PurchaseRequest $purchaseRequest,
        bool $isCreate,
        ?PurchaseOrderDocument $existingPo = null,
        bool $isRevision = false,
        ?string $revisionReason = null
    )
    {
        $employee = $request->attributes->get('user')->karyawan;
        $item = $purchaseRequest->items->first();

        $unitPrice = (float) $request->unit_price;
        $quantity = (float) $request->quantity;
        $discount = (float) ($request->discount ?? 0);
        $ppnPercent = (float) ($request->ppn_percent ?? 11);
        $otherCost = (float) ($request->other_cost ?? 0);

        $lineTotal = round($quantity * $unitPrice, 2);
        $subTotal = max(round($lineTotal - $discount, 2), 0);
        $ppnAmount = round($lineTotal * ($ppnPercent / 100), 2);
        $grandTotal = round($subTotal + $ppnAmount + $otherCost, 2);

        DB::beginTransaction();

        try {
            $supplierId = $this->resolveSupplier($request);
            $now = date('Y-m-d H:i:s');

            $poData = [
                'supplier_id' => $supplierId,
                'supplier_name' => trim($request->supplier_name),
                'supplier_address' => $request->supplier_address,
                'item_name' => trim($request->item_name),
                'quantity' => $quantity,
                'unit' => optional($item)->unit,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'discount' => $discount,
                'sub_total' => $subTotal,
                'ppn_percent' => $ppnPercent,
                'ppn_amount' => $ppnAmount,
                'other_cost' => $otherCost,
                'grand_total' => $grandTotal,
                'keterangan' => trim($request->keterangan ?? '') ?: trim(optional($item)->note ?? ''),
                'phone_fax' => $request->phone_fax ?: '021-5089-8988/89',
                'pic' => $request->pic,
                'payment_term' => $request->payment_term ?: 'Setelah Invoice diterima',
                'item_status' => $request->item_status ?: 'Ready Stok',
                'delivery_time' => $request->delivery_time,
                'delivery_type' => $request->delivery_type ?: 'Barang diambil',
                'offer_ref' => $request->offer_ref,
                'shipping_address' => $request->shipping_address,
                'approval_date' => $request->approval_date,
                'approval_name' => trim($request->approval_name),
                'approval_jabatan' => trim($request->approval_jabatan ?? ''),
            ];

            if ($isCreate) {
                $poNumber = $this->generatePoNumber();
                $poDocument = PurchaseOrderDocument::create(array_merge($poData, [
                    'purchase_request_id' => $purchaseRequest->id,
                    'po_date' => date('Y-m-d'),
                    'po_number' => $poNumber,
                    'invoice_number' => $poNumber,
                    'po_status' => 'draft',
                    'revision_no' => 1,
                    'created_by' => $this->karyawan,
                    'created_at' => $now,
                ]));

                $qrService = new GenerateQrDocumentPo();
                $qrFile = $qrService->insert('PURCHASE_ORDER', $poDocument, $this->karyawan);
                $poDocument->qr_file = $qrFile;
                $poDocument->save();

                $purchaseRequest->po_number = $poNumber;
                $purchaseRequest->po_created_by = $this->karyawan;
                $purchaseRequest->po_created_at = $now;
                $this->syncPurchaseRequestFromPos($purchaseRequest);
                $purchaseRequest->refresh();

                $processorName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;

                Notification::where('nama_lengkap', $purchaseRequest->created_by)
                    ->title('Permintaan Pembelian Barang Diproses!')
                    ->message("Permintaan pembelian barang {$purchaseRequest->request_number} sedang diproses. PO {$poNumber} telah dibuat oleh {$processorName} pada " . date('d-m-Y'))
                    ->url('/request/purchase-requests')
                    ->send();

                DB::commit();

                return response()->json([
                    'message' => 'Purchase Order berhasil dibuat. Permintaan pembelian sedang diproses.',
                    'data' => ['po_number' => $poNumber],
                ], 201);
            }

            if ($isRevision) {
                $this->snapshotPoRevision($existingPo, $revisionReason);
                $existingPo->revision_no = (int) ($existingPo->revision_no ?? 1) + 1;
            }

            $existingPo->fill($poData);
            $existingPo->save();

            $this->syncPurchaseRequestFromPos($purchaseRequest);

            DB::commit();

            return response()->json([
                'message' => $isRevision ? 'Purchase Order berhasil direvisi' : 'Purchase Order berhasil diperbarui',
                'data' => [
                    'po_number' => $existingPo->po_number,
                    'revision_no' => $existingPo->revision_no,
                ],
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyimpan Purchase Order: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::findOrFail($request->id);
        $poDocumentId = $request->input('po_document_id');
        $poDocument = $poDocumentId
            ? PurchaseOrderDocument::where('purchase_request_id', $purchaseRequest->id)->findOrFail($poDocumentId)
            : $this->getActivePoDocument($purchaseRequest->id);

        if (!$poDocument) {
            return response()->json(['message' => 'Dokumen PO tidak ditemukan'], 404);
        }

        $pdfString = $this->buildPdf($poDocument);

        return response()->json([
            'data' => base64_encode($pdfString),
            'message' => 'PDF generated successfully',
        ], 200);
    }

    private function resolveSupplier(Request $request): ?int
    {
        if ($request->supplier_id) {
            $supplier = MasterSupplier::where('id', $request->supplier_id)
                ->where('is_active', true)
                ->first();

            if ($supplier) {
                $supplier->address = $request->supplier_address ?: $supplier->address;
                $supplier->updated_at = date('Y-m-d H:i:s');
                $supplier->save();

                return (int) $supplier->id;
            }
        }

        $existing = MasterSupplier::where('name', trim($request->supplier_name))
            ->where('is_active', true)
            ->first();

        if ($existing) {
            $existing->address = $request->supplier_address ?: $existing->address;
            $existing->updated_at = date('Y-m-d H:i:s');
            $existing->save();

            return (int) $existing->id;
        }

        $created = MasterSupplier::create([
            'name' => trim($request->supplier_name),
            'address' => $request->supplier_address,
            'phone' => $request->phone_fax,
            'is_active' => true,
            'created_by' => $this->karyawan,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $created->id;
    }

    private function generatePoNumber(): string
    {
        $year = date('y');
        $month = self::ROMAN_MONTHS[date('m')];
        $prefix = "ISL/PO/{$year}-{$month}/";

        $latest = PurchaseOrderDocument::where('po_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(po_number, "/", -1) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;
        if ($latest) {
            $lastPart = substr($latest->po_number, strrpos($latest->po_number, '/') + 1);
            $nextNumber = (int) $lastPart + 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function buildPdf(PurchaseOrderDocument $poDocument): string
    {
        $purchaseRequest = PurchaseRequest::with('items')->find($poDocument->purchase_request_id);
        $itemNote = trim(optional(optional($purchaseRequest)->items->first())->note ?? '');
        $keterangan = trim($poDocument->keterangan ?? '') ?: $itemNote;

        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 10,
            'margin_bottom' => 15,
        ]);

        $poDateFormatted = Carbon::parse($poDocument->po_date)->locale('id')->isoFormat('DD MMMM YYYY');
        $approvalDateFormatted = Carbon::parse($poDocument->approval_date)->locale('id')->isoFormat('DD MMMM YYYY');
        $deliveryTimeFormatted = Carbon::parse($poDocument->delivery_time)->locale('id')->isoFormat('DD MMMM YYYY');
        $qrPath = $poDocument->qr_file
            ? public_path('qr_documents/' . $poDocument->qr_file . '.svg')
            : null;

        $html = view('pdf.purchase-order', compact(
            'poDocument',
            'poDateFormatted',
            'approvalDateFormatted',
            'deliveryTimeFormatted',
            'qrPath',
            'keterangan'
        ))->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function getActivePoDocumentsQuery(int $purchaseRequestId)
    {
        return PurchaseOrderDocument::where('purchase_request_id', $purchaseRequestId)
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
            ->whereIn('po_status', ['draft', 'active']);
    }

    private function getActivePoDocument(int $purchaseRequestId): ?PurchaseOrderDocument
    {
        return $this->getActivePoDocumentsQuery($purchaseRequestId)->latest('id')->first();
    }

    private function getPoDocumentById(int $purchaseRequestId, $poDocumentId): ?PurchaseOrderDocument
    {
        return PurchaseOrderDocument::where('purchase_request_id', $purchaseRequestId)
            ->where('id', $poDocumentId)
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
            ->first();
    }

    private function countActivePoDocuments(int $purchaseRequestId): int
    {
        return (int) $this->getActivePoDocumentsQuery($purchaseRequestId)->count();
    }

    private function getPrTargetQty(PurchaseRequest $purchaseRequest): float
    {
        if (!$purchaseRequest->relationLoaded('items')) {
            $purchaseRequest->load('items');
        }

        $itemQty = (float) optional($purchaseRequest->items->first())->quantity;

        if ($itemQty > 0) {
            return $itemQty;
        }

        return PurchaseReceiptService::resolveTargetQty($purchaseRequest);
    }

    private function getAllocatedPoQty(PurchaseRequest $purchaseRequest): float
    {
        return round((float) $this->getActivePoDocumentsQuery($purchaseRequest->id)->sum('quantity'), 2);
    }

    private function getRemainingPoQty(PurchaseRequest $purchaseRequest): float
    {
        $targetQty = $this->getPrTargetQty($purchaseRequest);

        return max(round($targetQty - $this->getAllocatedPoQty($purchaseRequest), 2), 0);
    }

    private function canCreateAdditionalPo(PurchaseRequest $purchaseRequest): bool
    {
        if (!$purchaseRequest->is_active || $this->getRemainingPoQty($purchaseRequest) <= 0) {
            return false;
        }

        if (in_array($purchaseRequest->finance_status, ['Rejected', 'Void', 'Distributed'], true)) {
            return false;
        }

        return in_array($purchaseRequest->status, ['Approved', 'Partially Approved'], true);
    }

    private function hasVendorReceiptActivity(PurchaseRequest $purchaseRequest): bool
    {
        return (float) ($purchaseRequest->vendor_received_total ?? 0) > 0
            || PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)
                ->whereNotNull('vendor_receipt_at')
                ->exists();
    }

    private function canRevisePoDocument(PurchaseOrderDocument $poDocument): bool
    {
        if ($poDocument->po_status !== 'active') {
            return false;
        }

        $purchaseRequest = PurchaseRequest::find($poDocument->purchase_request_id);

        return $purchaseRequest
            && in_array($purchaseRequest->finance_status, ['Waiting Vendor Receipt', 'On Process', 'Waiting User Receipt', 'Distributing'], true)
            && !$this->hasVendorReceiptActivity($purchaseRequest);
    }

    private function syncPurchaseRequestFromPos(PurchaseRequest $purchaseRequest): void
    {
        $activePos = $this->getActivePoDocumentsQuery($purchaseRequest->id)->get();
        $remainingQty = $this->getRemainingPoQty($purchaseRequest);
        $draftCount = $activePos->where('po_status', 'draft')->count();
        $activeCount = $activePos->where('po_status', 'active')->count();
        $latestPo = $activePos->sortByDesc('id')->first();

        if ($activePos->isEmpty()) {
            $purchaseRequest->finance_status = 'Waiting to Create PO';
            $purchaseRequest->po_number = null;
            $purchaseRequest->po_created_by = null;
            $purchaseRequest->po_created_at = null;
            $purchaseRequest->po_approved_by = null;
            $purchaseRequest->po_approved_at = null;
            $purchaseRequest->processed_by = null;
            $purchaseRequest->processed_at = null;
            $purchaseRequest->receipt_target_qty = null;
            $purchaseRequest->save();

            return;
        }

        $purchaseRequest->po_number = $latestPo->po_number ?? $purchaseRequest->po_number;
        $processedQty = round((float) $activePos->where('po_status', 'active')->sum('quantity'), 2);

        if ($activeCount > 0) {
            $purchaseRequest->receipt_target_qty = $processedQty;
            $purchaseRequest->po_approved_by = $latestPo->processed_by ?? $purchaseRequest->po_approved_by;
            $purchaseRequest->po_approved_at = $latestPo->processed_at ?? $purchaseRequest->po_approved_at;

            if (!$this->hasVendorReceiptActivity($purchaseRequest)) {
                $purchaseRequest->vendor_received_total = 0;
                $purchaseRequest->user_handed_total = 0;
                $purchaseRequest->user_confirmed_total = 0;
                $purchaseRequest->finance_status = 'Waiting Vendor Receipt';
            } else {
                PurchaseReceiptService::syncFinanceStatus($purchaseRequest);
            }
        } elseif ($draftCount > 0) {
            $purchaseRequest->finance_status = 'On Process';
        } elseif ($remainingQty > 0) {
            $purchaseRequest->finance_status = 'Waiting to Create PO';
        }

        $purchaseRequest->processed_by = $purchaseRequest->po_created_by;
        $purchaseRequest->processed_at = $purchaseRequest->po_created_at;
        $purchaseRequest->save();
    }

    private function remainingPoAllocationSql(string $operator): string
    {
        $itemQtySql = '(SELECT COALESCE(pri.quantity, 0) FROM purchase_request_items pri WHERE pri.purchase_request_id = purchase_requests.id ORDER BY pri.id ASC LIMIT 1)';
        $allocatedQtySql = '(SELECT COALESCE(SUM(pod.quantity), 0) FROM purchase_order_documents pod WHERE pod.purchase_request_id = purchase_requests.id AND (pod.is_voided = 0 OR pod.is_voided IS NULL) AND pod.po_status IN (\'draft\', \'active\'))';

        return "{$itemQtySql} - {$allocatedQtySql} {$operator} 0";
    }

    private function snapshotPoRevision(PurchaseOrderDocument $poDocument, ?string $reason): void
    {
        PurchaseOrderDocumentRevision::create([
            'purchase_order_document_id' => $poDocument->id,
            'purchase_request_id' => $poDocument->purchase_request_id,
            'revision_no' => (int) ($poDocument->revision_no ?? 1),
            'po_number' => $poDocument->po_number,
            'supplier_name' => $poDocument->supplier_name,
            'quantity' => $poDocument->quantity,
            'unit' => $poDocument->unit,
            'unit_price' => $poDocument->unit_price,
            'line_total' => $poDocument->line_total,
            'discount' => $poDocument->discount,
            'sub_total' => $poDocument->sub_total,
            'ppn_percent' => $poDocument->ppn_percent,
            'ppn_amount' => $poDocument->ppn_amount,
            'other_cost' => $poDocument->other_cost,
            'grand_total' => $poDocument->grand_total,
            'keterangan' => $poDocument->keterangan,
            'po_status' => $poDocument->po_status,
            'revision_reason' => $reason,
            'revised_by' => $this->karyawan,
            'revised_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function resolvePoDisplayStatus(PurchaseOrderDocument $poDocument): string
    {
        if ($poDocument->po_status === 'draft') {
            return 'On Process';
        }

        if ($poDocument->po_status === 'active') {
            return 'Waiting Vendor Receipt';
        }

        return $poDocument->po_status ?: '-';
    }

    private function resolveFinanceDisplayStatus($row): string
    {
        if ($row->finance_status === 'Waiting to Create PO') {
            return 'Waiting to Create PO';
        }

        if ($row->finance_status === 'PO Created') {
            return 'PO Created';
        }

        if ($row->finance_status === 'On Process') {
            return 'On Process';
        }

        if ($row->finance_status === 'Waiting Vendor Receipt') {
            return 'Waiting Vendor Receipt';
        }

        if ($row->finance_status === 'Waiting User Receipt') {
            return 'Waiting User Receipt';
        }

        if ($row->finance_status === 'Distributing') {
            return 'Distributing';
        }

        if (in_array($row->finance_status, ['Waiting Process', 'Pending'])) {
            return $row->finance_status;
        }

        if ($row->finance_status === 'Distributed' || $row->status === 'Done') {
            return 'Distributed';
        }

        return $row->finance_status ?: '-';
    }
}
<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Models\PurchaseOrderDocument;
use App\Models\PurchaseReceiptBatch;
use App\Models\PurchaseRequest;
use App\Services\{KaryawanProfileService, Notification, PurchaseReceiptService};
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GoodsReceiptsController extends Controller
{
    private const MAX_ATTACHMENT_SIZE = 2097152; // 2MB
    private const ATTACHMENT_DIR = 'goods-receipt/vendor';

    private const ROMAN_MONTHS = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV',
        '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
        '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII',
    ];

    public function index(Request $request)
    {
        $scope = $request->input('scope', 'pending');

        $poVendorReceivedSql = '(SELECT COALESCE(SUM(prb.vendor_receipt_qty), 0) FROM purchase_receipt_batches prb WHERE prb.purchase_order_document_id = purchase_order_documents.id)';
        $poUserConfirmedSql = '(SELECT COALESCE(SUM(prb.user_handover_qty), 0) FROM purchase_receipt_batches prb WHERE prb.purchase_order_document_id = purchase_order_documents.id AND prb.completed_at IS NOT NULL)';

        $poDocuments = PurchaseOrderDocument::with(['purchaseRequest.items', 'purchaseRequest.employee.jabatan', 'purchaseRequest.employee.divisi'])
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
            ->where('po_status', 'active')
            ->whereHas('purchaseRequest', function ($query) {
                $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->where('is_goods_voided', false)->orWhereNull('is_goods_voided');
                    })
                    ->where(function ($q) {
                        $q->whereIn('status', ['Approved', 'Partially Approved'])
                            ->orWhere(function ($sub) {
                                $sub->where('status', 'Done')
                                    ->whereIn('finance_status', ['Waiting Vendor Receipt', 'Waiting User Receipt', 'Distributing']);
                            });
                    })
                    ->whereIn('finance_status', ['Waiting Vendor Receipt', 'Waiting User Receipt', 'Distributing']);
            })
            ->latest('processed_at')
            ->latest('id');

        if ($scope === 'pending') {
            $poDocuments = $poDocuments->whereRaw("{$poVendorReceivedSql} < purchase_order_documents.quantity");
        } else {
            $poDocuments = $poDocuments
                ->whereRaw("{$poVendorReceivedSql} > 0")
                ->whereRaw("({$poUserConfirmedSql}) < purchase_order_documents.quantity")
                ->where(function ($query) {
                    $query->whereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('purchase_receipt_batches as prb')
                            ->whereColumn('prb.purchase_order_document_id', 'purchase_order_documents.id')
                            ->whereNull('prb.handover_number')
                            ->whereNotNull('prb.vendor_receipt_at');
                    })->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('purchase_receipt_batches as prb')
                            ->whereColumn('prb.purchase_order_document_id', 'purchase_order_documents.id')
                            ->whereNotNull('prb.handover_number')
                            ->whereNull('prb.completed_at');
                    });
                });
        }

        return DataTables::of($poDocuments)
            ->addColumn('purchase_request_id', fn($row) => $row->purchase_request_id)
            ->addColumn('po_document_id', fn($row) => $row->id)
            ->addColumn('request_number', fn($row) => optional($row->purchaseRequest)->request_number)
            ->filterColumn('request_number', function ($query, $keyword) {
                $query->whereHas('purchaseRequest', function ($q) use ($keyword) {
                    $q->where('request_number', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('po_number', fn($row) => $row->po_number)
            ->filterColumn('po_number', function ($query, $keyword) {
                $query->where('purchase_order_documents.po_number', 'like', "%{$keyword}%");
            })
            ->addColumn('item_name', fn($row) => $row->item_name ?: optional(optional($row->purchaseRequest)->items->first())->item_name)
            ->filterColumn('item_name', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('purchase_order_documents.item_name', 'like', "%{$keyword}%")
                        ->orWhereHas('purchaseRequest.items', function ($subQ) use ($keyword) {
                            $subQ->where('item_name', 'like', "%{$keyword}%");
                        });
                });
            })
            ->addColumn('quantity', fn($row) => (float) $row->quantity)
            ->filterColumn('quantity', function ($query, $keyword) {
                $query->where('purchase_order_documents.quantity', 'like', "%{$keyword}%");
            })
            ->addColumn('unit', fn($row) => $row->unit ?: optional(optional($row->purchaseRequest)->items->first())->unit)
            ->filterColumn('unit', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('purchase_order_documents.unit', 'like', "%{$keyword}%")
                        ->orWhereHas('purchaseRequest.items', function ($subQ) use ($keyword) {
                            $subQ->where('unit', 'like', "%{$keyword}%");
                        });
                });
            })
            ->addColumn('vendor_received_total', fn($row) => PurchaseReceiptService::getPoVendorReceivedTotal($row->id))
            ->addColumn('remaining_vendor_qty', fn($row) => PurchaseReceiptService::getPoRemainingVendorQty($row))
            ->addColumn('user_confirmed_total', fn($row) => PurchaseReceiptService::getPoUserConfirmedTotal($row->id))
            ->addColumn('pending_user_handover_count', fn($row) => PurchaseReceiptService::countPendingUserHandoverBatchesForPo($row->id))
            ->addColumn('can_create_user_receipt', fn($row) => PurchaseReceiptService::hasPendingUserHandoverBatchForPo($row->id))
            ->addColumn('handover_count', fn($row) => PurchaseReceiptService::countHandoverBatchesForPo($row->id))
            ->addColumn('created_by', fn($row) => optional($row->purchaseRequest)->created_by)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->whereHas('purchaseRequest', function ($q) use ($keyword) {
                    $q->where('created_by', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('requester_jabatan', fn($row) => KaryawanProfileService::resolveJabatan(optional($row->purchaseRequest)->employee))
            ->addColumn('requester_divisi', fn($row) => KaryawanProfileService::resolveDivisi(optional($row->purchaseRequest)->employee))
            ->filterColumn('requester_divisi', fn($query, $keyword) => $this->applyRequesterDivisiFilter($query, $keyword))
            ->addColumn('finance_display_status', fn($row) => $this->resolvePoRowDisplayStatus($row, $scope))
            ->addColumn('po_approved_at', fn($row) => $row->processed_at)
            ->filterColumn('po_approved_at', function ($query, $keyword) {
                $query->where('purchase_order_documents.processed_at', 'like', "%{$keyword}%");
            })
            ->addColumn('vendor_receipt_at', fn($row) => PurchaseReceiptService::getLatestVendorReceiptAtForPo($row->id))
            ->filterColumn('vendor_receipt_at', function ($query, $keyword) {
                $query->whereExists(function ($sub) use ($keyword) {
                    $sub->select(DB::raw(1))
                        ->from('purchase_receipt_batches as prb')
                        ->whereColumn('prb.purchase_order_document_id', 'purchase_order_documents.id')
                        ->where('prb.vendor_receipt_at', 'like', "%{$keyword}%");
                });
            })
            ->make(true);
    }

    public function getUserReceipt(Request $request)
    {
        $purchaseRequest = PurchaseRequest::with(['items', 'employee'])->findOrFail($request->id);
        $poDocument = $request->po_document_id
            ? PurchaseReceiptService::findActivePoDocument($purchaseRequest->id, (int) $request->po_document_id)
            : null;

        if ($poDocument && PurchaseReceiptService::getPoVendorReceivedTotal($poDocument->id) <= 0) {
            return response()->json(['message' => 'Tanda terima user hanya dapat dibuat setelah ada tanda terima vendor untuk PO ini'], 422);
        }

        if (!$poDocument) {
            if (!in_array($purchaseRequest->finance_status, ['Waiting User Receipt', 'Distributing'])) {
                return response()->json(['message' => 'Permintaan tidak dalam status menunggu serah terima ke user'], 422);
            }

            if ((float) ($purchaseRequest->vendor_received_total ?? 0) <= 0) {
                return response()->json(['message' => 'Tanda terima user hanya dapat dibuat setelah ada tanda terima vendor'], 422);
            }
        }

        $recipient = $this->findKaryawanByName($purchaseRequest->created_by);
        $item = $purchaseRequest->items->first();
        $targetQty = $poDocument
            ? (float) $poDocument->quantity
            : PurchaseReceiptService::resolveTargetQty($purchaseRequest);
        $vendorReceivedTotal = $poDocument
            ? PurchaseReceiptService::getPoVendorReceivedTotal($poDocument->id)
            : (float) ($purchaseRequest->vendor_received_total ?? 0);
        $userConfirmedTotal = $poDocument
            ? PurchaseReceiptService::getPoUserConfirmedTotal($poDocument->id)
            : (float) ($purchaseRequest->user_confirmed_total ?? 0);

        $batchQuery = PurchaseReceiptBatch::with('purchaseOrderDocument')
            ->where('purchase_request_id', $purchaseRequest->id);

        if ($poDocument) {
            $batchQuery->where('purchase_order_document_id', $poDocument->id);
        }

        $pendingBatches = (clone $batchQuery)
            ->whereNull('handover_number')
            ->whereNotNull('vendor_receipt_at')
            ->orderBy('batch_no')
            ->get()
            ->map(fn($batch) => PurchaseReceiptService::formatBatch($batch, self::ATTACHMENT_DIR));

        $handoverHistory = (clone $batchQuery)
            ->whereNotNull('handover_number')
            ->orderByDesc('batch_no')
            ->get()
            ->map(fn($batch) => PurchaseReceiptService::formatBatch($batch, self::ATTACHMENT_DIR));

        if ($pendingBatches->isEmpty() && $handoverHistory->isEmpty()) {
            return response()->json(['message' => 'Belum ada data serah terima user'], 422);
        }

        return response()->json([
            'data' => [
                'id' => $purchaseRequest->id,
                'po_document_id' => $poDocument->id ?? null,
                'po_number' => $poDocument->po_number ?? $purchaseRequest->po_number,
                'request_number' => $purchaseRequest->request_number,
                'purpose' => $purchaseRequest->purpose,
                'target_qty' => $targetQty,
                'vendor_received_total' => $vendorReceivedTotal,
                'user_confirmed_total' => $userConfirmedTotal,
                'remaining_qty' => max(round($targetQty - $userConfirmedTotal, 2), 0),
                'can_create_user_receipt' => $poDocument
                    ? PurchaseReceiptService::hasPendingUserHandoverBatchForPo($poDocument->id)
                    : PurchaseReceiptService::hasPendingUserHandoverBatch($purchaseRequest),
                'recipient' => [
                    'nama_lengkap' => $purchaseRequest->created_by,
                    'jabatan' => $this->resolveKaryawanJabatan($recipient),
                    'divisi' => $this->resolveKaryawanDivisi($recipient),
                ],
                'item' => [
                    'item_code' => $item->item_code ?? '',
                    'item_name' => $item->item_name ?? '',
                    'quantity' => $targetQty,
                    'unit' => $item->unit ?? '',
                    'note' => $item->note ?? '',
                ],
                'pending_batches' => $pendingBatches,
                'handover_history' => $handoverHistory,
            ],
            'message' => 'Data serah terima user berhasil dimuat',
        ], 200);
    }

    public function getVendorReceipt(Request $request)
    {
        $purchaseRequest = PurchaseRequest::with('items')->findOrFail($request->id);

        if (!in_array($purchaseRequest->finance_status, ['Waiting Vendor Receipt', 'Waiting User Receipt', 'Distributing'])) {
            return response()->json(['message' => 'Data tanda terima vendor tidak dapat diakses'], 422);
        }

        $poDocument = $request->po_document_id
            ? PurchaseReceiptService::findActivePoDocument($purchaseRequest->id, (int) $request->po_document_id)
            : null;

        if ($request->po_document_id && !$poDocument) {
            return response()->json(['message' => 'PO tidak ditemukan atau belum diproses'], 422);
        }

        if ($poDocument) {
            $poProgress = PurchaseReceiptService::formatPoProgress($poDocument);

            return response()->json([
                'data' => [
                    'id' => $purchaseRequest->id,
                    'po_document_id' => $poDocument->id,
                    'po_number' => $poDocument->po_number,
                    'item_name' => $poDocument->item_name ?: optional($purchaseRequest->items->first())->item_name,
                    'quantity' => $poProgress['quantity'],
                    'vendor_received_total' => $poProgress['vendor_received_total'],
                    'remaining_vendor_qty' => $poProgress['remaining_vendor_qty'],
                    'finance_status' => $purchaseRequest->finance_status,
                ],
                'message' => 'Data tanda terima vendor berhasil dimuat',
            ], 200);
        }

        $activePos = PurchaseReceiptService::formatActivePosProgress($purchaseRequest);
        $receivablePos = array_values(array_filter($activePos, fn($po) => ($po['remaining_vendor_qty'] ?? 0) > 0));

        return response()->json([
            'data' => [
                'id' => $purchaseRequest->id,
                'po_number' => PurchaseReceiptService::formatPoNumbersDisplay($purchaseRequest),
                'item_name' => optional($purchaseRequest->items->first())->item_name,
                'quantity' => PurchaseReceiptService::resolveTargetQty($purchaseRequest),
                'vendor_received_total' => $purchaseRequest->vendor_received_total ?? 0,
                'remaining_vendor_qty' => PurchaseReceiptService::getRemainingVendorQty($purchaseRequest),
                'active_pos' => $activePos,
                'receivable_pos' => $receivablePos,
                'finance_status' => $purchaseRequest->finance_status,
            ],
            'message' => 'Data tanda terima vendor berhasil dimuat',
        ], 200);
    }

    public function saveVendorReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'po_document_id' => 'required|integer',
            'vendor_delivery_note' => 'nullable|string|max:255',
            'vendor_receipt_qty' => 'required|numeric|min:0.01',
            'vendor_receipt_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::findOrFail($request->id);

        if (!in_array($purchaseRequest->finance_status, ['Waiting Vendor Receipt', 'Waiting User Receipt', 'Distributing'])) {
            return response()->json(['message' => 'Permintaan tidak dalam status penerimaan vendor'], 422);
        }

        $poDocument = PurchaseReceiptService::findActivePoDocument($purchaseRequest->id, (int) $request->po_document_id);

        if (!$poDocument) {
            return response()->json(['message' => 'PO tidak ditemukan atau belum diproses'], 422);
        }

        $targetQty = (float) $poDocument->quantity;
        $remainingQty = PurchaseReceiptService::getPoRemainingVendorQty($poDocument);
        $receiptQty = (float) $request->vendor_receipt_qty;

        if ($remainingQty <= 0) {
            return response()->json(['message' => "PO {$poDocument->po_number} sudah diterima penuh"], 422);
        }

        if ($receiptQty > $remainingQty) {
            return response()->json([
                'message' => "Qty diterima melebihi sisa PO {$poDocument->po_number} ({$remainingQty})",
            ], 422);
        }

        $attachments = $this->handleAttachments($request, null);
        if ($attachments === false) {
            return response()->json(['message' => 'Lampiran harus berupa gambar dengan ukuran maksimal 2MB per file'], 422);
        }

        $employee = $request->attributes->get('user')->karyawan;
        $now = date('Y-m-d H:i:s');

        PurchaseReceiptBatch::create([
            'purchase_request_id' => $purchaseRequest->id,
            'purchase_order_document_id' => $poDocument->id,
            'batch_no' => PurchaseReceiptService::getNextBatchNo($purchaseRequest->id),
            'vendor_receipt_qty' => $receiptQty,
            'vendor_delivery_note' => $request->vendor_delivery_note,
            'vendor_receipt_note' => $request->vendor_receipt_note,
            'vendor_receipt_attachments' => $this->encodeAttachments($attachments),
            'vendor_receipt_by' => $this->karyawan,
            'vendor_receipt_at' => $now,
            'created_at' => $now,
        ]);

        if (empty($purchaseRequest->receipt_target_qty)) {
            $purchaseRequest->receipt_target_qty = PurchaseReceiptService::resolveTargetQty($purchaseRequest);
        }

        PurchaseReceiptService::refreshTotals($purchaseRequest);

        $processorName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;
        $poVendorTotal = PurchaseReceiptService::getPoVendorReceivedTotal($poDocument->id);
        $isPartial = $poVendorTotal < $targetQty;
        $partialLabel = $isPartial ? ' (parsial)' : '';

        Notification::where('nama_lengkap', $purchaseRequest->created_by)
            ->title('Barang dari Vendor Diterima' . $partialLabel . '!')
            ->message("Barang sebanyak {$receiptQty} untuk permintaan {$purchaseRequest->request_number} (PO {$poDocument->po_number}) telah diterima dari vendor oleh {$processorName} pada " . date('d-m-Y'))
            ->url('/request/purchase-requests')
            ->send();

        return response()->json([
            'message' => $isPartial
                ? 'Tanda terima parsial vendor berhasil disimpan. Sisa barang dapat diterima pada pengiriman berikutnya.'
                : 'Tanda terima barang dari vendor berhasil disimpan',
            'data' => [
                'vendor_received_total' => $purchaseRequest->vendor_received_total,
                'remaining_vendor_qty' => PurchaseReceiptService::getRemainingVendorQty($purchaseRequest),
                'po_vendor_received_total' => $poVendorTotal,
                'po_remaining_vendor_qty' => PurchaseReceiptService::getPoRemainingVendorQty($poDocument),
            ],
        ], 200);
    }

    public function updateVendorReceipt(Request $request)
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

        if ($purchaseRequest->finance_status !== 'Waiting User Receipt') {
            return response()->json(['message' => 'Tanda terima vendor hanya dapat diubah sebelum serah terima ke user'], 422);
        }

        $attachments = $this->handleAttachments($request, $purchaseRequest->vendor_receipt_attachments);
        if ($attachments === false) {
            return response()->json(['message' => 'Lampiran harus berupa gambar dengan ukuran maksimal 2MB per file'], 422);
        }

        $purchaseRequest->vendor_delivery_note = $request->vendor_delivery_note;
        $purchaseRequest->vendor_receipt_qty = $request->vendor_receipt_qty;
        $purchaseRequest->vendor_receipt_note = $request->vendor_receipt_note;
        $purchaseRequest->vendor_receipt_attachments = $this->encodeAttachments($attachments);
        $purchaseRequest->save();

        return response()->json(['message' => 'Tanda terima vendor berhasil diperbarui'], 200);
    }

    public function saveUserReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'batch_id' => 'required|integer',
            'user_receipt_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $purchaseRequest = PurchaseRequest::with(['items'])->findOrFail($request->id);

        if (!in_array($purchaseRequest->finance_status, ['Waiting User Receipt', 'Distributing'])) {
            return response()->json(['message' => 'Permintaan tidak dalam status menunggu serah terima ke user'], 422);
        }

        if ((float) ($purchaseRequest->vendor_received_total ?? 0) <= 0) {
            return response()->json(['message' => 'Tanda terima user hanya dapat dibuat setelah ada tanda terima vendor'], 422);
        }

        if (!PurchaseReceiptService::hasPendingUserHandoverBatch($purchaseRequest)) {
            return response()->json(['message' => 'Belum ada batch tanda terima vendor yang siap diserahkan ke user'], 422);
        }

        $batch = PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)
            ->where('id', $request->batch_id)
            ->whereNull('handover_number')
            ->whereNotNull('vendor_receipt_at')
            ->first();

        if (!$batch) {
            return response()->json(['message' => 'Batch tanda terima vendor tidak ditemukan atau sudah diserahkan'], 422);
        }

        $employee = $request->attributes->get('user')->karyawan;
        $now = date('Y-m-d H:i:s');

        $batch->handover_number = $this->generateHandoverNumber();
        $batch->user_handover_qty = $batch->vendor_receipt_qty;
        $batch->user_receipt_at = $now;
        $batch->user_receipt_by = $this->karyawan;
        $batch->user_receipt_note = $request->user_receipt_note;
        $batch->save();

        PurchaseReceiptService::refreshTotals($purchaseRequest);

        $pdfContent = $this->buildHandoverPdf($purchaseRequest, $batch, $employee);
        $processorName = ($employee && $employee->nama_lengkap) ? $employee->nama_lengkap : $this->karyawan;
        $targetQty = PurchaseReceiptService::resolveTargetQty($purchaseRequest);
        $isPartial = (float) $purchaseRequest->user_confirmed_total < $targetQty
            || (float) $purchaseRequest->vendor_received_total < $targetQty;

        Notification::where('nama_lengkap', $purchaseRequest->created_by)
            ->title('Purchase Request Ready' . ($isPartial ? ' (Parsial)' : '') . '!')
            ->message("Purchase request {$purchaseRequest->request_number} sebanyak {$batch->user_handover_qty} siap diserahkan oleh {$processorName} ({$batch->handover_number}). Silakan konfirmasi penerimaan barang.")
            ->url('/request/purchase-requests')
            ->send();

        return response()->json([
            'message' => $isPartial
                ? 'Dokumen serah terima parsial berhasil dibuat'
                : 'Dokumen serah terima berhasil dibuat',
            'data' => [
                'handover_number' => $batch->handover_number,
                'batch_id' => $batch->id,
                'pdf' => base64_encode($pdfContent),
            ],
        ], 200);
    }

    public function exportHandoverPdf(Request $request)
    {
        $purchaseRequest = PurchaseRequest::with(['items'])->findOrFail($request->id);

        $batch = $request->batch_id
            ? PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)->findOrFail($request->batch_id)
            : PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)
                ->whereNotNull('handover_number')
                ->latest('id')
                ->first();

        if (!$batch || empty($batch->handover_number)) {
            return response()->json(['message' => 'Dokumen serah terima belum tersedia'], 422);
        }

        $handedBy = MasterKaryawan::with('jabatan')
            ->where('nama_lengkap', $batch->user_receipt_by)
            ->where('is_active', true)
            ->first();

        $pdfContent = $this->buildHandoverPdf($purchaseRequest, $batch, $handedBy);

        return response()->json([
            'message' => 'PDF serah terima berhasil digenerate',
            'data' => base64_encode($pdfContent),
        ], 200);
    }

    private function generateHandoverNumber(): string
    {
        $year = date('y');
        $month = self::ROMAN_MONTHS[date('m')];
        $prefix = "ISL/PB/{$year}-{$month}/";

        $latestBatch = PurchaseReceiptBatch::where('handover_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(handover_number, "/", -1) AS UNSIGNED) DESC')
            ->first();

        $latestPr = PurchaseRequest::where('handover_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(handover_number, "/", -1) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;
        foreach ([$latestBatch, $latestPr] as $latest) {
            if ($latest && !empty($latest->handover_number)) {
                $lastPart = substr($latest->handover_number, strrpos($latest->handover_number, '/') + 1);
                $nextNumber = max($nextNumber, (int) $lastPart + 1);
            }
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function buildHandoverPdf(PurchaseRequest $purchaseRequest, PurchaseReceiptBatch $batch, $handedByEmployee): string
    {
        if (!$batch->relationLoaded('purchaseOrderDocument') && $batch->purchase_order_document_id) {
            $batch->load('purchaseOrderDocument');
        }

        $item = $purchaseRequest->items->first();
        $recipient = $this->findKaryawanByName($purchaseRequest->created_by);

        $handoverDate = $batch->user_receipt_at ?: date('Y-m-d H:i:s');
        $handoverDateFormatted = Carbon::parse($handoverDate)->locale('id')->isoFormat('D MMMM YYYY H:mm');

        $keteranganParts = array_filter([
            $purchaseRequest->request_number,
            $batch->purchase_order_document_id && $batch->purchaseOrderDocument
                ? 'PO: ' . $batch->purchaseOrderDocument->po_number
                : ($purchaseRequest->po_number ? 'PO: ' . $purchaseRequest->po_number : null),
            'Batch #' . $batch->batch_no,
            $purchaseRequest->purpose,
            $item->note ?? null,
            $batch->vendor_receipt_note ? 'Catatan Vendor: ' . $batch->vendor_receipt_note : null,
            $batch->user_receipt_note ? 'Catatan Serah Terima: ' . $batch->user_receipt_note : null,
        ]);

        $handoverNumber = $batch->handover_number;
        $itemCode = $item->item_code ?? '';
        $itemName = $item->item_name ?? '';
        $quantity = $batch->user_handover_qty ?? $batch->vendor_receipt_qty;
        $unit = $item->unit ?? '';
        $itemNote = $item->note ?? '';
        $keterangan = implode("\n", $keteranganParts);

        $handedByName = ($handedByEmployee && $handedByEmployee->nama_lengkap) ? $handedByEmployee->nama_lengkap : ($batch->user_receipt_by ?: '-');
        $handedByRecord = $this->findKaryawanByName($handedByName);
        $handedByPosition = $this->resolveKaryawanJabatan($handedByRecord);
        if ($handedByPosition === '-') {
            $handedByPosition = 'Purchasing';
        }
        $handedByDivision = $this->resolveKaryawanDivisi($handedByRecord);
        if ($handedByDivision === '-') {
            $handedByDivision = 'Purchasing';
        }

        $receivedByName = $purchaseRequest->created_by;
        $receivedByPosition = $this->resolveKaryawanJabatan($recipient);
        $receivedByDivision = $this->resolveKaryawanDivisi($recipient);
        $receivedByDate = Carbon::parse($batch->completed_at ?: $handoverDate)->locale('id')->isoFormat('D MMMM YYYY H:mm');

        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A5',
            'orientation' => 'P',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 10,
            'margin_bottom' => 12,
        ]);

        $html = view('pdf.serah-terima-barang', compact(
            'handoverNumber',
            'handoverDateFormatted',
            'itemCode',
            'itemName',
            'quantity',
            'unit',
            'itemNote',
            'keterangan',
            'handedByName',
            'handedByPosition',
            'handedByDivision',
            'receivedByName',
            'receivedByPosition',
            'receivedByDivision',
            'receivedByDate',
        ))->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function handleAttachments(Request $request, $existingAttachmentField)
    {
        $attachments = $this->parseAttachments($existingAttachmentField);

        $removed = $request->input('removed_attachments', []);
        if (!is_array($removed)) {
            $removed = [$removed];
        }
        $removed = array_filter($removed);

        if (!empty($removed)) {
            $attachments = array_values(array_filter($attachments, fn($file) => !in_array($file, $removed)));
            foreach ($removed as $file) {
                $path = public_path(self::ATTACHMENT_DIR . '/' . $file);
                if (file_exists($path) && is_file($path)) {
                    unlink($path);
                }
            }
        }

        $index = 0;
        while ($request->hasFile("attachments.$index") || $request->hasFile("attachments[$index]")) {
            $file = $request->file("attachments.$index") ?: $request->file("attachments[$index]");

            if ($file->getSize() > self::MAX_ATTACHMENT_SIZE) {
                return false;
            }

            if (strpos($file->getMimeType(), 'image/') !== 0) {
                return false;
            }

            $destinationPath = public_path(self::ATTACHMENT_DIR);
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $file->move($destinationPath, $fileName);
            $attachments[] = $fileName;

            $index++;
        }

        return $attachments;
    }

    private function parseAttachments($attachmentField): array
    {
        if (empty($attachmentField)) {
            return [];
        }

        $decoded = json_decode($attachmentField, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter($decoded));
        }

        return [$attachmentField];
    }

    private function encodeAttachments(array $files): ?string
    {
        $files = array_values(array_filter($files));

        if (empty($files)) {
            return null;
        }

        return json_encode($files);
    }

    private function findKaryawanByName(?string $namaLengkap): ?MasterKaryawan
    {
        if (empty($namaLengkap)) {
            return null;
        }

        return MasterKaryawan::with(['jabatan', 'divisi'])
            ->where('nama_lengkap', $namaLengkap)
            ->where('is_active', true)
            ->first();
    }

    private function resolveKaryawanJabatan(?MasterKaryawan $employee): string
    {
        if (!$employee) {
            return '-';
        }

        if ($employee->relationLoaded('jabatan')) {
            $jabatanRelation = $employee->getRelation('jabatan');
            if ($jabatanRelation && !empty($jabatanRelation->nama_jabatan)) {
                return $jabatanRelation->nama_jabatan;
            }
        }

        if (!empty($employee->id_jabatan)) {
            $jabatan = MasterJabatan::where('id', $employee->id_jabatan)->where('is_active', true)->first();
            if ($jabatan && !empty($jabatan->nama_jabatan)) {
                return $jabatan->nama_jabatan;
            }
        }

        $jabatanAttr = $employee->getAttributes()['jabatan'] ?? null;
        if (!empty($jabatanAttr) && is_string($jabatanAttr)) {
            return $jabatanAttr;
        }

        return '-';
    }

    private function resolveKaryawanDivisi(?MasterKaryawan $employee): string
    {
        if (!$employee) {
            return '-';
        }

        if ($employee->relationLoaded('divisi')) {
            $divisiRelation = $employee->getRelation('divisi');
            if ($divisiRelation && !empty($divisiRelation->nama_divisi)) {
                return $divisiRelation->nama_divisi;
            }
        }

        if (!empty($employee->id_department)) {
            $divisi = MasterDivisi::where('id', $employee->id_department)->where('is_active', true)->first();
            if ($divisi && !empty($divisi->nama_divisi)) {
                return $divisi->nama_divisi;
            }
        }

        $departmentAttr = $employee->getAttributes()['department'] ?? null;
        if (!empty($departmentAttr) && is_string($departmentAttr)) {
            return $departmentAttr;
        }

        return '-';
    }

    private function resolvePoRowDisplayStatus(PurchaseOrderDocument $poDocument, string $scope = 'pending'): string
    {
        $target = (float) $poDocument->quantity;
        $vendorTotal = PurchaseReceiptService::getPoVendorReceivedTotal($poDocument->id);
        $confirmedTotal = PurchaseReceiptService::getPoUserConfirmedTotal($poDocument->id);

        if ($scope === 'pending') {
            if ($vendorTotal > 0 && $vendorTotal < $target) {
                return 'Partial Vendor Receipt';
            }

            return 'Waiting Vendor Receipt';
        }

        if ($confirmedTotal >= $target && $target > 0) {
            return 'User Receipt Complete';
        }

        if ($confirmedTotal > 0 && $confirmedTotal < $target) {
            return 'Partial User Receipt';
        }

        if (PurchaseReceiptBatch::where('purchase_order_document_id', $poDocument->id)
            ->whereNotNull('handover_number')
            ->whereNull('completed_at')
            ->exists()) {
            return 'Waiting User Confirm';
        }

        return 'Waiting User Receipt';
    }

    private function applyRequesterDivisiFilter($query, string $keyword): void
    {
        $matchingDivisiIds = MasterDivisi::where('is_active', true)
            ->where('nama_divisi', 'like', "%{$keyword}%")
            ->pluck('id');

        $query->whereHas('purchaseRequest', function ($prQuery) use ($keyword, $matchingDivisiIds) {
            $prQuery->whereExists(function ($sub) use ($keyword, $matchingDivisiIds) {
                $sub->select(DB::raw(1))
                    ->from('master_karyawan as mk')
                    ->whereRaw('purchase_requests.created_by COLLATE utf8mb4_unicode_ci = mk.nama_lengkap COLLATE utf8mb4_unicode_ci')
                    ->where(function ($q) use ($keyword, $matchingDivisiIds) {
                        $q->where('mk.department', 'like', "%{$keyword}%");

                        if ($matchingDivisiIds->isNotEmpty()) {
                            $q->orWhereIn('mk.id_department', $matchingDivisiIds);
                        }

                        $q->orWhereExists(function ($divSub) use ($keyword) {
                            $divSub->select(DB::raw(1))
                                ->from('master_divisi as md')
                                ->whereColumn('mk.id_department', 'md.id')
                                ->where('md.nama_divisi', 'like', "%{$keyword}%");
                        });
                    });
            });
        });
    }
}

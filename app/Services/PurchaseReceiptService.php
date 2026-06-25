<?php

namespace App\Services;

use App\Models\PurchaseOrderDocument;
use App\Models\PurchaseReceiptBatch;
use App\Models\PurchaseRequest;
use Illuminate\Support\Collection;

class PurchaseReceiptService
{
    public static function getPrItemQty(PurchaseRequest $purchaseRequest): float
    {
        if (!$purchaseRequest->relationLoaded('items')) {
            $purchaseRequest->load('items');
        }

        return (float) optional($purchaseRequest->items->first())->quantity;
    }

    public static function getAllocatedPoQty(PurchaseRequest $purchaseRequest): float
    {
        return round((float) PurchaseOrderDocument::where('purchase_request_id', $purchaseRequest->id)
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
            ->whereIn('po_status', ['draft', 'active'])
            ->sum('quantity'), 2);
    }

    public static function getRemainingPoAllocationQty(PurchaseRequest $purchaseRequest): float
    {
        return max(round(self::getPrItemQty($purchaseRequest) - self::getAllocatedPoQty($purchaseRequest), 2), 0);
    }

    public static function getProcessedActivePoQty(PurchaseRequest $purchaseRequest): float
    {
        return round((float) self::getActivePoDocumentsQuery($purchaseRequest->id)->sum('quantity'), 2);
    }

    public static function getActivePoDocuments(int $purchaseRequestId): Collection
    {
        return self::getActivePoDocumentsQuery($purchaseRequestId)
            ->orderBy('processed_at')
            ->orderBy('id')
            ->get();
    }

    public static function getActivePoDocumentsQuery(int $purchaseRequestId)
    {
        return PurchaseOrderDocument::where('purchase_request_id', $purchaseRequestId)
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
            ->where('po_status', 'active');
    }

    public static function findActivePoDocument(int $purchaseRequestId, int $poDocumentId): ?PurchaseOrderDocument
    {
        return self::getActivePoDocumentsQuery($purchaseRequestId)
            ->where('id', $poDocumentId)
            ->first();
    }

    public static function assignOrphanBatchesToPos(int $purchaseRequestId): int
    {
        $assigned = 0;
        $orphanBatches = PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequestId)
            ->whereNull('purchase_order_document_id')
            ->orderBy('batch_no')
            ->get();

        if ($orphanBatches->isEmpty()) {
            return 0;
        }

        $activePos = self::getActivePoDocuments($purchaseRequestId);

        if ($activePos->isEmpty()) {
            return 0;
        }

        if ($activePos->count() === 1) {
            $updated = PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequestId)
                ->whereNull('purchase_order_document_id')
                ->update(['purchase_order_document_id' => $activePos->first()->id]);

            return (int) $updated;
        }

        $poCapacities = [];
        foreach ($activePos as $poDocument) {
            $poCapacities[$poDocument->id] = max(
                round((float) $poDocument->quantity - self::getPoVendorReceivedTotal($poDocument->id), 2),
                0
            );
        }

        foreach ($orphanBatches as $batch) {
            $batchQty = (float) $batch->vendor_receipt_qty;
            $assignedPoId = null;

            foreach ($activePos as $poDocument) {
                if (($poCapacities[$poDocument->id] ?? 0) >= $batchQty) {
                    $assignedPoId = $poDocument->id;
                    $poCapacities[$poDocument->id] -= $batchQty;
                    break;
                }
            }

            if (!$assignedPoId) {
                foreach ($activePos as $poDocument) {
                    if (($poCapacities[$poDocument->id] ?? 0) > 0) {
                        $assignedPoId = $poDocument->id;
                        $poCapacities[$poDocument->id] = max($poCapacities[$poDocument->id] - $batchQty, 0);
                        break;
                    }
                }
            }

            if (!$assignedPoId) {
                $assignedPoId = $activePos->last()->id;
            }

            $batch->purchase_order_document_id = $assignedPoId;
            $batch->save();
            $assigned++;
        }

        $purchaseRequest = PurchaseRequest::find($purchaseRequestId);
        if ($purchaseRequest) {
            self::refreshTotals($purchaseRequest);
        }

        return $assigned;
    }

    public static function getPoVendorReceivedTotal(int $poDocumentId): float
    {
        return round((float) PurchaseReceiptBatch::where('purchase_order_document_id', $poDocumentId)
            ->sum('vendor_receipt_qty'), 2);
    }

    public static function getPoUserConfirmedTotal(int $poDocumentId): float
    {
        return round((float) PurchaseReceiptBatch::where('purchase_order_document_id', $poDocumentId)
            ->whereNotNull('completed_at')
            ->sum('user_handover_qty'), 2);
    }

    public static function poVendorReceivedSubquerySql(): string
    {
        return '(SELECT COALESCE(SUM(prb.vendor_receipt_qty), 0) FROM purchase_receipt_batches prb WHERE prb.purchase_order_document_id = purchase_order_documents.id)';
    }

    public static function poUserConfirmedSubquerySql(): string
    {
        return '(SELECT COALESCE(SUM(prb.user_handover_qty), 0) FROM purchase_receipt_batches prb WHERE prb.purchase_order_document_id = purchase_order_documents.id AND prb.completed_at IS NOT NULL)';
    }

    public static function isPoFullyDistributed(PurchaseOrderDocument $poDocument): bool
    {
        if ($poDocument->po_status !== 'active') {
            return false;
        }

        $target = (float) $poDocument->quantity;

        if ($target <= 0) {
            return false;
        }

        return self::getPoVendorReceivedTotal($poDocument->id) >= $target
            && self::getPoUserConfirmedTotal($poDocument->id) >= $target;
    }

    public static function applyExcludeFullyDistributedPoFilter($query): void
    {
        $vendorSql = self::poVendorReceivedSubquerySql();
        $confirmedSql = self::poUserConfirmedSubquerySql();

        $query->whereRaw("NOT (
            purchase_order_documents.po_status = 'active'
            AND purchase_order_documents.quantity > 0
            AND {$vendorSql} >= purchase_order_documents.quantity
            AND {$confirmedSql} >= purchase_order_documents.quantity
        )");
    }

    public static function getPoRemainingVendorQty(PurchaseOrderDocument $poDocument): float
    {
        $target = (float) $poDocument->quantity;

        return max(round($target - self::getPoVendorReceivedTotal($poDocument->id), 2), 0);
    }

    public static function hasPendingVendorReceiptForPo(PurchaseOrderDocument $poDocument): bool
    {
        return self::getPoRemainingVendorQty($poDocument) > 0;
    }

    public static function hasPendingVendorReceiptForAnyActivePo(PurchaseRequest $purchaseRequest): bool
    {
        foreach (self::getActivePoDocuments($purchaseRequest->id) as $poDocument) {
            if (self::hasPendingVendorReceiptForPo($poDocument)) {
                return true;
            }
        }

        return false;
    }

    public static function formatPoProgress(PurchaseOrderDocument $poDocument): array
    {
        $target = round((float) $poDocument->quantity, 2);
        $vendorReceived = self::getPoVendorReceivedTotal($poDocument->id);
        $userConfirmed = self::getPoUserConfirmedTotal($poDocument->id);

        return [
            'id' => $poDocument->id,
            'po_number' => $poDocument->po_number,
            'quantity' => $target,
            'vendor_received_total' => $vendorReceived,
            'remaining_vendor_qty' => max(round($target - $vendorReceived, 2), 0),
            'user_confirmed_total' => $userConfirmed,
            'remaining_confirm_qty' => max(round($target - $userConfirmed, 2), 0),
            'is_vendor_complete' => $vendorReceived >= $target,
            'is_user_complete' => $userConfirmed >= $target,
        ];
    }

    public static function formatActivePosProgress(PurchaseRequest $purchaseRequest): array
    {
        return self::getActivePoDocuments($purchaseRequest->id)
            ->map(fn(PurchaseOrderDocument $poDocument) => self::formatPoProgress($poDocument))
            ->values()
            ->all();
    }

    public static function formatPoNumbersDisplay(PurchaseRequest $purchaseRequest): string
    {
        $activePos = self::getActivePoDocuments($purchaseRequest->id);

        if ($activePos->isEmpty()) {
            return (string) ($purchaseRequest->po_number ?? '-');
        }

        return $activePos->pluck('po_number')->filter()->implode(', ');
    }

    public static function resolveTargetQty(PurchaseRequest $purchaseRequest): float
    {
        $activeProcessedQty = self::getProcessedActivePoQty($purchaseRequest);

        if ($activeProcessedQty > 0) {
            return $activeProcessedQty;
        }

        if (!empty($purchaseRequest->receipt_target_qty) && (float) $purchaseRequest->receipt_target_qty > 0) {
            return (float) $purchaseRequest->receipt_target_qty;
        }

        $poDocument = PurchaseOrderDocument::where('purchase_request_id', $purchaseRequest->id)
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
            ->whereIn('po_status', ['draft', 'active'])
            ->latest('id')
            ->first();

        if ($poDocument && (float) $poDocument->quantity > 0) {
            return (float) $poDocument->quantity;
        }

        if (!$purchaseRequest->relationLoaded('items')) {
            $purchaseRequest->load('items');
        }

        $item = $purchaseRequest->items->first();

        return (float) ($item->quantity ?? 0);
    }

    public static function reopenIfIncomplete(PurchaseRequest $purchaseRequest): void
    {
        $target = self::resolveTargetQty($purchaseRequest);

        if ($target <= 0 || $purchaseRequest->finance_status === 'Distributed') {
            return;
        }

        $vendorTotal = (float) ($purchaseRequest->vendor_received_total ?? 0);
        $confirmedTotal = (float) ($purchaseRequest->user_confirmed_total ?? 0);

        if ($vendorTotal >= $target && $confirmedTotal >= $target && !self::hasPendingVendorReceiptForAnyActivePo($purchaseRequest)) {
            return;
        }

        if ($purchaseRequest->status === 'Done') {
            $purchaseRequest->status = 'Approved';
            $purchaseRequest->completed_at = null;
            $purchaseRequest->completed_by = null;
        }
    }

    public static function getRemainingVendorQty(PurchaseRequest $purchaseRequest): float
    {
        $activePos = self::getActivePoDocuments($purchaseRequest->id);

        if ($activePos->isEmpty()) {
            $target = self::resolveTargetQty($purchaseRequest);

            return max(round($target - (float) $purchaseRequest->vendor_received_total, 2), 0);
        }

        $remaining = 0;
        foreach ($activePos as $poDocument) {
            $remaining += self::getPoRemainingVendorQty($poDocument);
        }

        return round($remaining, 2);
    }

    public static function hasPendingVendorReceipt(PurchaseRequest $purchaseRequest): bool
    {
        return self::getRemainingVendorQty($purchaseRequest) > 0;
    }

    public static function hasPendingUserReceipt(PurchaseRequest $purchaseRequest): bool
    {
        $target = self::resolveTargetQty($purchaseRequest);

        return (float) $purchaseRequest->user_confirmed_total < $target;
    }

    public static function getNextBatchNo(int $purchaseRequestId): int
    {
        return (int) PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequestId)->max('batch_no') + 1;
    }

    public static function refreshTotals(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        $batches = PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)->get();

        $vendorTotal = round($batches->sum('vendor_receipt_qty'), 2);
        $handedTotal = round($batches->whereNotNull('handover_number')->sum('user_handover_qty'), 2);
        $confirmedTotal = round($batches->whereNotNull('completed_at')->sum('user_handover_qty'), 2);

        if (empty($purchaseRequest->receipt_target_qty) || (float) $purchaseRequest->receipt_target_qty <= 0) {
            $purchaseRequest->receipt_target_qty = self::resolveTargetQty($purchaseRequest);
        } else {
            $resolvedTarget = self::resolveTargetQty($purchaseRequest);
            if ($resolvedTarget > (float) $purchaseRequest->receipt_target_qty) {
                $purchaseRequest->receipt_target_qty = $resolvedTarget;
            }
        }

        $purchaseRequest->vendor_received_total = $vendorTotal;
        $purchaseRequest->user_handed_total = $handedTotal;
        $purchaseRequest->user_confirmed_total = $confirmedTotal;
        $purchaseRequest->po_number = self::formatPoNumbersDisplay($purchaseRequest) ?: $purchaseRequest->po_number;

        $latestVendorBatch = $batches->sortByDesc('id')->first();
        if ($latestVendorBatch) {
            $purchaseRequest->vendor_receipt_at = $latestVendorBatch->vendor_receipt_at;
            $purchaseRequest->vendor_receipt_by = $latestVendorBatch->vendor_receipt_by;
            $purchaseRequest->vendor_delivery_note = $latestVendorBatch->vendor_delivery_note;
            $purchaseRequest->vendor_receipt_qty = $vendorTotal;
            $purchaseRequest->vendor_receipt_note = $latestVendorBatch->vendor_receipt_note;
            $purchaseRequest->vendor_receipt_attachments = $latestVendorBatch->vendor_receipt_attachments;
        }

        $latestHandoverBatch = $batches->whereNotNull('handover_number')->sortByDesc('id')->first();
        if ($latestHandoverBatch) {
            $purchaseRequest->handover_number = $latestHandoverBatch->handover_number;
            $purchaseRequest->user_receipt_at = $latestHandoverBatch->user_receipt_at;
            $purchaseRequest->user_receipt_by = $latestHandoverBatch->user_receipt_by;
            $purchaseRequest->user_receipt_note = $latestHandoverBatch->user_receipt_note;
        }

        self::syncFinanceStatus($purchaseRequest);
        self::reopenIfIncomplete($purchaseRequest);
        $purchaseRequest->save();

        return $purchaseRequest->fresh();
    }

    public static function syncFinanceStatus(PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->receipt_target_qty = self::resolveTargetQty($purchaseRequest);
        $target = (float) $purchaseRequest->receipt_target_qty;
        $vendorTotal = (float) ($purchaseRequest->vendor_received_total ?? 0);
        $confirmedTotal = (float) ($purchaseRequest->user_confirmed_total ?? 0);
        $pendingVendorForAnyPo = self::hasPendingVendorReceiptForAnyActivePo($purchaseRequest);

        if ($target > 0 && $confirmedTotal >= $target && $vendorTotal >= $target && !$pendingVendorForAnyPo) {
            if (self::getRemainingPoAllocationQty($purchaseRequest) > 0) {
                if (self::hasUnconfirmedHandover($purchaseRequest)) {
                    $purchaseRequest->finance_status = 'Distributing';
                } elseif (self::countPendingUserHandoverBatches($purchaseRequest) > 0) {
                    $purchaseRequest->finance_status = 'Waiting User Receipt';
                } else {
                    $purchaseRequest->finance_status = 'Waiting to Create PO';
                }
            } else {
                $purchaseRequest->finance_status = 'Distributed';
                $purchaseRequest->status = 'Done';

                if (empty($purchaseRequest->completed_at)) {
                    $lastConfirmed = PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)
                        ->whereNotNull('completed_at')
                        ->orderByDesc('completed_at')
                        ->first();

                    $purchaseRequest->completed_by = $lastConfirmed->completed_by ?? $purchaseRequest->created_by;
                    $purchaseRequest->completed_at = $lastConfirmed->completed_at ?? date('Y-m-d H:i:s');
                }
            }
        } elseif (self::hasUnconfirmedHandover($purchaseRequest)) {
            $purchaseRequest->finance_status = 'Distributing';
        } elseif (self::countPendingUserHandoverBatches($purchaseRequest) > 0) {
            $purchaseRequest->finance_status = 'Waiting User Receipt';
        } elseif ($pendingVendorForAnyPo) {
            $purchaseRequest->finance_status = 'Waiting Vendor Receipt';
        } elseif ($vendorTotal > 0) {
            $purchaseRequest->finance_status = 'Waiting User Receipt';
        } else {
            $purchaseRequest->finance_status = 'Waiting Vendor Receipt';
        }

        self::reopenIfIncomplete($purchaseRequest);
    }

    public static function hasUnconfirmedHandover(PurchaseRequest $purchaseRequest): bool
    {
        return PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)
            ->whereNotNull('handover_number')
            ->whereNull('completed_at')
            ->exists();
    }

    public static function hasPendingUserHandoverBatch(PurchaseRequest $purchaseRequest): bool
    {
        return self::countPendingUserHandoverBatches($purchaseRequest) > 0;
    }

    public static function countPendingUserHandoverBatchesForPo(int $poDocumentId): int
    {
        return (int) PurchaseReceiptBatch::where('purchase_order_document_id', $poDocumentId)
            ->whereNull('handover_number')
            ->whereNotNull('vendor_receipt_at')
            ->count();
    }

    public static function countHandoverBatchesForPo(int $poDocumentId): int
    {
        return (int) PurchaseReceiptBatch::where('purchase_order_document_id', $poDocumentId)
            ->whereNotNull('handover_number')
            ->count();
    }

    public static function hasPendingUserHandoverBatchForPo(int $poDocumentId): bool
    {
        return self::countPendingUserHandoverBatchesForPo($poDocumentId) > 0;
    }

    public static function getLatestVendorReceiptAtForPo(int $poDocumentId): ?string
    {
        return PurchaseReceiptBatch::where('purchase_order_document_id', $poDocumentId)
            ->whereNotNull('vendor_receipt_at')
            ->orderByDesc('vendor_receipt_at')
            ->value('vendor_receipt_at');
    }

    public static function countPendingUserHandoverBatches(PurchaseRequest $purchaseRequest): int
    {
        if ((float) ($purchaseRequest->vendor_received_total ?? 0) <= 0) {
            return 0;
        }

        return (int) PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)
            ->whereNull('handover_number')
            ->whereNotNull('vendor_receipt_at')
            ->count();
    }

    public static function countHandoverBatches(PurchaseRequest $purchaseRequest): int
    {
        return (int) PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)
            ->whereNotNull('handover_number')
            ->count();
    }

    public static function formatBatch(PurchaseReceiptBatch $batch, string $attachmentDirectory): array
    {
        $attachments = self::parseAttachments($batch->vendor_receipt_attachments);
        $poDocument = $batch->relationLoaded('purchaseOrderDocument')
            ? $batch->purchaseOrderDocument
            : ($batch->purchase_order_document_id
                ? PurchaseOrderDocument::find($batch->purchase_order_document_id)
                : null);

        return [
            'id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'purchase_order_document_id' => $batch->purchase_order_document_id,
            'po_number' => $poDocument->po_number ?? null,
            'vendor_receipt_qty' => $batch->vendor_receipt_qty,
            'vendor_delivery_note' => $batch->vendor_delivery_note,
            'vendor_receipt_note' => $batch->vendor_receipt_note,
            'vendor_receipt_by' => $batch->vendor_receipt_by,
            'vendor_receipt_at' => $batch->vendor_receipt_at,
            'attachments' => array_map(function ($filename) use ($attachmentDirectory) {
                return [
                    'filename' => $filename,
                    'url' => $attachmentDirectory . '/' . $filename,
                ];
            }, $attachments),
            'handover_number' => $batch->handover_number,
            'user_handover_qty' => $batch->user_handover_qty,
            'user_receipt_note' => $batch->user_receipt_note,
            'user_receipt_by' => $batch->user_receipt_by,
            'user_receipt_at' => $batch->user_receipt_at,
            'user_confirm_note' => $batch->user_confirm_note,
            'completed_by' => $batch->completed_by,
            'completed_at' => $batch->completed_at,
            'is_partial' => true,
        ];
    }

    public static function parseAttachments($attachmentField): array
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
}

<?php

namespace App\Services;

use App\Models\PurchaseOrderDocument;
use App\Models\PurchaseReceiptBatch;
use App\Models\PurchaseRequest;

class PurchaseReceiptService
{
    public static function resolveTargetQty(PurchaseRequest $purchaseRequest): float
    {
        if (!empty($purchaseRequest->receipt_target_qty) && (float) $purchaseRequest->receipt_target_qty > 0) {
            return (float) $purchaseRequest->receipt_target_qty;
        }

        $poDocument = PurchaseOrderDocument::where('purchase_request_id', $purchaseRequest->id)
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
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

    public static function getRemainingVendorQty(PurchaseRequest $purchaseRequest): float
    {
        $target = self::resolveTargetQty($purchaseRequest);

        return max(round($target - (float) $purchaseRequest->vendor_received_total, 2), 0);
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
        }

        $purchaseRequest->vendor_received_total = $vendorTotal;
        $purchaseRequest->user_handed_total = $handedTotal;
        $purchaseRequest->user_confirmed_total = $confirmedTotal;

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
        $purchaseRequest->save();

        return $purchaseRequest->fresh();
    }

    public static function syncFinanceStatus(PurchaseRequest $purchaseRequest): void
    {
        $target = (float) $purchaseRequest->receipt_target_qty;
        $vendorTotal = (float) $purchaseRequest->vendor_received_total;
        $confirmedTotal = (float) $purchaseRequest->user_confirmed_total;

        if ($target > 0 && $confirmedTotal >= $target && $vendorTotal >= $target) {
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

            return;
        }

        $hasUnconfirmedHandover = PurchaseReceiptBatch::where('purchase_request_id', $purchaseRequest->id)
            ->whereNotNull('handover_number')
            ->whereNull('completed_at')
            ->exists();

        if ($hasUnconfirmedHandover) {
            $purchaseRequest->finance_status = 'Distributing';

            return;
        }

        if ($vendorTotal > 0) {
            $purchaseRequest->finance_status = 'Waiting User Receipt';

            return;
        }

        $purchaseRequest->finance_status = 'Waiting Vendor Receipt';
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

    public static function formatBatch(PurchaseReceiptBatch $batch, string $attachmentDirectory): array
    {
        $attachments = self::parseAttachments($batch->vendor_receipt_attachments);

        return [
            'id' => $batch->id,
            'batch_no' => $batch->batch_no,
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

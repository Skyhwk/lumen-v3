<?php

use App\Models\PurchaseRequest;
use App\Services\PurchaseReceiptService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixPartialPoGoodsReceiptStatus extends Migration
{
    public function up()
    {
        $purchaseRequestIds = DB::table('purchase_order_documents')
            ->where(function ($query) {
                $query->where('is_voided', false)->orWhereNull('is_voided');
            })
            ->where('po_status', 'active')
            ->distinct()
            ->pluck('purchase_request_id');

        foreach ($purchaseRequestIds as $purchaseRequestId) {
            $purchaseRequest = PurchaseRequest::with('items')->find($purchaseRequestId);
            if (!$purchaseRequest) {
                continue;
            }

            $processedQty = PurchaseReceiptService::getProcessedActivePoQty($purchaseRequest);
            if ($processedQty <= 0) {
                continue;
            }

            $purchaseRequest->receipt_target_qty = $processedQty;
            $vendorTotal = (float) ($purchaseRequest->vendor_received_total ?? 0);

            if ($vendorTotal < $processedQty) {
                if ($vendorTotal > 0 || PurchaseReceiptService::countHandoverBatches($purchaseRequest) > 0) {
                    PurchaseReceiptService::syncFinanceStatus($purchaseRequest);
                } else {
                    $purchaseRequest->finance_status = 'Waiting Vendor Receipt';
                }
            } elseif (PurchaseReceiptService::getRemainingPoAllocationQty($purchaseRequest) > 0) {
                PurchaseReceiptService::syncFinanceStatus($purchaseRequest);
            }

            $purchaseRequest->save();
        }
    }

    public function down()
    {
        // no rollback
    }
}

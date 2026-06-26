<?php

use App\Services\PurchaseReceiptService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixOrphanReceiptBatchPoAssignment extends Migration
{
    public function up()
    {
        $purchaseRequestIds = DB::table('purchase_receipt_batches')
            ->whereNull('purchase_order_document_id')
            ->distinct()
            ->pluck('purchase_request_id');

        $totalAssigned = 0;

        foreach ($purchaseRequestIds as $purchaseRequestId) {
            $totalAssigned += PurchaseReceiptService::assignOrphanBatchesToPos((int) $purchaseRequestId);
        }

        echo "[orphan-gr-fix] Assigned {$totalAssigned} batch(es) across {$purchaseRequestIds->count()} PR.\n";
    }

    public function down()
    {
        // no rollback
    }
}

<?php

use App\Models\PurchaseRequest;
use App\Services\PurchaseReceiptService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixMultiPoGoodsReceiptStatus extends Migration
{
    public function up()
    {
        $purchaseRequestIds = DB::table('purchase_requests')
            ->where('is_active', true)
            ->where('status', 'Done')
            ->whereIn('finance_status', ['Waiting Vendor Receipt', 'Waiting User Receipt', 'Distributing'])
            ->pluck('id');

        foreach ($purchaseRequestIds as $purchaseRequestId) {
            $purchaseRequest = PurchaseRequest::find($purchaseRequestId);

            if (!$purchaseRequest) {
                continue;
            }

            $purchaseRequest->receipt_target_qty = PurchaseReceiptService::resolveTargetQty($purchaseRequest);
            PurchaseReceiptService::syncFinanceStatus($purchaseRequest);
            PurchaseReceiptService::reopenIfIncomplete($purchaseRequest);
            $purchaseRequest->save();
        }

        echo '[multi-po-gr-fix] Diperbarui ' . $purchaseRequestIds->count() . " PR.\n";
    }

    public function down()
    {
        // no rollback
    }
}

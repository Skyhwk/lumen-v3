<?php

use App\Models\PurchaseRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixPartialPoPrStatus extends Migration
{
    public function up()
    {
        $purchaseRequestIds = DB::table('purchase_order_documents as pod')
            ->join('purchase_requests as pr', 'pr.id', '=', 'pod.purchase_request_id')
            ->join('purchase_request_items as pri', 'pri.purchase_request_id', '=', 'pr.id')
            ->where(function ($query) {
                $query->where('pod.is_voided', false)->orWhereNull('pod.is_voided');
            })
            ->whereIn('pod.po_status', ['draft', 'active'])
            ->groupBy('pod.purchase_request_id', 'pri.quantity')
            ->havingRaw('pri.quantity > COALESCE(SUM(pod.quantity), 0)')
            ->pluck('pod.purchase_request_id');

        foreach ($purchaseRequestIds as $purchaseRequestId) {
            $purchaseRequest = PurchaseRequest::with('items')->find($purchaseRequestId);
            if (!$purchaseRequest) {
                continue;
            }

            $targetQty = (float) optional($purchaseRequest->items->first())->quantity;
            $allocatedQty = (float) DB::table('purchase_order_documents')
                ->where('purchase_request_id', $purchaseRequestId)
                ->where(function ($query) {
                    $query->where('is_voided', false)->orWhereNull('is_voided');
                })
                ->whereIn('po_status', ['draft', 'active'])
                ->sum('quantity');

            if ($targetQty > $allocatedQty) {
                $purchaseRequest->finance_status = 'Waiting to Create PO';
                $purchaseRequest->save();
            }
        }
    }

    public function down()
    {
        // no rollback
    }
}

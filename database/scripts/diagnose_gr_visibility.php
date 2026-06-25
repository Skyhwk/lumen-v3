<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->boot();

$pr = DB::table('purchase_requests')->where('request_number', 'ISL/PR/26-VI/0091')->first();
$poVendorReceivedSql = '(SELECT COALESCE(SUM(prb.vendor_receipt_qty), 0) FROM purchase_receipt_batches prb WHERE prb.purchase_order_document_id = purchase_order_documents.id)';
$poUserConfirmedSql = '(SELECT COALESCE(SUM(prb.user_handover_qty), 0) FROM purchase_receipt_batches prb WHERE prb.purchase_order_document_id = purchase_order_documents.id AND prb.completed_at IS NOT NULL)';

foreach (['pending', 'completed'] as $scope) {
    echo "\n=== SCOPE: {$scope} ===\n";
    $q = DB::table('purchase_order_documents')
        ->join('purchase_requests', 'purchase_requests.id', '=', 'purchase_order_documents.purchase_request_id')
        ->where('purchase_requests.id', $pr->id)
        ->where('purchase_order_documents.po_status', 'active')
        ->select('purchase_order_documents.po_number', 'purchase_order_documents.quantity', DB::raw("{$poVendorReceivedSql} as v"), DB::raw("{$poUserConfirmedSql} as c"));

    if ($scope === 'pending') {
        $q->whereRaw("{$poVendorReceivedSql} < purchase_order_documents.quantity");
    } else {
        $activePoTargetSql = '(SELECT COALESCE(SUM(pod.quantity), 0) FROM purchase_order_documents pod WHERE pod.purchase_request_id = purchase_requests.id AND (pod.is_voided = 0 OR pod.is_voided IS NULL) AND pod.po_status = \'active\')';
        $q->whereRaw("{$poVendorReceivedSql} > 0")
            ->whereRaw("COALESCE(purchase_requests.user_confirmed_total, 0) < COALESCE(NULLIF(purchase_requests.receipt_target_qty, 0), {$activePoTargetSql})")
            ->whereExists(function ($sub) use ($pr) {
                $sub->select(DB::raw(1))
                    ->from('purchase_receipt_batches as prb')
                    ->whereColumn('prb.purchase_order_document_id', 'purchase_order_documents.id')
                    ->whereNotNull('prb.handover_number');
            });
    }

    foreach ($q->get() as $row) {
        echo "{$row->po_number} qty={$row->quantity} vendor={$row->v} confirmed={$row->c}\n";
    }
}

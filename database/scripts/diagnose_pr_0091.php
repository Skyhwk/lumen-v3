<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->boot();

$requestNumber = $argv[1] ?? 'ISL/PR/26-VI/0091';

$pr = DB::table('purchase_requests')->where('request_number', $requestNumber)->first();
if (!$pr) {
    echo "PR not found: {$requestNumber}\n";
    exit(1);
}

echo "PR id={$pr->id} status={$pr->status} finance={$pr->finance_status}\n";
echo "vendor_total={$pr->vendor_received_total} confirmed={$pr->user_confirmed_total} target={$pr->receipt_target_qty}\n";

$pos = DB::table('purchase_order_documents')
    ->where('purchase_request_id', $pr->id)
    ->orderBy('processed_at')
    ->orderBy('id')
    ->get(['id', 'po_number', 'quantity', 'po_status', 'processed_at', 'is_voided']);

foreach ($pos as $po) {
    $v = (float) DB::table('purchase_receipt_batches')
        ->where('purchase_order_document_id', $po->id)
        ->sum('vendor_receipt_qty');
    echo "PO id={$po->id} {$po->po_number} qty={$po->quantity} status={$po->po_status} void=" . ($po->is_voided ?? 'null') . " batch_vendor={$v}\n";
}

$batches = DB::table('purchase_receipt_batches')->where('purchase_request_id', $pr->id)->orderBy('batch_no')->get();
echo "Batches:\n";
foreach ($batches as $b) {
    echo "  batch#{$b->batch_no} qty={$b->vendor_receipt_qty} po_doc_id=" . ($b->purchase_order_document_id ?? 'NULL')
        . " handover=" . ($b->handover_number ?? '-')
        . " completed=" . ($b->completed_at ?? '-') . "\n";
}

$orphanSum = (float) DB::table('purchase_receipt_batches')
    ->where('purchase_request_id', $pr->id)
    ->whereNull('purchase_order_document_id')
    ->sum('vendor_receipt_qty');
echo "Orphan batches sum (null po): {$orphanSum}\n";

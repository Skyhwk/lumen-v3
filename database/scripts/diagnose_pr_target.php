<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->boot();

use App\Services\PurchaseReceiptService;

$requestNumber = $argv[1] ?? 'ISL/PR/26-VI/0091';
$pr = \App\Models\PurchaseRequest::with('items')->where('request_number', $requestNumber)->first();

if (!$pr) {
    echo "PR not found\n";
    exit(1);
}

$itemQty = PurchaseReceiptService::getPrItemQty($pr);
$allocated = PurchaseReceiptService::getAllocatedPoQty($pr);
$activeProcessed = PurchaseReceiptService::getProcessedActivePoQty($pr);
$remainingAlloc = PurchaseReceiptService::getRemainingPoAllocationQty($pr);
$resolvedTarget = PurchaseReceiptService::resolveTargetQty($pr);

echo "=== PR {$requestNumber} (id={$pr->id}) ===\n";
echo "PR item qty (permintaan)     : {$itemQty}\n";
echo "receipt_target_qty (DB)      : {$pr->receipt_target_qty}\n";
echo "Allocated PO qty (draft+active): {$allocated}\n";
echo "Active processed PO qty      : {$activeProcessed}\n";
echo "Remaining PO allocation      : {$remainingAlloc}\n";
echo "resolveTargetQty()           : {$resolvedTarget}\n";
echo "vendor_received_total        : {$pr->vendor_received_total}\n";
echo "user_confirmed_total         : {$pr->user_confirmed_total}\n";
echo "finance_status               : {$pr->finance_status}\n\n";

echo "PO documents:\n";
$pos = \App\Models\PurchaseOrderDocument::where('purchase_request_id', $pr->id)
    ->orderBy('id')
    ->get(['id', 'po_number', 'quantity', 'po_status', 'is_voided']);

foreach ($pos as $po) {
    echo "  [{$po->po_status}] {$po->po_number} qty={$po->quantity} void={$po->is_voided}\n";
}

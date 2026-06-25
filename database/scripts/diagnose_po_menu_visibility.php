<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->boot();

$requestNumber = $argv[1] ?? 'ISL/PR/26-VI/0091';
$pr = \App\Models\PurchaseRequest::with('items')->where('request_number', $requestNumber)->first();

if (!$pr) {
    echo "PR not found\n";
    exit(1);
}

$itemQtySql = '(SELECT COALESCE(pri.quantity, 0) FROM purchase_request_items pri WHERE pri.purchase_request_id = purchase_requests.id ORDER BY pri.id ASC LIMIT 1)';
$allocatedQtySql = '(SELECT COALESCE(SUM(pod.quantity), 0) FROM purchase_order_documents pod WHERE pod.purchase_request_id = purchase_requests.id AND (pod.is_voided = 0 OR pod.is_voided IS NULL) AND pod.po_status IN (\'draft\', \'active\'))';

$remaining = \App\Services\PurchaseReceiptService::getRemainingPoAllocationQty($pr);

echo "PR: {$requestNumber}\n";
echo "finance_status: {$pr->finance_status}\n";
echo "remaining_po_allocation: {$remaining}\n\n";

$blocked = ['Waiting to Delegate', 'Rejected', 'Void', 'Distributed'];

echo "=== PO menu PENDING (buat PO baru) — rule baru ===\n";
$pending = DB::table('purchase_requests')
    ->where('id', $pr->id)
    ->where('is_active', true)
    ->whereIn('status', ['Approved', 'Partially Approved'])
    ->where('finance_status', '!=', 'Rejected')
    ->whereNotIn('finance_status', $blocked)
    ->whereRaw("({$itemQtySql} - {$allocatedQtySql}) > 0")
    ->exists();
echo $pending ? "MUNCUL di pending PO creation\n" : "TIDAK muncul di pending PO creation\n";
echo "  - remaining > 0? " . ($remaining > 0 ? 'YA' : 'TIDAK') . "\n";
echo "  - finance blocked? " . (in_array($pr->finance_status, $blocked) ? 'YA' : 'TIDAK (' . $pr->finance_status . ')') . "\n\n";

echo "=== PO menu ON PROCESS (draft PO) ===\n";
$onProcess = $pr->finance_status === 'On Process';
echo ($onProcess ? "MUNCUL" : "TIDAK muncul") . " di tab On Process\n\n";

echo "=== PO LIST (dokumen PO existing) ===\n";
$poCount = DB::table('purchase_order_documents')
    ->where('purchase_request_id', $pr->id)
    ->whereIn('po_status', ['draft', 'active'])
    ->where(function ($q) {
        $q->where('is_voided', false)->orWhereNull('is_voided');
    })
    ->count();
echo "PO aktif/draft: {$poCount} dokumen (0044 + 0090)\n";

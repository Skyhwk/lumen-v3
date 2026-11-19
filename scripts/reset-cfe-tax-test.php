<?php

/**
 * Reset claim yang terlanjur di-approve tax (PROCESSED / TAX_IN_PROGRESS)
 * kembali ke antrian pajak (WAITING PROCESS / WAITING_TAX).
 *
 * Usage:
 *   php scripts/reset-cfe-tax-test.php --no-order=KEON012501
 *   php scripts/reset-cfe-tax-test.php --id=123
 */

use App\Models\ClaimFeeExternal;
use App\Services\ClaimFeeExternalStatusSyncService;
use App\Support\ClaimFeeExternal\ClaimFeeExternalWorkflow;

$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

$opts = getopt('', ['id:', 'no-order:']);
$id = isset($opts['id']) ? (int) $opts['id'] : 0;
$noOrder = isset($opts['no-order']) ? trim((string) $opts['no-order']) : '';

if ($id <= 0 && $noOrder === '') {
    fwrite(STDERR, "Berikan --id= atau --no-order=\n");
    exit(1);
}

$query = ClaimFeeExternal::query()->where('is_active', true);
if ($id > 0) {
    $query->where('id', $id);
} else {
    $query->where('no_order', $noOrder);
}

/** @var ClaimFeeExternal|null $claim */
$claim = $query->first();

if (!$claim) {
    fwrite(STDERR, "Claim tidak ditemukan.\n");
    exit(1);
}

$before = [
    'id' => $claim->id,
    'no_order' => $claim->no_order,
    'status_pembayaran' => $claim->status_pembayaran,
    'finance_status' => $claim->finance_status,
    'processed_by' => $claim->processed_by,
    'processed_at' => $claim->processed_at,
    'potongan' => $claim->potongan,
    'nominal_bayar' => $claim->nominal_bayar,
];

$claim->processed_by = null;
$claim->processed_at = null;
$claim->potongan = null;
$claim->nominal_bayar = null;

if (\Illuminate\Support\Facades\Schema::hasColumn('claim_fee_external', 'finance_status')) {
    $claim->finance_status = ClaimFeeExternalWorkflow::FINANCE_WAITING_TAX;
}

app(ClaimFeeExternalStatusSyncService::class)->sync($claim);
$claim->save();

$after = [
    'id' => $claim->id,
    'no_order' => $claim->no_order,
    'status_pembayaran' => $claim->status_pembayaran,
    'finance_status' => $claim->finance_status,
];

echo json_encode(['before' => $before, 'after' => $after], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
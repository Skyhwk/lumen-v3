<?php

/**
 * Reset claim yang sudah transfer/selesai kembali ke langkah upload bukti potong PPh (tax settlement).
 * PPh angka (potongan / nominal_bayar) tetap; file & transfer dibersihkan.
 *
 * Usage:
 *   php scripts/reset-cfe-upload-pph.php --no-order=KEON012501
 *   php scripts/reset-cfe-upload-pph.php --id=3
 */

use App\Models\ClaimFeeExternal;
use App\Services\ClaimFeeExternalStatusSyncService;
use App\Support\ClaimFeeExternal\ClaimFeeExternalWorkflow;
use Illuminate\Support\Facades\Schema;

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
$claim = $query->orderByDesc('id')->first();

if (!$claim) {
    fwrite(STDERR, "Claim tidak ditemukan.\n");
    exit(1);
}

$before = $claim->only([
    'id', 'no_order', 'status_pembayaran', 'finance_status',
    'filename', 'bukti_transfer', 'transferred_by', 'tanggal_pembayaran',
    'potongan', 'nominal_bayar', 'processed_by',
]);

if (Schema::hasColumn('claim_fee_external', 'finance_status')) {
    $claim->finance_status = ClaimFeeExternalWorkflow::FINANCE_READY_TO_PAY;
}

$claim->transferred_by = null;
$claim->tanggal_pembayaran = null;

if (Schema::hasColumn('claim_fee_external', 'filename')) {
    if (!empty($claim->filename)) {
        $pphPath = public_path('claim_fee_external' . DIRECTORY_SEPARATOR . $claim->filename);
        if (is_file($pphPath)) {
            @unlink($pphPath);
        }
    }
    $claim->filename = null;
}

if (Schema::hasColumn('claim_fee_external', 'bukti_transfer')) {
    if (!empty($claim->bukti_transfer)) {
        $buktiPath = public_path(
            'claim_fee_external' . DIRECTORY_SEPARATOR . 'bukti_transfer' . DIRECTORY_SEPARATOR . $claim->bukti_transfer
        );
        if (is_file($buktiPath)) {
            @unlink($buktiPath);
        }
    }
    $claim->bukti_transfer = null;
}

if ($claim->potongan === null || $claim->nominal_bayar === null) {
  fwrite(STDERR, "Claim belum punya potongan/nominal_bayar — gunakan reset-cfe-tax-test.php atau proses PPh dulu.\n");
  exit(1);
}

app(ClaimFeeExternalStatusSyncService::class)->sync($claim);
$claim->save();

$after = $claim->fresh()->only([
    'id', 'no_order', 'status_pembayaran', 'finance_status',
    'filename', 'bukti_transfer', 'transferred_by', 'tanggal_pembayaran',
    'potongan', 'nominal_bayar',
]);

echo json_encode([
    'message' => 'OK — siap upload bukti PPh di Finance > Tax > tab settlement.',
    'before' => $before,
    'after' => $after,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
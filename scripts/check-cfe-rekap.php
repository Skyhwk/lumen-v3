<?php

use App\Models\ClaimFeeExternal;
use Illuminate\Support\Facades\Schema;

$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

$noOrder = $argv[1] ?? 'KEON012501';
$claim = ClaimFeeExternal::query()
    ->where('is_active', true)
    ->where('no_order', $noOrder)
    ->orderByDesc('id')
    ->first();

if (!$claim) {
    fwrite(STDERR, "Claim tidak ditemukan: {$noOrder}\n");
    exit(1);
}

$inRekapQuery = ClaimFeeExternal::query()
    ->where('id', $claim->id)
  ->where('status_pembayaran', 'TRANSFER')
    ->whereNotNull('transferred_by');

if (Schema::hasColumn('claim_fee_external', 'tanggal_pembayaran')) {
    $inRekapQuery->whereNotNull('tanggal_pembayaran');
}

$inRekap = $inRekapQuery->exists();

$checks = [
    'status_TRANSFER' => $claim->status_pembayaran === 'TRANSFER',
    'has_transferred_by' => !empty($claim->transferred_by),
    'has_tanggal' => $claim->tanggal_pembayaran !== null && $claim->tanggal_pembayaran !== '',
    'is_active' => (bool) $claim->is_active,
];
$checks['status_pembayaran_raw'] = $claim->status_pembayaran;
$checks['tanggal_pembayaran_raw'] = $claim->tanggal_pembayaran;

$raw = \Illuminate\Support\Facades\DB::selectOne(
    'SELECT id, status_pembayaran, transferred_by, tanggal_pembayaran,
            (tanggal_pembayaran IS NOT NULL) AS t_not_null,
            (tanggal_pembayaran != ?) AS t_neq_empty
     FROM claim_fee_external WHERE id = ?',
    ['', $claim->id]
);

echo json_encode([
    'claim' => $claim->only([
        'id', 'no_order', 'status_pembayaran', 'finance_status',
        'transferred_by', 'tanggal_pembayaran', 'filename', 'bukti_transfer',
        'potongan', 'nominal_bayar',
    ]),
    'rekap_check' => [
        'matches_rekap_scope' => $inRekap,
        'reason' => $inRekap ? 'OK' : buildReason($claim),
        'checks' => $checks,
        'sql' => $inRekapQuery->toSql(),
        'bindings' => $inRekapQuery->getBindings(),
        'raw_sql_row' => $raw,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

function buildReason(ClaimFeeExternal $c): string
{
    $parts = [];
    if ($c->status_pembayaran !== 'TRANSFER') {
        $parts[] = "status_pembayaran={$c->status_pembayaran} (harus TRANSFER)";
    }
    if (empty($c->transferred_by)) {
        $parts[] = 'transferred_by kosong';
    }
    if ($c->tanggal_pembayaran === null || $c->tanggal_pembayaran === '') {
        $parts[] = 'tanggal_pembayaran kosong';
    }
    if (!$parts) {
        return 'tidak diketahui';
    }

    return implode('; ', $parts);
}
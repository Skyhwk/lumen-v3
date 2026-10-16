<?php

/**
 * Kirim 1 email preview template payroll paid (+ slip PDF opsional).
 *
 * Usage:
 *   php database/scripts/test_payroll_paid_email.php [email] [--karyawan-id=601]
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->boot();

use App\Models\MasterKaryawan;
use App\Models\Payroll;
use App\Models\PayrollHeader;
use App\Services\Greatday\RenderSlipGajiService;
use App\Services\Payroll\PayrollTransferCompletionService;
use App\Services\SendEmail;

$to = 'harold@intilab.com';
$karyawanId = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--karyawan-id=') === 0) {
        $karyawanId = (int) substr($arg, strlen('--karyawan-id='));
        continue;
    }
    if (strpos($arg, '--') !== 0 && filter_var($arg, FILTER_VALIDATE_EMAIL)) {
        $to = $arg;
    }
}

if (!$karyawanId) {
    $karyawanId = 601;
}

$karyawan = MasterKaryawan::query()->where('id', $karyawanId)->first();
if (!$karyawan) {
    fwrite(STDERR, "Karyawan id {$karyawanId} tidak ditemukan.\n");
    exit(1);
}

$payroll = Payroll::query()
    ->where('id_karyawan', $karyawanId)
    ->orderByDesc('id')
    ->first();

if (!$payroll) {
    $payroll = Payroll::query()
        ->where('nik_karyawan', $karyawan->nik_karyawan ?? '')
        ->orderByDesc('id')
        ->first();
}

if (!$payroll) {
    fwrite(STDERR, "Payroll untuk karyawan id {$karyawanId} tidak ditemukan.\n");
    exit(1);
}

$header = PayrollHeader::find($payroll->payroll_header_id);
$tglTransfer = $header->tgl_transfer ?? date('Y-m-d');

$periodeLabel = PayrollTransferCompletionService::formatPeriodeLabel($payroll->periode_payroll);

$tglTransferLabel = PayrollTransferCompletionService::formatTransferDateLabel($tglTransfer);
$nama = $payroll->karyawan ?: $karyawan->nama_lengkap ?: 'Karyawan';

echo "Generate slip PDF payroll_id={$payroll->id}...\n";
$generated = RenderSlipGajiService::fromPayrollId((int) $payroll->id, null, true)->generate();
$pdfPath = $generated['path'] ?? null;

if (!$pdfPath || !is_file($pdfPath)) {
    fwrite(STDERR, "Gagal generate PDF slip gaji.\n");
    exit(1);
}

$attachmentPublicPath = 'dokumen/payroll/slip_gaji/pdf/' . basename($pdfPath);

$body = view('emails.payroll.paid', [
    'nama' => $nama,
    'periodeLabel' => $periodeLabel,
    'tglTransferLabel' => $tglTransferLabel,
])->render();

SendEmail::where('to', $to)
    ->where('subject', 'Payroll ' . $periodeLabel)
    ->where('body', $body)
    ->where('attachment', [
        [
            'path' => $attachmentPublicPath,
            'name' => basename($pdfPath),
        ],
    ])
    ->noReply('Payroll ISL')
    ->send();

echo "OK: Payroll {$periodeLabel} + PDF ke {$to} (karyawan id {$karyawanId}, payroll id {$payroll->id})\n";

<?php

/**
 * Kembalikan permohonan Achmad Maulana Alawiyyy ke tab Menunggu Assessment.
 *
 * - status: hrd_processing (siap Generate Link)
 * - hapus assessment + sesi + konseling (reset penuh)
 *
 * Usage:
 *   php database/scripts/restore_achmad_menunggu_assessment.php
 *   php database/scripts/restore_achmad_menunggu_assessment.php --run
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->boot();

use App\Services\SalaryAdjustmentWorkflowService;
use Illuminate\Support\Facades\DB;

$execute = in_array('--run', $argv, true);
$requestIds = [6, 7];
$employeeName = 'Achmad Maulana Alawiyyy';

$rows = DB::table('salary_adjustment_requests as sar')
    ->join('master_karyawan as k', 'k.id', '=', 'sar.employee_id')
    ->whereIn('sar.id', $requestIds)
    ->where('k.nama_lengkap', $employeeName)
    ->select('sar.id', 'sar.no_document', 'sar.status')
    ->get();

if ($rows->count() !== 2) {
    echo "Harus ada 2 permohonan (id 6 & 7) untuk {$employeeName}. Ditemukan: {$rows->count()}\n";
    exit(1);
}

echo "Kembalikan ke Menunggu Assessment (status hrd_processing, assessment di-reset):\n";
foreach ($rows as $row) {
    echo "  - id={$row->id} {$row->no_document} (status sekarang: {$row->status})\n";
}

if (!$execute) {
    echo "\nDry-run. Jalankan dengan --run\n";
    exit(0);
}

DB::connection('mysql')->transaction(function () use ($requestIds) {
    DB::table('salary_adjustment_counselings')->whereIn('request_id', $requestIds)->delete();

    $assessmentIds = DB::table('salary_adjustment_assessments')
        ->whereIn('request_id', $requestIds)
        ->pluck('id')
        ->all();

    if ($assessmentIds) {
        DB::table('salary_adjustment_assessment_sessions')
            ->whereIn('assessment_id', $assessmentIds)
            ->delete();
        DB::table('salary_adjustment_assessments')
            ->whereIn('id', $assessmentIds)
            ->delete();
        echo 'Assessment dihapus: ' . implode(', ', $assessmentIds) . "\n";
    }

    DB::table('salary_adjustment_requests')->whereIn('id', $requestIds)->update([
        'status' => SalaryAdjustmentWorkflowService::STATUS_HRD_PROCESSING,
        'updated_by' => 'restore_menunggu_assessment',
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
});

echo "Selesai.\n";
echo "- UI: Konseling Karyawan → Menunggu Assessment (periode 2026)\n";
echo "- Aksi: Generate Link Assessment untuk masing-masing baris\n";

<?php

/**
 * Bersihkan data transaksi tes modul Penyesuaian Gaji.
 *
 * Usage:
 *   php database/scripts/cleanup_salary_adjustment_test_data.php          # preview
 *   php database/scripts/cleanup_salary_adjustment_test_data.php --run  # eksekusi
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->boot();

use App\Models\MasterSallary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$execute = in_array('--run', $argv, true);

$tables = [
    'salary_adjustment_requests',
    'salary_adjustment_kpi',
    'salary_adjustment_kpi_items',
    'salary_adjustment_status_logs',
    'salary_adjustment_assessments',
    'salary_adjustment_assessment_sessions',
    'salary_adjustment_counselings',
    'salary_adjustment_approval_tokens',
];

echo $execute ? "=== EKSEKUSI PEMBERSIHAN ===\n" : "=== PREVIEW (tambahkan --run untuk eksekusi) ===\n\n";

foreach ($tables as $table) {
    if (!Schema::hasTable($table)) {
        echo "{$table}: (tabel tidak ada)\n";
        continue;
    }
    echo "{$table}: " . DB::table($table)->count() . " baris\n";
}

if (Schema::hasTable('salary_adjustment_kpi_criteria')) {
    echo "salary_adjustment_kpi_criteria: " . DB::table('salary_adjustment_kpi_criteria')->count() . " baris (master — tidak dihapus)\n";
}

$requests = DB::table('salary_adjustment_requests')
    ->select('id', 'no_document', 'status', 'employee_id', 'master_salary_id', 'applied_at')
    ->orderBy('id')
    ->get();

echo "\nPermohonan (" . $requests->count() . "):\n";
foreach ($requests as $row) {
    echo "  #{$row->id} {$row->no_document} | {$row->status}";
    if ($row->master_salary_id) {
        echo " | master_salary_id={$row->master_salary_id}";
    }
    echo "\n";
}

$applied = $requests->filter(fn ($row) => !empty($row->master_salary_id));
if ($applied->isNotEmpty()) {
    echo "\nPermohonan selesai dengan perubahan master_sallary (akan di-rollback):\n";
    foreach ($applied as $row) {
        $ms = MasterSallary::find($row->master_salary_id);
        $prev = $ms && $ms->previous_id ? MasterSallary::find($ms->previous_id) : null;
        echo "  request #{$row->id}: aktif id={$row->master_salary_id}";
        if ($prev) {
            echo ", reaktivasi previous_id={$prev->id}";
        } else {
            echo ", nonaktifkan record baru (tanpa previous)";
        }
        echo "\n";
    }
}

$notificationUrls = [
    '/request/permohonan/penyesuaian-karyawan',
    '/hrd/permohonan/permohonan-penyesuaian-karyawan',
    '/finance/pengajuan-penyesuaian-gaji',
    '/hrd/hris/konseling-karyawan',
];

$notifCount = 0;
if (Schema::hasTable('notification')) {
    $notifCount = DB::table('notification')
        ->where(function ($query) use ($notificationUrls) {
            foreach ($notificationUrls as $url) {
                $query->orWhere('url', 'like', '%' . $url . '%');
            }
        })
        ->count();
    echo "\nNotifikasi terkait penyesuaian gaji: {$notifCount} baris\n";
}

$tempDir = base_path('public/temp/salary-adjustment');
$tempDirs = is_dir($tempDir) ? count(glob($tempDir . '/*', GLOB_ONLYDIR) ?: []) : 0;
echo "Folder temp PDF: {$tempDirs}\n";

if (!$execute) {
    echo "\nJalankan dengan --run untuk menghapus data di atas.\n";
    exit(0);
}

if ($requests->isEmpty() && $notifCount === 0 && $tempDirs === 0) {
    echo "\nTidak ada data tes yang perlu dibersihkan.\n";
    exit(0);
}

DB::transaction(function () use ($applied, $notificationUrls) {
    foreach ($applied as $row) {
        $appliedSalary = MasterSallary::find($row->master_salary_id);
        if (!$appliedSalary) {
            continue;
        }

        if ($appliedSalary->previous_id) {
            $previous = MasterSallary::find($appliedSalary->previous_id);
            if ($previous) {
                $previous->is_active = true;
                $previous->updated_by = 'cleanup_salary_adjustment_test';
                $previous->updated_at = date('Y-m-d H:i:s');
                $previous->save();
            }
        }

        $appliedSalary->is_active = false;
        $appliedSalary->updated_by = 'cleanup_salary_adjustment_test';
        $appliedSalary->updated_at = date('Y-m-d H:i:s');
        $appliedSalary->save();
    }

    $assessmentIds = DB::table('salary_adjustment_assessments')->pluck('id')->all();
    if (!empty($assessmentIds)) {
        DB::table('salary_adjustment_assessment_sessions')
            ->whereIn('assessment_id', $assessmentIds)
            ->delete();
    }

    $kpiIds = DB::table('salary_adjustment_kpi')->pluck('id')->all();
    if (!empty($kpiIds)) {
        DB::table('salary_adjustment_kpi_items')
            ->whereIn('kpi_id', $kpiIds)
            ->delete();
    }

    DB::table('salary_adjustment_assessments')->delete();
    DB::table('salary_adjustment_approval_tokens')->delete();
    DB::table('salary_adjustment_counselings')->delete();
    DB::table('salary_adjustment_kpi')->delete();
    DB::table('salary_adjustment_status_logs')->delete();
    DB::table('salary_adjustment_requests')->delete();

    if (Schema::hasTable('notification')) {
        DB::table('notification')
            ->where(function ($query) use ($notificationUrls) {
                foreach ($notificationUrls as $url) {
                    $query->orWhere('url', 'like', '%' . $url . '%');
                }
            })
            ->delete();
    }
});

if (is_dir($tempDir)) {
    foreach (glob($tempDir . '/*', GLOB_ONLYDIR) ?: [] as $batchDir) {
        foreach (glob($batchDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($batchDir);
    }
}

echo "\nPembersihan selesai.\n\nVerifikasi:\n";
foreach (['salary_adjustment_requests', 'salary_adjustment_status_logs', 'salary_adjustment_assessments'] as $table) {
    if (Schema::hasTable($table)) {
        echo "  {$table}: " . DB::table($table)->count() . " baris\n";
    }
}

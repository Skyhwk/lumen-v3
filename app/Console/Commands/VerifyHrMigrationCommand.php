<?php

namespace App\Console\Commands;

use App\Models\Hr\HrMigrationMap;
use App\Models\Hr\HrRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyHrMigrationCommand extends Command
{
    protected $signature = 'greatday:verify-hr-migration';

    protected $description = 'Bandingkan jumlah legacy vs hr_migration_map / hr_request (M3)';

    public function handle()
    {
        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $types = [
            'leave' => ['leave_requests', HrRequest::TYPE_LEAVE],
            'permission' => ['permission_requests', HrRequest::TYPE_PERMISSION],
            'overtime' => ['overtime_requests', HrRequest::TYPE_OVERTIME],
            'attendance' => ['attendance_corrections', HrRequest::TYPE_ATTENDANCE_CORRECTION],
            'consultation' => ['consultation_requests', HrRequest::TYPE_CONSULTATION],
            'event_report' => ['event_reports', HrRequest::TYPE_EVENT_REPORT],
        ];

        $ok = true;

        foreach ($types as $label => [$table, $requestType]) {
            $legacy = DB::connection($conn)->table($table)->where('is_active', true)->count();
            $mapped = HrMigrationMap::where('old_table', $table)->count();
            $hr = HrRequest::where('request_type', $requestType)->where('is_active', true)->count();

            $this->line(sprintf(
                '%s: legacy=%d mapped=%d hr_request=%d',
                $label,
                $legacy,
                $mapped,
                $hr
            ));

            if ($mapped < $legacy || $hr < $mapped) {
                $ok = false;
                $this->warn("  -> selisih pada {$label}");
            }
        }

        if ($ok) {
            $this->info('Verifikasi dasar OK.');
        } else {
            $this->error('Ada selisih — cek baris yang di-skip saat migrasi.');
        }

        return $ok ? 0 : 1;
    }
}

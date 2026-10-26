<?php

namespace App\Console\Commands;

use App\Models\Hr\HrMigrationMap;
use App\Models\Hr\HrRequest;
use App\Services\Hr\PortalHrSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyHrStatusParityCommand extends Command
{
    protected $signature = 'greatday:verify-hr-status-parity
                            {--limit=15 : Maks baris mismatch yang ditampilkan per tipe}
                            {--fix : Jalankan PortalHrSync untuk setiap mismatch}
                            {--recent-days= : Hanya baris legacy created_at >= N hari terakhir}';

    protected $description = 'Bandingkan status legacy intilab_apps vs hr_request (smoke dual-write / portal sync)';

    public function handle(PortalHrSync $sync): int
    {
        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $limit = max(1, (int) $this->option('limit'));
        $fix = (bool) $this->option('fix');
        $recentDays = $this->option('recent-days');
        $recentCutoff = null;
        if ($recentDays !== null && $recentDays !== '') {
            $recentCutoff = now()->subDays((int) $recentDays)->format('Y-m-d H:i:s');
        }

        $this->line('Config: HR_USE_LEGACY_TABLES=' . (config('greatday.use_legacy_hr_tables') ? 'true' : 'false')
            . ', HR_DUAL_WRITE_LEGACY=' . (config('greatday.dual_write_legacy_hr') ? 'true' : 'false'));

        $types = [
            'leave' => [
                'table' => 'leave_requests',
                'sync' => fn (int $id) => $sync->syncLeaveFromLegacy($id),
            ],
            'permission' => [
                'table' => 'permission_requests',
                'sync' => fn (int $id) => $sync->syncPermissionFromLegacy($id),
            ],
            'overtime' => [
                'table' => 'overtime_requests',
                'sync' => fn (int $id) => $sync->syncOvertimeFromLegacy($id),
            ],
        ];

        $totalMismatch = 0;
        $totalChecked = 0;

        foreach ($types as $label => $cfg) {
            $table = $cfg['table'];
            $maps = HrMigrationMap::where('old_table', $table)
                ->where('old_connection', 'intilab_apps')
                ->orderByDesc('old_id');

            if ($recentCutoff) {
                $legacyIds = DB::connection($conn)->table($table)
                    ->where('created_at', '>=', $recentCutoff)
                    ->pluck('id');
                $maps = $maps->whereIn('old_id', $legacyIds);
            }

            $maps = $maps->get(['old_id', 'new_id']);
            $checked = $maps->count();
            $totalChecked += $checked;

            $mismatches = [];
            foreach ($maps as $map) {
                $legacy = DB::connection($conn)->table($table)->where('id', $map->old_id)->first();
                $hr = HrRequest::find($map->new_id);
                if (!$legacy || !$hr) {
                    $mismatches[] = [
                        'legacy_id' => $map->old_id,
                        'hr_id' => $map->new_id,
                        'legacy_status' => $legacy->status ?? '(missing)',
                        'hr_status' => $hr->status ?? '(missing)',
                        'note' => 'row missing',
                    ];
                    continue;
                }

                $legacyStatus = (string) $legacy->status;
                $hrStatus = (string) $hr->status;
                if ($legacyStatus !== $hrStatus) {
                    $mismatches[] = [
                        'legacy_id' => (int) $map->old_id,
                        'hr_id' => (int) $map->new_id,
                        'legacy_status' => $legacyStatus,
                        'hr_status' => $hrStatus,
                        'note' => '',
                    ];
                }
            }

            $count = count($mismatches);
            $totalMismatch += $count;

            $this->line(sprintf('%s: checked=%d mismatch=%d', $label, $checked, $count));

            if ($fix && $count > 0) {
                foreach ($mismatches as $row) {
                    if ($row['legacy_id'] > 0) {
                        ($cfg['sync'])((int) $row['legacy_id']);
                    }
                }
                $this->info("  -> PortalHrSync: {$count} baris {$label}.");
            }

            $shown = 0;
            foreach ($mismatches as $row) {
                if ($shown >= $limit) {
                    break;
                }
                $this->warn(sprintf(
                    '  legacy#%d hr#%d legacy=%s hr=%s %s',
                    $row['legacy_id'],
                    $row['hr_id'],
                    $row['legacy_status'],
                    $row['hr_status'],
                    $row['note']
                ));
                $shown++;
            }
        }

        if ($fix && $totalMismatch > 0) {
            $this->info('Re-check setelah --fix...');
            return $this->call('greatday:verify-hr-status-parity', [
                '--limit' => $limit,
                '--recent-days' => $recentDays,
            ]);
        }

        if ($totalMismatch === 0) {
            $this->info("Parity OK ({$totalChecked} mapped rows).");

            return 0;
        }

        $this->error("Total mismatch: {$totalMismatch}. Jalankan dengan --fix untuk sinkron ulang dari legacy.");

        return 1;
    }
}

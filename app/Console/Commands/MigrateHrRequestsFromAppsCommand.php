<?php

namespace App\Console\Commands;

use App\Models\Greatday\LeaveRequest;
use App\Models\Greatday\OvertimeRequest;
use App\Models\Greatday\OvertimeRequestMember;
use App\Models\Greatday\PermissionRequest;
use App\Models\Hr\HrApprovalStep;
use App\Models\Hr\HrLeaveDetail;
use App\Models\Hr\HrMigrationMap;
use App\Models\Hr\HrOvertimeDetail;
use App\Models\Hr\HrOvertimeParticipant;
use App\Models\Hr\HrPermissionDetail;
use App\Models\Hr\HrRequest;
use App\Models\Hr\HrSpecialLeaveType;
use App\Models\MasterKaryawan;
use App\Services\Hr\WorkflowStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MigrateHrRequestsFromAppsCommand extends Command
{
    protected $signature = 'greatday:migrate-hr-from-apps
                            {--dry-run : Hanya hitung, tidak tulis}
                            {--type= : leave|permission|overtime|special_leave|all}';

    protected $description = 'Backfill intilab_apps form tables → intilab_produksi hr_* (Phase 2B M2)';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $type = $this->option('type') ?: 'all';

        if ($dryRun) {
            $this->warn('DRY RUN — tidak ada insert.');
        }

        if (in_array($type, ['all', 'special_leave'], true)) {
            $this->migrateSpecialLeaveTypes($dryRun);
        }
        if (in_array($type, ['all', 'leave'], true)) {
            $this->migrateLeave($dryRun);
        }
        if (in_array($type, ['all', 'permission'], true)) {
            $this->migratePermission($dryRun);
        }
        if (in_array($type, ['all', 'overtime'], true)) {
            $this->migrateOvertime($dryRun);
        }

        $this->info('Selesai.');

        return 0;
    }

    private function migrateSpecialLeaveTypes(bool $dryRun): void
    {
        $rows = DB::connection(config('greatday.legacy_apps_connection'))
            ->table('special_leave_types')
            ->get();
        $this->info('special_leave_types: ' . $rows->count());

        foreach ($rows as $row) {
            if (HrMigrationMap::where([
                'old_connection' => 'intilab_apps',
                'old_table' => 'special_leave_types',
                'old_id' => $row->id,
            ])->exists()) {
                continue;
            }

            if ($dryRun) {
                continue;
            }

            $new = HrSpecialLeaveType::create([
                'name' => $row->name,
                'duration' => $row->duration,
                'description' => $row->description,
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'updated_by' => $row->updated_by,
                'updated_at' => $row->updated_at,
                'is_active' => (bool) $row->is_active,
            ]);

            $this->mapLegacy('special_leave_types', $row->id, 'hr_special_leave_type', $new->id);
        }
    }

    private function migrateLeave(bool $dryRun): void
    {
        $query = LeaveRequest::on(config('greatday.legacy_apps_connection'))->orderBy('id');
        $this->info('leave_requests: ' . $query->count());

        $query->chunk(200, function ($chunk) use ($dryRun) {
            foreach ($chunk as $row) {
                if ($this->alreadyMapped('leave_requests', $row->id)) {
                    continue;
                }

                $karyawanId = (int) $row->employee_id;
                if (!MasterKaryawan::where('id', $karyawanId)->exists()) {
                    $this->error("Skip leave {$row->id}: karyawan {$karyawanId} tidak ada");
                    continue;
                }

                if ($dryRun) {
                    continue;
                }

                DB::transaction(function () use ($row, $karyawanId) {
                    $karyawan = MasterKaryawan::find($karyawanId);
                    $specialId = $this->mapSpecialLeaveId($row->special_leave_id);

                    $header = HrRequest::create([
                        'uuid' => (string) Str::uuid(),
                        'request_type' => HrRequest::TYPE_LEAVE,
                        'no_document' => $row->no_document,
                        'karyawan_id' => $karyawanId,
                        'id_cabang' => $karyawan->id_cabang ?? null,
                        'id_department' => $karyawan->id_department ?? null,
                        'status' => $row->status,
                        'workflow_code' => 'default_2_step',
                        'description' => $row->description,
                        'submitted_at' => $row->created_at,
                        'created_by_karyawan_id' => $karyawanId,
                        'created_by_name' => $row->created_by,
                        'created_at' => $row->created_at,
                        'updated_by_name' => $row->updated_by,
                        'updated_at' => $row->updated_at,
                        'is_active' => (bool) $row->is_active,
                    ]);

                    HrLeaveDetail::create([
                        'request_id' => $header->id,
                        'leave_kind' => $this->mapLeaveKind($row->type),
                        'special_leave_type_id' => $specialId,
                        'start_date' => $row->start_date,
                        'end_date' => $row->end_date,
                        'attachment_path' => $row->attachment,
                    ]);

                    $this->syncLegacyApprovalColumns($header->id, $row);
                    $this->mapLegacy('leave_requests', $row->id, 'hr_request', $header->id);
                });
            }
        });
    }

    private function migratePermission(bool $dryRun): void
    {
        $query = PermissionRequest::on(config('greatday.legacy_apps_connection'))->orderBy('id');
        $this->info('permission_requests: ' . $query->count());

        $query->chunk(200, function ($chunk) use ($dryRun) {
            foreach ($chunk as $row) {
                if ($this->alreadyMapped('permission_requests', $row->id)) {
                    continue;
                }

                $karyawanId = $this->resolvePermissionKaryawanId($row);
                if (!$karyawanId) {
                    $this->error("Skip permission {$row->id}: employee_id {$row->employee_id} tidak ter-resolve");
                    continue;
                }

                if ($dryRun) {
                    continue;
                }

                DB::transaction(function () use ($row, $karyawanId) {
                    $karyawan = MasterKaryawan::find($karyawanId);

                    $header = HrRequest::create([
                        'uuid' => (string) Str::uuid(),
                        'request_type' => HrRequest::TYPE_PERMISSION,
                        'no_document' => $row->no_document,
                        'karyawan_id' => $karyawanId,
                        'id_cabang' => $karyawan->id_cabang ?? null,
                        'id_department' => $karyawan->id_department ?? null,
                        'status' => $row->status,
                        'workflow_code' => 'default_2_step',
                        'description' => $row->description,
                        'submitted_at' => $row->created_at,
                        'created_by_karyawan_id' => $karyawanId,
                        'created_by_name' => $row->created_by,
                        'created_at' => $row->created_at,
                        'updated_by_name' => $row->updated_by,
                        'updated_at' => $row->updated_at,
                        'is_active' => (bool) $row->is_active,
                    ]);

                    HrPermissionDetail::create([
                        'request_id' => $header->id,
                        'permission_kind' => $this->mapPermissionKind($row->type),
                        'start_date' => $row->start_date,
                        'end_date' => $row->end_date,
                        'start_time' => $row->start_time,
                        'end_time' => $row->end_time,
                        'attachment_path' => $row->attachment,
                    ]);

                    $this->syncLegacyApprovalColumns($header->id, $row);
                    $this->mapLegacy('permission_requests', $row->id, 'hr_request', $header->id);
                });
            }
        });
    }

    private function migrateOvertime(bool $dryRun): void
    {
        $query = OvertimeRequest::on(config('greatday.legacy_apps_connection'))->orderBy('id');
        $this->info('overtime_requests: ' . $query->count());

        $query->chunk(100, function ($chunk) use ($dryRun) {
            foreach ($chunk as $row) {
                if ($this->alreadyMapped('overtime_requests', $row->id)) {
                    continue;
                }

                if ($dryRun) {
                    continue;
                }

                DB::transaction(function () use ($row) {
                    $header = HrRequest::create([
                        'uuid' => (string) Str::uuid(),
                        'request_type' => HrRequest::TYPE_OVERTIME,
                        'no_document' => $row->no_document,
                        'karyawan_id' => $this->guessOvertimeSubmitterKaryawanId($row),
                        'id_cabang' => null,
                        'id_department' => $row->department_id,
                        'status' => $row->status,
                        'workflow_code' => 'overtime_3_step_finance',
                        'description' => $row->description,
                        'submitted_at' => $row->created_at,
                        'created_by_karyawan_id' => null,
                        'created_by_name' => $row->created_by,
                        'created_at' => $row->created_at,
                        'updated_by_name' => $row->updated_by,
                        'updated_at' => $row->updated_at,
                        'is_active' => (bool) $row->is_active,
                    ]);

                    HrOvertimeDetail::create([
                        'request_id' => $header->id,
                        'start_date' => $row->start_date,
                        'end_date' => $row->end_date,
                        'start_time' => $row->start_time,
                        'end_time' => $row->end_time,
                    ]);

                    $members = OvertimeRequestMember::on(config('greatday.legacy_apps_connection'))
                        ->where('overtime_request_id', $row->id)
                        ->get();

                    foreach ($members as $member) {
                        HrOvertimeParticipant::create([
                            'request_id' => $header->id,
                            'karyawan_id' => $member->employee_id,
                            'is_active' => (bool) $member->is_active,
                            'created_at' => $member->created_at,
                            'updated_at' => $member->updated_at,
                        ]);
                    }

                    $this->syncLegacyApprovalColumns($header->id, $row, true);
                    $this->mapLegacy('overtime_requests', $row->id, 'hr_request', $header->id);
                });
            }
        });
    }

    private function resolvePermissionKaryawanId($row): ?int
    {
        $byId = MasterKaryawan::where('id', (int) $row->employee_id)->value('id');
        if ($byId) {
            return (int) $byId;
        }

        $byUserId = MasterKaryawan::where('user_id', (int) $row->employee_id)->value('id');

        return $byUserId ? (int) $byUserId : null;
    }

    private function guessOvertimeSubmitterKaryawanId($row): int
    {
        $member = OvertimeRequestMember::on(config('greatday.legacy_apps_connection'))
            ->where('overtime_request_id', $row->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($member) {
            return (int) $member->employee_id;
        }

        return 0;
    }

    private function mapLeaveKind(?string $type): string
    {
        if ($type === 'Special Leave') {
            return 'special';
        }
        if ($type === 'Unpaid Leave') {
            return 'unpaid';
        }

        return 'annual';
    }

    private function mapPermissionKind(?string $type): string
    {
        if ($type === 'Sick Leave') {
            return 'sick';
        }
        if ($type === 'Late Arrival') {
            return 'late';
        }

        return 'event';
    }

    private function mapSpecialLeaveId($legacyId): ?int
    {
        if (!$legacyId) {
            return null;
        }

        $map = HrMigrationMap::where([
            'old_connection' => 'intilab_apps',
            'old_table' => 'special_leave_types',
            'old_id' => $legacyId,
        ])->first();

        return $map ? (int) $map->new_id : (int) $legacyId;
    }

    private function syncLegacyApprovalColumns(int $requestId, $row, bool $withFinance = false): void
    {
        $this->upsertStep($requestId, HrApprovalStep::STEP_ATASAN, $row, 'atasan', $withFinance);
        $this->upsertStep($requestId, HrApprovalStep::STEP_HRD, $row, 'hrd', $withFinance);
        if ($withFinance) {
            $this->upsertStep($requestId, HrApprovalStep::STEP_FINANCE, $row, 'finance', true);
        }
    }

    private function upsertStep(int $requestId, string $step, $row, string $prefix, bool $finance): void
    {
        $approvedBy = $row->{"approved_{$prefix}_by"} ?? null;
        $rejectedBy = $row->{"rejected_{$prefix}_by"} ?? null;
        $state = HrApprovalStep::STATE_PENDING;
        $actorName = null;
        $actedAt = null;
        $reason = null;

        if ($approvedBy) {
            $state = HrApprovalStep::STATE_APPROVED;
            $actorName = $approvedBy;
            $actedAt = $row->{"approved_{$prefix}_at"} ?? null;
        } elseif ($rejectedBy) {
            $state = HrApprovalStep::STATE_REJECTED;
            $actorName = $rejectedBy;
            $actedAt = $row->{"rejected_{$prefix}_at"} ?? null;
            $reason = $row->{"reject_{$prefix}_reason"} ?? null;
        } elseif ($finance && $step === HrApprovalStep::STEP_FINANCE && $row->status === WorkflowStatus::PENDING) {
            return;
        }

        if (!$approvedBy && !$rejectedBy && $row->status === WorkflowStatus::PENDING && $step !== HrApprovalStep::STEP_ATASAN) {
            if ($step === HrApprovalStep::STEP_HRD && $row->status !== WorkflowStatus::APPROVED_ATASAN) {
                return;
            }
        }

        HrApprovalStep::create([
            'request_id' => $requestId,
            'step' => $step,
            'state' => $state,
            'actor_name' => $actorName,
            'acted_at' => $actedAt,
            'reason' => $reason,
        ]);
    }

    private function alreadyMapped(string $table, $oldId): bool
    {
        return HrMigrationMap::where([
            'old_connection' => 'intilab_apps',
            'old_table' => $table,
            'old_id' => $oldId,
        ])->exists();
    }

    private function mapLegacy(string $oldTable, $oldId, string $newTable, $newId): void
    {
        HrMigrationMap::create([
            'old_connection' => 'intilab_apps',
            'old_table' => $oldTable,
            'old_id' => $oldId,
            'new_table' => $newTable,
            'new_id' => $newId,
            'migrated_at' => Carbon::now(),
        ]);
    }
}

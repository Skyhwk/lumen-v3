<?php

namespace App\Console\Commands;

use App\Models\Hr\HrApprovalStep;
use App\Models\Hr\HrAttendanceCorrectionDetail;
use App\Models\Hr\HrConsultationDetail;
use App\Models\Hr\HrEventReportDetail;
use App\Models\Hr\HrMigrationMap;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateHrExtendedFormsFromAppsCommand extends Command
{
    protected $signature = 'greatday:migrate-hr-extended-from-apps
                            {--dry-run : Hanya hitung}
                            {--type= : attendance|consultation|event|all}';

    protected $description = 'Backfill attendance_corrections, consultation_requests, event_reports → hr_* (Phase 2C)';

    public function handle(): int
    {
        $type = $this->option('type') ?: 'all';
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN');
        }

        if (in_array($type, ['all', 'attendance'], true)) {
            $this->migrateAttendance($dryRun);
        }
        if (in_array($type, ['all', 'consultation'], true)) {
            $this->migrateConsultation($dryRun);
        }
        if (in_array($type, ['all', 'event'], true)) {
            $this->migrateEventReports($dryRun);
        }

        $this->info('Selesai.');

        return 0;
    }

    private function migrateAttendance(bool $dryRun): void
    {
        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $rows = DB::connection($conn)->table('attendance_corrections')->where('is_active', true)->orderBy('id')->get();
        $this->info('attendance_corrections: ' . $rows->count());

        foreach ($rows as $row) {
            if ($this->mapped('attendance_corrections', (int) $row->id)) {
                continue;
            }

            $karyawanId = (int) $row->employee_id;
            if (!MasterKaryawan::where('id', $karyawanId)->exists()) {
                $this->warn("Skip attendance {$row->id}: karyawan {$karyawanId} tidak ada");
                continue;
            }

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($row, $karyawanId) {
                $karyawan = MasterKaryawan::find($karyawanId);
                $noDoc = 'AC-' . $row->id . '/' . str_replace('-', '', (string) $row->date);
                $now = Carbon::now();

                $header = HrRequest::create([
                    'uuid' => (string) Str::uuid(),
                    'request_type' => HrRequest::TYPE_ATTENDANCE_CORRECTION,
                    'no_document' => $noDoc,
                    'karyawan_id' => $karyawanId,
                    'id_cabang' => $karyawan->id_cabang ?? null,
                    'id_department' => $karyawan->id_department ?? null,
                    'status' => $row->status ?? WorkflowStatus::PENDING,
                    'workflow_code' => 'default_2_step',
                    'description' => $row->description,
                    'submitted_at' => $row->created_at ?? $now,
                    'created_by_karyawan_id' => $karyawanId,
                    'created_by_name' => $row->created_by ?? 'System',
                    'created_at' => $row->created_at ?? $now,
                    'updated_by_name' => $row->updated_by ?? 'System',
                    'updated_at' => $row->updated_at ?? $now,
                    'is_active' => (bool) ($row->is_active ?? true),
                ]);

                HrAttendanceCorrectionDetail::create([
                    'request_id' => $header->id,
                    'correction_type' => $row->type,
                    'correction_date' => $row->date,
                    'correction_time' => $row->time,
                    'attachment_path' => $row->attachment,
                ]);

                $this->rebuildAtasanHrdSteps($header->id, $row, false);
                $this->mapLegacy('attendance_corrections', (int) $row->id, $header->id);
            });
        }
    }

    private function migrateConsultation(bool $dryRun): void
    {
        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $rows = DB::connection($conn)->table('consultation_requests')->where('is_active', true)->orderBy('id')->get();
        $this->info('consultation_requests: ' . $rows->count());

        foreach ($rows as $row) {
            if ($this->mapped('consultation_requests', (int) $row->id)) {
                continue;
            }

            $karyawanId = (int) $row->employee_id;
            if (!MasterKaryawan::where('id', $karyawanId)->exists()) {
                $this->warn("Skip consultation {$row->id}: karyawan {$karyawanId} tidak ada");
                continue;
            }

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($row, $karyawanId) {
                $karyawan = MasterKaryawan::find($karyawanId);
                $noDoc = 'CR-' . $row->id . '/' . str_replace('-', '', (string) $row->date);
                $now = Carbon::now();

                $header = HrRequest::create([
                    'uuid' => (string) Str::uuid(),
                    'request_type' => HrRequest::TYPE_CONSULTATION,
                    'no_document' => $noDoc,
                    'karyawan_id' => $karyawanId,
                    'id_cabang' => $karyawan->id_cabang ?? null,
                    'id_department' => $karyawan->id_department ?? null,
                    'status' => $row->status ?? 'Pending',
                    'workflow_code' => 'consultation_hr_counselling',
                    'description' => $row->description,
                    'submitted_at' => $row->created_at ?? $now,
                    'created_by_karyawan_id' => $karyawanId,
                    'created_by_name' => $row->created_by ?? 'System',
                    'created_at' => $row->created_at ?? $now,
                    'updated_by_name' => $row->updated_by ?? 'System',
                    'updated_at' => $row->updated_at ?? $now,
                    'is_active' => (bool) ($row->is_active ?? true),
                ]);

                HrConsultationDetail::create([
                    'request_id' => $header->id,
                    'consultation_type' => $row->type,
                    'consultation_date' => $row->date,
                    'consultation_time' => $row->time,
                ]);

                if ($row->status === 'Pending') {
                    HrApprovalStep::create([
                        'request_id' => $header->id,
                        'step' => HrApprovalStep::STEP_HRD,
                        'state' => HrApprovalStep::STATE_PENDING,
                    ]);
                } elseif ($row->status === 'Approved' && !empty($row->approved_by)) {
                    HrApprovalStep::create([
                        'request_id' => $header->id,
                        'step' => HrApprovalStep::STEP_HRD,
                        'state' => HrApprovalStep::STATE_APPROVED,
                        'actor_name' => $row->approved_by,
                        'acted_at' => $row->approved_at,
                    ]);
                } elseif ($row->status === 'Rejected' && !empty($row->rejected_by)) {
                    HrApprovalStep::create([
                        'request_id' => $header->id,
                        'step' => HrApprovalStep::STEP_HRD,
                        'state' => HrApprovalStep::STATE_REJECTED,
                        'actor_name' => $row->rejected_by,
                        'acted_at' => $row->rejected_at,
                        'reason' => $row->reject_reason,
                    ]);
                }

                $this->mapLegacy('consultation_requests', (int) $row->id, $header->id);
            });
        }
    }

    private function migrateEventReports(bool $dryRun): void
    {
        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $rows = DB::connection($conn)->table('event_reports')->where('is_active', true)->orderBy('id')->get();
        $this->info('event_reports: ' . $rows->count());

        foreach ($rows as $row) {
            if ($this->mapped('event_reports', (int) $row->id)) {
                continue;
            }

            $karyawanId = (int) $row->employee_id;
            if (!MasterKaryawan::where('id', $karyawanId)->exists()) {
                $this->warn("Skip event {$row->id}: karyawan {$karyawanId} tidak ada");
                continue;
            }

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($row, $karyawanId) {
                $karyawan = MasterKaryawan::find($karyawanId);
                $noDoc = 'ER-' . $row->id . '/' . str_replace('-', '', (string) $row->date);
                $now = Carbon::now();

                $header = HrRequest::create([
                    'uuid' => (string) Str::uuid(),
                    'request_type' => HrRequest::TYPE_EVENT_REPORT,
                    'no_document' => $noDoc,
                    'karyawan_id' => $karyawanId,
                    'id_cabang' => $karyawan->id_cabang ?? null,
                    'id_department' => $karyawan->id_department ?? null,
                    'status' => 'Submitted',
                    'workflow_code' => 'event_report_log',
                    'description' => $row->description,
                    'submitted_at' => $row->created_at ?? $now,
                    'created_by_karyawan_id' => $karyawanId,
                    'created_by_name' => $row->created_by ?? 'System',
                    'created_at' => $row->created_at ?? $now,
                    'updated_by_name' => $row->updated_by ?? 'System',
                    'updated_at' => $row->updated_at ?? $now,
                    'is_active' => (bool) ($row->is_active ?? true),
                ]);

                HrEventReportDetail::create([
                    'request_id' => $header->id,
                    'subject' => $row->subject,
                    'event_date' => $row->date,
                    'event_time' => $row->time,
                    'attachment_path' => $row->attachment,
                ]);

                $this->mapLegacy('event_reports', (int) $row->id, $header->id);
            });
        }
    }

    private function rebuildAtasanHrdSteps(int $requestId, $row, bool $withFinance): void
    {
        HrApprovalStep::where('request_id', $requestId)->delete();
        $this->insertLegacyStep($requestId, HrApprovalStep::STEP_ATASAN, $row, 'atasan');
        $this->insertLegacyStep($requestId, HrApprovalStep::STEP_HRD, $row, 'hrd');
        if ($withFinance) {
            $this->insertLegacyStep($requestId, HrApprovalStep::STEP_FINANCE, $row, 'finance');
        }
    }

    private function insertLegacyStep(int $requestId, string $step, $row, string $prefix): void
    {
        $approvedBy = $row->{"approved_{$prefix}_by"} ?? null;
        $rejectedBy = $row->{"rejected_{$prefix}_by"} ?? null;

        if (!$approvedBy && !$rejectedBy) {
            if ($step === HrApprovalStep::STEP_ATASAN && ($row->status ?? '') === WorkflowStatus::PENDING) {
                HrApprovalStep::create([
                    'request_id' => $requestId,
                    'step' => $step,
                    'state' => HrApprovalStep::STATE_PENDING,
                ]);
            }

            return;
        }

        if ($approvedBy) {
            HrApprovalStep::create([
                'request_id' => $requestId,
                'step' => $step,
                'state' => HrApprovalStep::STATE_APPROVED,
                'actor_name' => $approvedBy,
                'acted_at' => $row->{"approved_{$prefix}_at"} ?? null,
            ]);
        } else {
            HrApprovalStep::create([
                'request_id' => $requestId,
                'step' => $step,
                'state' => HrApprovalStep::STATE_REJECTED,
                'actor_name' => $rejectedBy,
                'acted_at' => $row->{"rejected_{$prefix}_at"} ?? null,
                'reason' => $row->{"reject_{$prefix}_reason"} ?? null,
            ]);
        }
    }

    private function mapped(string $table, int $oldId): bool
    {
        return HrMigrationMap::where([
            'old_connection' => 'intilab_apps',
            'old_table' => $table,
            'old_id' => $oldId,
        ])->exists();
    }

    private function mapLegacy(string $table, int $oldId, int $newId): void
    {
        HrMigrationMap::create([
            'old_connection' => 'intilab_apps',
            'old_table' => $table,
            'old_id' => $oldId,
            'new_table' => 'hr_request',
            'new_id' => $newId,
            'migrated_at' => Carbon::now(),
        ]);
    }
}

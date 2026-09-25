<?php

namespace App\Services;

use App\Jobs\NonaktifKaryawanJob;
use App\Models\EmployeeAdjustmentApplyLog;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Models\SalaryAdjustmentRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeAdjustmentApplyOrchestrator
{
    /** @var EmployeeAdjustmentWorkflowResolver */
    private $workflowResolver;

    /** @var EmployeeAdjustmentRekapService */
    private $rekapService;

    /** @var SalaryAdjustmentApplyService */
    private $salaryApplyService;

    public function __construct(
        EmployeeAdjustmentWorkflowResolver $workflowResolver = null,
        EmployeeAdjustmentRekapService $rekapService = null,
        SalaryAdjustmentApplyService $salaryApplyService = null
    ) {
        $this->workflowResolver = $workflowResolver ?: new EmployeeAdjustmentWorkflowResolver();
        $this->rekapService = $rekapService ?: new EmployeeAdjustmentRekapService();
        $this->salaryApplyService = $salaryApplyService ?: new SalaryAdjustmentApplyService();
    }

    public function scheduleOrApplyAfterBapakApproval(SalaryAdjustmentRequest $record, string $actorLabel): string
    {
        $scheduledAt = $record->scheduled_apply_at
            ? Carbon::parse($record->scheduled_apply_at)->startOfDay()
            : null;

        if (!$this->workflowResolver->shouldApplyImmediately($scheduledAt)) {
            $record->apply_status = EmployeeAdjustmentWorkflowResolver::APPLY_STATUS_PENDING;
            $record->master_apply_error = null;

            return 'Apply ditunda hingga ' . $scheduledAt->format('d/m/Y');
        }

        $result = $this->applyRecord($record, $actorLabel, 'approval');

        return $result['message'];
    }

    /**
     * @return array{processed: int, applied: int, failed: int, skipped: int, details: array<int, array<string, mixed>>}
     */
    public function applyDueRecords(Carbon $asOf, string $source = 'cron', bool $retryFailed = false): array
    {
        $summary = [
            'processed' => 0,
            'applied' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        $statuses = [EmployeeAdjustmentWorkflowResolver::APPLY_STATUS_PENDING];
        if ($retryFailed) {
            $statuses[] = EmployeeAdjustmentWorkflowResolver::APPLY_STATUS_FAILED;
        }

        SalaryAdjustmentRequest::query()
            ->where('status', SalaryAdjustmentWorkflowService::STATUS_COMPLETED)
            ->where('is_active', true)
            ->whereIn('apply_status', $statuses)
            ->where(function ($query) use ($asOf) {
                $query->whereNull('scheduled_apply_at')
                    ->orWhereDate('scheduled_apply_at', '<=', $asOf->toDateString());
            })
            ->orderBy('id')
            ->chunkById(50, function ($records) use (&$summary, $source) {
                foreach ($records as $record) {
                    $summary['processed']++;
                    $result = $this->applyRecord($record, 'System Cron', $source);
                    $summary['details'][] = [
                        'request_id' => $record->id,
                        'no_document' => $record->no_document,
                        'request_type' => $record->request_type,
                        'success' => $result['success'],
                        'message' => $result['message'],
                    ];

                    if ($result['success']) {
                        $summary['applied']++;
                    } elseif ($result['skipped'] ?? false) {
                        $summary['skipped']++;
                    } else {
                        $summary['failed']++;
                    }
                }
            });

        return $summary;
    }

    /**
     * @return array{success: bool, skipped?: bool, message: string}
     */
    public function applyRecord(SalaryAdjustmentRequest $record, string $appliedBy, string $source = 'manual'): array
    {
        if ($record->apply_status === EmployeeAdjustmentWorkflowResolver::APPLY_STATUS_APPLIED) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Permohonan sudah di-apply sebelumnya',
            ];
        }

        if ($record->status !== SalaryAdjustmentWorkflowService::STATUS_COMPLETED) {
            return [
                'success' => false,
                'message' => 'Permohonan belum disetujui Waiting Approval Final',
            ];
        }

        $scheduledAt = $record->scheduled_apply_at
            ? Carbon::parse($record->scheduled_apply_at)->startOfDay()
            : null;

        if (!$this->workflowResolver->shouldApplyImmediately($scheduledAt)) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Belum jatuh tempo apply',
            ];
        }

        $requestType = EmployeeAdjustmentTypeRegistry::normalizeType($record->request_type);

        try {
            $result = DB::connection('mysql')->transaction(function () use ($record, $appliedBy, $source) {
                $locked = SalaryAdjustmentRequest::where('id', $record->id)
                    ->lockForUpdate()
                    ->first();

                if (!$locked || $locked->apply_status === EmployeeAdjustmentWorkflowResolver::APPLY_STATUS_APPLIED) {
                    return [
                        'success' => true,
                        'skipped' => true,
                        'message' => 'Permohonan sudah di-apply sebelumnya',
                    ];
                }

                $employee = MasterKaryawan::where('id', $locked->employee_id)->lockForUpdate()->first();
                if (!$employee) {
                    throw new \RuntimeException('Data karyawan tidak ditemukan');
                }

                $before = $this->captureEmployeeSnapshot($employee);
                $salaryBefore = $this->rekapService->captureSalarySnapshot($employee);
                $before = array_merge($before, $salaryBefore);

                $locked->loadMissing(['kpi', 'newJabatan']);

                $this->applyEmployeeChanges($locked, $employee, $appliedBy, $source, $before);

                $masterSalaryId = null;
                if ($this->workflowResolver->recordHasSalaryAdjustment($locked)) {
                    $masterSalaryId = $this->salaryApplyService->apply($locked, $appliedBy);
                    $locked->master_salary_id = $masterSalaryId;

                    $this->writeApplyLog(
                        $locked,
                        'salary',
                        'master_sallary',
                        json_encode([
                            'gaji_pokok' => $before['gaji_lama'],
                            'tunjangan_kerja' => $before['tunjangan_lama'],
                        ]),
                        json_encode([
                            'gaji_pokok' => (float) $locked->requested_gaji_pokok,
                            'tunjangan_kerja' => (float) $locked->requested_tunjangan_kerja,
                            'master_salary_id' => $masterSalaryId,
                        ]),
                        $appliedBy,
                        $source
                    );
                }

                $employee->refresh();
                $afterSnapshot = $this->buildAfterSnapshot($before, $employee, $locked, $source, $masterSalaryId);

                $appliedAt = Carbon::now();
                $this->rekapService->upsertFromApply(
                    $locked,
                    $employee,
                    array_merge($before, $afterSnapshot),
                    $masterSalaryId,
                    $appliedAt
                );

                $locked->apply_status = EmployeeAdjustmentWorkflowResolver::APPLY_STATUS_APPLIED;
                $locked->applied_by = $appliedBy;
                $locked->applied_at = $appliedAt;
                $locked->master_apply_error = null;
                $locked->updated_by = $appliedBy;
                $locked->save();

                SalaryAdjustmentLogService::log(
                    $locked->id,
                    $locked->status,
                    $locked->status,
                    'master_apply',
                    null,
                    $appliedBy,
                    'Perubahan master karyawan/gaji diterapkan (' . EmployeeAdjustmentTypeRegistry::label($locked->request_type) . ')'
                );

                return [
                    'success' => true,
                    'message' => 'Apply berhasil',
                ];
            });

            if ($result['success'] && $this->isDeactivationType($requestType)) {
                $employee = MasterKaryawan::find($record->employee_id);
                if ($employee) {
                    dispatch(new NonaktifKaryawanJob($employee));
                }
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('Employee adjustment apply failed', [
                'request_id' => $record->id,
                'request_type' => $record->request_type,
                'message' => $e->getMessage(),
            ]);

            $record->apply_status = EmployeeAdjustmentWorkflowResolver::APPLY_STATUS_FAILED;
            $record->master_apply_error = $e->getMessage();
            $record->save();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $before
     */
    private function applyEmployeeChanges(
        SalaryAdjustmentRequest $record,
        MasterKaryawan $employee,
        string $appliedBy,
        string $source,
        array $before
    ): void {
        $type = EmployeeAdjustmentTypeRegistry::normalizeType($record->request_type);
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $employeeChanged = false;

        switch ($type) {
            case EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI:
                break;

            case EmployeeAdjustmentTypeRegistry::TYPE_PROMOSI:
            case EmployeeAdjustmentTypeRegistry::TYPE_DEMOSI:
                $this->applyJabatanChange($record, $employee, $appliedBy, $source);
                $employeeChanged = true;
                break;

            case EmployeeAdjustmentTypeRegistry::TYPE_PENGANGKATAN_TETAP:
                $this->applyStatusKaryawanChange(
                    $record,
                    $employee,
                    EmployeeAdjustmentTypeRegistry::STATUS_KARYAWAN_PERMANENT,
                    $appliedBy,
                    $source,
                    $before
                );
                $employeeChanged = true;
                break;

            case EmployeeAdjustmentTypeRegistry::TYPE_PENGANGKATAN_KONTRAK:
                $this->applyStatusKaryawanChange(
                    $record,
                    $employee,
                    EmployeeAdjustmentTypeRegistry::STATUS_KARYAWAN_CONTRACT,
                    $appliedBy,
                    $source,
                    $before
                );
                $this->applyKontrakEndDate($record, $employee, $appliedBy, $source, $before);
                $employeeChanged = true;
                break;

            case EmployeeAdjustmentTypeRegistry::TYPE_PERPANJANG_KONTRAK:
                $this->applyKontrakEndDate($record, $employee, $appliedBy, $source, $before);
                $employeeChanged = true;
                break;

            case EmployeeAdjustmentTypeRegistry::TYPE_PERPANJANG_PELATIHAN:
                $this->applyStatusKaryawanChange(
                    $record,
                    $employee,
                    EmployeeAdjustmentTypeRegistry::STATUS_KARYAWAN_TRAINING,
                    $appliedBy,
                    $source,
                    $before
                );
                $this->applyKontrakEndDate($record, $employee, $appliedBy, $source, $before);
                $employeeChanged = true;
                break;

            case EmployeeAdjustmentTypeRegistry::TYPE_GAGAL_PELATIHAN:
                $this->deactivateEmployee($record, $employee, $appliedBy, $source, 'Gagal Masa Pelatihan');
                $employeeChanged = true;
                break;

            case EmployeeAdjustmentTypeRegistry::TYPE_PHK:
                $this->deactivateEmployee($record, $employee, $appliedBy, $source, 'PHK');
                $employeeChanged = true;
                break;

            case EmployeeAdjustmentTypeRegistry::TYPE_PENSIUN:
                $this->deactivateEmployee($record, $employee, $appliedBy, $source, 'Pensiun');
                $employeeChanged = true;
                break;

            case EmployeeAdjustmentTypeRegistry::TYPE_MUTASI:
                $this->applyJabatanChange($record, $employee, $appliedBy, $source);
                $this->applyMutasi($record, $employee, $appliedBy, $source, $before);
                $employeeChanged = true;
                break;

            default:
                throw new \RuntimeException('Tipe permohonan tidak dikenali untuk apply: ' . $type);
        }

        if ($employeeChanged) {
            $employee->updated_by = $appliedBy;
            $employee->updated_at = $now;
            $employee->save();
        }
    }

    private function applyJabatanChange(
        SalaryAdjustmentRequest $record,
        MasterKaryawan $employee,
        string $appliedBy,
        string $source
    ): void {
        if (!$record->new_jabatan_id) {
            throw new \RuntimeException('Jabatan baru belum ditentukan');
        }

        $jabatan = MasterJabatan::where('id', $record->new_jabatan_id)->where('is_active', true)->first();
        if (!$jabatan) {
            throw new \RuntimeException('Jabatan baru tidak ditemukan');
        }

        $oldValue = json_encode([
            'id_jabatan' => $employee->id_jabatan,
            'jabatan' => KaryawanProfileService::resolveJabatan($employee),
        ]);

        $employee->id_jabatan = $jabatan->id;
        if (array_key_exists('jabatan', $employee->getAttributes())) {
            $employee->jabatan = $jabatan->nama_jabatan;
        }

        $this->writeApplyLog(
            $record,
            'jabatan',
            'id_jabatan',
            $oldValue,
            json_encode([
                'id_jabatan' => $jabatan->id,
                'jabatan' => $jabatan->nama_jabatan,
            ]),
            $appliedBy,
            $source
        );
    }

    /**
     * @param array<string, mixed> $before
     */
    private function applyStatusKaryawanChange(
        SalaryAdjustmentRequest $record,
        MasterKaryawan $employee,
        string $targetStatus,
        string $appliedBy,
        string $source,
        array $before
    ): void {
        $oldStatus = $before['status_karyawan_lama'] ?? $employee->status_karyawan;
        if ($oldStatus === $targetStatus) {
            return;
        }

        $employee->status_karyawan = $targetStatus;

        $this->writeApplyLog(
            $record,
            'status_karyawan',
            'status_karyawan',
            (string) $oldStatus,
            $targetStatus,
            $appliedBy,
            $source
        );
    }

    /**
     * @param array<string, mixed> $before
     */
    private function applyKontrakEndDate(
        SalaryAdjustmentRequest $record,
        MasterKaryawan $employee,
        string $appliedBy,
        string $source,
        array $before
    ): void {
        $newDate = $record->tanggal_selesai
            ? Carbon::parse($record->tanggal_selesai)->toDateString()
            : null;

        if (!$newDate) {
            throw new \RuntimeException('Tanggal selesai kontrak/pelatihan wajib diisi');
        }

        $oldDate = $before['tgl_berakhir_kontrak_lama'] ?? $employee->tgl_berakhir_kontrak;
        $employee->tgl_berakhir_kontrak = $newDate;

        $this->writeApplyLog(
            $record,
            'kontrak',
            'tgl_berakhir_kontrak',
            (string) ($oldDate ?? ''),
            $newDate,
            $appliedBy,
            $source
        );
    }

    /**
     * @param array<string, mixed> $before
     */
    private function applyMutasi(
        SalaryAdjustmentRequest $record,
        MasterKaryawan $employee,
        string $appliedBy,
        string $source,
        array $before
    ): void {
        if (!$record->receiver_manager_id) {
            throw new \RuntimeException('Manager penerima mutasi belum ditentukan');
        }

        $receiver = MasterKaryawan::find($record->receiver_manager_id);
        if (!$receiver || !$receiver->is_active) {
            throw new \RuntimeException('Manager penerima mutasi tidak ditemukan atau tidak aktif');
        }

        $oldAtasan = $employee->atasan_langsung;
        $newAtasan = json_encode([(string) $record->receiver_manager_id]);
        $employee->atasan_langsung = $newAtasan;

        if ($receiver->id_department && (int) $receiver->id_department !== (int) $employee->id_department) {
            $oldDepartment = $employee->id_department;
            $employee->id_department = $receiver->id_department;

            $this->writeApplyLog(
                $record,
                'mutasi',
                'id_department',
                (string) $oldDepartment,
                (string) $receiver->id_department,
                $appliedBy,
                $source
            );
        }

        $this->writeApplyLog(
            $record,
            'mutasi',
            'atasan_langsung',
            (string) $oldAtasan,
            $newAtasan,
            $appliedBy,
            $source
        );
    }

    private function deactivateEmployee(
        SalaryAdjustmentRequest $record,
        MasterKaryawan $employee,
        string $appliedBy,
        string $source,
        string $reason
    ): void {
        $effectiveDate = $record->tanggal_berakhir_kerja
            ?: $record->tanggal_efektif;

        if (!$effectiveDate) {
            throw new \RuntimeException('Tanggal berakhir kerja/efektif wajib diisi');
        }

        $effectiveDate = Carbon::parse($effectiveDate)->toDateString();
        $oldActive = $employee->is_active ? '1' : '0';

        $employee->effective_date = $effectiveDate;
        $employee->reason_non_active = $reason;
        $employee->notes = trim((string) ($record->catatan_tambahan ?? '')) ?: $employee->notes;
        $employee->active = false;
        $employee->is_active = false;

        PayrollRecordSyncService::deactivateForKaryawan($employee, $appliedBy);

        $user = User::where('id', $employee->user_id)->first();
        if ($user) {
            $user->is_active = false;
            $user->updated_by = $appliedBy;
            $user->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $user->save();
        }

        DB::connection('mysql')
            ->table('users')
            ->where('user_id', $employee->id)
            ->update([
                'is_active' => false,
                'updated_by' => $appliedBy,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

        $this->writeApplyLog(
            $record,
            'deactivate',
            'is_active',
            $oldActive,
            '0',
            $appliedBy,
            $source
        );
    }

    private function isDeactivationType(string $requestType): bool
    {
        return in_array($requestType, [
            EmployeeAdjustmentTypeRegistry::TYPE_GAGAL_PELATIHAN,
            EmployeeAdjustmentTypeRegistry::TYPE_PHK,
            EmployeeAdjustmentTypeRegistry::TYPE_PENSIUN,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function captureEmployeeSnapshot(MasterKaryawan $employee): array
    {
        return [
            'jabatan_lama' => KaryawanProfileService::resolveJabatan($employee),
            'status_karyawan_lama' => $employee->status_karyawan,
            'tgl_berakhir_kontrak_lama' => $employee->tgl_berakhir_kontrak,
        ];
    }

    /**
     * @param array<string, mixed> $before
     * @return array<string, mixed>
     */
    private function buildAfterSnapshot(
        array $before,
        MasterKaryawan $employee,
        SalaryAdjustmentRequest $record,
        string $source,
        ?int $masterSalaryId
    ): array {
        $after = $before;
        $after['jabatan_baru'] = KaryawanProfileService::resolveJabatan($employee);
        $after['status_karyawan_baru'] = $employee->status_karyawan;
        $after['tgl_berakhir_kontrak_baru'] = $employee->tgl_berakhir_kontrak;
        $after['apply_source'] = $source;

        if ($masterSalaryId) {
            $after['gaji_baru'] = (float) $record->requested_gaji_pokok;
            $after['tunjangan_baru'] = (float) $record->requested_tunjangan_kerja;
        }

        return $after;
    }

    private function writeApplyLog(
        SalaryAdjustmentRequest $record,
        string $applyType,
        ?string $fieldName,
        ?string $oldValue,
        ?string $newValue,
        string $appliedBy,
        string $source
    ): void {
        EmployeeAdjustmentApplyLog::create([
            'request_id' => $record->id,
            'request_type' => $record->request_type ?: EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI,
            'apply_type' => $applyType,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'applied_by' => $appliedBy,
            'source' => $source,
            'is_success' => true,
            'applied_at' => Carbon::now(),
        ]);
    }
}

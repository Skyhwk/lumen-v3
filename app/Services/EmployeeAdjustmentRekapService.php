<?php

namespace App\Services;

use App\Models\EmployeeAdjustmentRekap;
use App\Models\MasterKaryawan;
use App\Models\MasterSallary;
use App\Models\SalaryAdjustmentRequest;
use Carbon\Carbon;

class EmployeeAdjustmentRekapService
{
    /**
     * @param array<string, mixed> $afterSnapshot
     */
    public function upsertFromApply(
        SalaryAdjustmentRequest $record,
        MasterKaryawan $employee,
        array $afterSnapshot,
        ?int $masterSalaryId,
        Carbon $appliedAt
    ): EmployeeAdjustmentRekap {
        $manager = MasterKaryawan::find($record->requested_by_id);
        $receiver = $record->receiver_manager_id
            ? MasterKaryawan::find($record->receiver_manager_id)
            : null;

        $kpi = $record->relationLoaded('kpi') ? $record->kpi : $record->kpi()->first();
        $jabatanBaru = $afterSnapshot['jabatan_baru'] ?? null;

        if (!$jabatanBaru && $record->new_jabatan_id) {
            $jabatanBaru = optional($record->newJabatan)->nama_jabatan;
        }

        $payload = [
            'request_id' => $record->id,
            'request_type' => $record->request_type ?: EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI,
            'no_document' => $record->no_document,
            'employee_id' => $employee->id,
            'employee_nama' => $employee->nama_lengkap,
            'employee_nik' => $employee->nik_karyawan,
            'jabatan_lama' => $afterSnapshot['jabatan_lama'] ?? KaryawanProfileService::resolveJabatan($employee),
            'jabatan_baru' => $jabatanBaru,
            'manager_pengaju_id' => $record->requested_by_id,
            'manager_pengaju_nama' => $manager->nama_lengkap ?? $record->created_by,
            'manager_penerima_id' => $record->receiver_manager_id,
            'manager_penerima_nama' => $receiver->nama_lengkap ?? null,
            'status_karyawan_lama' => $afterSnapshot['status_karyawan_lama'] ?? $employee->getOriginal('status_karyawan'),
            'status_karyawan_baru' => $afterSnapshot['status_karyawan_baru'] ?? $employee->status_karyawan,
            'gaji_lama' => $afterSnapshot['gaji_lama'] ?? (float) $record->current_gaji_pokok,
            'gaji_baru' => $afterSnapshot['gaji_baru'] ?? (float) $record->requested_gaji_pokok,
            'tunjangan_lama' => $afterSnapshot['tunjangan_lama'] ?? (float) $record->current_tunjangan_kerja,
            'tunjangan_baru' => $afterSnapshot['tunjangan_baru'] ?? (float) $record->requested_tunjangan_kerja,
            'tanggal_efektif' => $record->tanggal_efektif,
            'tanggal_mulai' => $record->tanggal_mulai,
            'tanggal_selesai' => $record->tanggal_selesai,
            'tanggal_berakhir_kerja' => $record->tanggal_berakhir_kerja,
            'tgl_berakhir_kontrak_lama' => $afterSnapshot['tgl_berakhir_kontrak_lama'] ?? null,
            'tgl_berakhir_kontrak_baru' => $afterSnapshot['tgl_berakhir_kontrak_baru'] ?? $employee->tgl_berakhir_kontrak,
            'kpi_summary' => $kpi->summary ?? null,
            'kpi_score_avg' => $kpi->total_score_avg ?? null,
            'approved_bapak_at' => $record->bapak_approved_at,
            'applied_at' => $appliedAt,
            'payload_json' => [
                'master_salary_id' => $masterSalaryId,
                'receiver_salary_decision' => $record->receiver_salary_decision,
                'apply_source' => $afterSnapshot['apply_source'] ?? null,
            ],
            'is_active' => true,
        ];

        return EmployeeAdjustmentRekap::updateOrCreate(
            ['request_id' => $record->id],
            $payload
        );
    }

    public function captureSalarySnapshot(MasterKaryawan $employee): array
    {
        $salary = MasterSallary::where('is_active', true)
            ->where(function ($query) use ($employee) {
                $query->where('nik_karyawan', $employee->nik_karyawan)
                    ->orWhere('karyawan', $employee->nama_lengkap);
            })
            ->orderByDesc('id')
            ->first();

        return [
            'gaji_lama' => (float) ($salary->gaji_pokok ?? 0),
            'tunjangan_lama' => (float) ($salary->tunjangan_kerja ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatForDetail(SalaryAdjustmentRequest $record): array
    {
        $rekap = $record->relationLoaded('rekap') ? $record->rekap : $record->rekap()->first();
        if (!$rekap) {
            return [
                'apply_status' => $record->apply_status,
                'apply_status_label' => EmployeeAdjustmentWorkflowResolver::applyStatusLabel($record->apply_status),
                'scheduled_apply_at' => $record->scheduled_apply_at,
                'applied_at' => $record->applied_at,
                'master_apply_error' => $record->master_apply_error,
                'rekap' => null,
            ];
        }

        return [
            'apply_status' => $record->apply_status,
            'apply_status_label' => EmployeeAdjustmentWorkflowResolver::applyStatusLabel($record->apply_status),
            'scheduled_apply_at' => $record->scheduled_apply_at,
            'applied_at' => $record->applied_at ?: $rekap->applied_at,
            'master_apply_error' => $record->master_apply_error,
            'rekap' => [
                'jabatan_lama' => $rekap->jabatan_lama,
                'jabatan_baru' => $rekap->jabatan_baru,
                'status_karyawan_lama' => $rekap->status_karyawan_lama,
                'status_karyawan_baru' => $rekap->status_karyawan_baru,
                'gaji_lama' => $rekap->gaji_lama !== null ? (float) $rekap->gaji_lama : null,
                'gaji_baru' => $rekap->gaji_baru !== null ? (float) $rekap->gaji_baru : null,
                'tunjangan_lama' => $rekap->tunjangan_lama !== null ? (float) $rekap->tunjangan_lama : null,
                'tunjangan_baru' => $rekap->tunjangan_baru !== null ? (float) $rekap->tunjangan_baru : null,
                'tgl_berakhir_kontrak_lama' => $rekap->tgl_berakhir_kontrak_lama,
                'tgl_berakhir_kontrak_baru' => $rekap->tgl_berakhir_kontrak_baru,
                'tanggal_efektif' => $rekap->tanggal_efektif,
                'tanggal_berakhir_kerja' => $rekap->tanggal_berakhir_kerja,
                'manager_penerima_nama' => $rekap->manager_penerima_nama,
                'kpi_score_avg' => $rekap->kpi_score_avg !== null ? (float) $rekap->kpi_score_avg : null,
                'applied_at' => $rekap->applied_at,
            ],
        ];
    }
}

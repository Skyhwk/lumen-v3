<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Models\MasterSallary;
use App\Models\SalaryAdjustmentRequest;
use Carbon\Carbon;

class SalaryAdjustmentApplyService
{
    public function apply(SalaryAdjustmentRequest $record, string $appliedBy): int
    {
        $employee = MasterKaryawan::find($record->employee_id);
        if (!$employee) {
            throw new \RuntimeException('Data karyawan tidak ditemukan');
        }

        $existing = MasterSallary::where('is_active', true)
            ->where(function ($query) use ($employee) {
                $query->where('nik_karyawan', $employee->nik_karyawan)
                    ->orWhere('karyawan', $employee->nama_lengkap);
            })
            ->orderByDesc('id')
            ->first();

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $newSalary = new MasterSallary();
        $newSalary->gaji_pokok = $record->requested_gaji_pokok;
        $newSalary->tunjangan_kerja = $record->requested_tunjangan_kerja;
        $newSalary->bulan_efektif = $record->bulan_efektif;
        $newSalary->created_by = $appliedBy;
        $newSalary->created_at = $now;
        $newSalary->is_active = true;

        if ($existing) {
            $existing->is_active = false;
            $existing->updated_by = $appliedBy;
            $existing->updated_at = $now;
            $existing->save();

            $newSalary->previous_id = $existing->id;
            $newSalary->nik_karyawan = $existing->nik_karyawan;
            $newSalary->karyawan = $existing->karyawan;
        } else {
            $newSalary->nik_karyawan = $employee->nik_karyawan;
            $newSalary->karyawan = $employee->nama_lengkap;
        }

        $newSalary->save();

        return (int) $newSalary->id;
    }
}

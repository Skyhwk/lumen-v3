<?php

namespace App\Services\Hr;

use App\Models\MasterKaryawan;
use App\Services\Greatday\AtasanApprovalScope;
use Illuminate\Database\Eloquent\Builder;

class GreatdayIndexScope
{
    public static function apply(Builder $query, MasterKaryawan $employee, string $karyawanColumn = 'karyawan_id'): Builder
    {
        return $query->where(function ($q) use ($employee, $karyawanColumn) {
            $q->where($karyawanColumn, $employee->id);

            if (AtasanApprovalScope::isAtasanGrade($employee)) {
                $subordinateIds = AtasanApprovalScope::subordinateKaryawanIds($employee);

                if (!empty($subordinateIds)) {
                    $q->orWhere(function ($sub) use ($subordinateIds, $karyawanColumn) {
                        $sub->where('status', WorkflowStatus::PENDING)
                            ->whereIn($karyawanColumn, $subordinateIds);
                    });
                }
            }
        });
    }

    /** Lembur: riwayat anggota + antrian Pending tim bawahan */
    public static function applyOvertime(Builder $query, MasterKaryawan $employee): Builder
    {
        return $query->where(function ($q) use ($employee) {
            $q->whereHas('overtimeParticipants', fn ($p) => $p->where('karyawan_id', $employee->id))
                ->orWhere('created_by_name', $employee->nama_lengkap);

            if (AtasanApprovalScope::isAtasanGrade($employee)) {
                $subordinateIds = AtasanApprovalScope::subordinateKaryawanIds($employee);
                $subordinateNames = AtasanApprovalScope::subordinateKaryawanNames($employee);

                if (!empty($subordinateIds) || !empty($subordinateNames)) {
                    $q->orWhere(function ($sub) use ($subordinateIds, $subordinateNames) {
                        $sub->where('status', WorkflowStatus::PENDING)
                            ->where(function ($inner) use ($subordinateIds, $subordinateNames) {
                                if (!empty($subordinateIds)) {
                                    $inner->whereIn('karyawan_id', $subordinateIds)
                                        ->orWhereHas('overtimeParticipants', fn ($p) => $p->whereIn('karyawan_id', $subordinateIds));
                                }
                                if (!empty($subordinateNames)) {
                                    $inner->orWhereIn('created_by_name', $subordinateNames);
                                }
                            });
                    });
                }
            }
        });
    }
}

<?php

namespace App\Services\Greatday;

use App\Models\MasterKaryawan;
use App\Services\ProfileEmployeeDetailService;
use App\Support\Greatday\KaryawanPresentation;

class MembersEmployeeDetailService
{
    /**
     * Detail master karyawan untuk Greatday (scope + field tambahan).
     *
     * @return array|null
     */
    public function build(MasterKaryawan $viewer, $targetId)
    {
        $targetId = (int) $targetId;
        if ($targetId <= 0 || !$this->canView($viewer, $targetId)) {
            return null;
        }

        $detail = app(ProfileEmployeeDetailService::class)->build($targetId);
        if (!$detail) {
            return null;
        }

        $atasanIds = isset($detail['employee']['dsupervisor']) ? $detail['employee']['dsupervisor'] : [];
        if (is_array($atasanIds) && $atasanIds !== []) {
            $detail['nama_atasan'] = MasterKaryawan::whereIn('id', $atasanIds)
                ->where('is_active', 1)
                ->pluck('nama_lengkap')
                ->implode(', ');
        } else {
            $detail['nama_atasan'] = null;
        }

        $detail['grade'] = isset($detail['employee']['grade']) ? $detail['employee']['grade'] : null;
        $detail['can_see_salary'] = $this->canSeeSalary($viewer, $targetId);
        $detail['salary'] = null;

        if ($detail['can_see_salary']) {
            $row = MasterKaryawan::with('salary')->where('id', $targetId)->first();
            if ($row && $row->salary) {
                $detail['salary'] = (int) $row->salary->gaji_pokok + (int) $row->salary->tunjangan_kerja;
            }
        }

        $detail['jabatan_label'] = isset($detail['jabatan']) ? $detail['jabatan'] : null;
        $detail['divisi_label'] = isset($detail['department']) ? $detail['department'] : null;

        if ((int) $viewer->id !== $targetId) {
            unset($detail['access']);
        }

        return $detail;
    }

    public function canView(MasterKaryawan $viewer, $targetId)
    {
        $targetId = (int) $targetId;
        $allowedIds = app(MembersHierarchyService::class)->stakeholderIds($viewer);
        if (!in_array($targetId, $allowedIds, true)) {
            return false;
        }

        $grade = $this->normalizeGrade($viewer->grade);

        if ($grade === 'STAFF' && (int) $viewer->id !== $targetId) {
            return false;
        }

        return true;
    }

    private function canSeeSalary(MasterKaryawan $viewer, $targetId)
    {
        $targetId = (int) $targetId;
        $viewerId = (int) $viewer->id;
        $grade = strtoupper(trim((string) ($viewer->grade ?? '')));

        if (in_array($viewerId, [1], true)) {
            return true;
        }
        if (in_array($grade, ['MANAGER', 'SENIOR MANAGER'], true)) {
            return true;
        }

        return $viewerId === $targetId;
    }

    private function normalizeGrade($grade)
    {
        $grade = strtoupper(trim((string) $grade));

        return $grade === 'SPV' ? 'SUPERVISOR' : $grade;
    }
}

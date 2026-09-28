<?php

namespace App\Services\Greatday;

use App\Models\MasterKaryawan;

/**
 * Rantai persetujuan atasan dari hirarki atasan_langsung (SPV → Manager → Senior Manager).
 * Tidak melibatkan grade di atas Senior Manager (mis. Director).
 */
class DynamicApprovalChainService
{
    /** @var list<string> */
    private const APPROVER_GRADES = ['SUPERVISOR', 'MANAGER', 'SENIOR MANAGER'];

    /** @var list<string> */
    private const BLOCKED_GRADES = ['DIRECTOR', 'DIREKTUR', 'CEO', 'COMMISSIONER', 'PRESIDENT'];

    /**
     * @return list<int> karyawan_id urut dari atasan terdekat ke paling atas (max SM)
     */
    public function orderedApproverIds(MasterKaryawan $submitter): array
    {
        $submitterRank = $this->gradeRank($this->normalizeGrade($submitter->grade ?? ''));
        $chain = [];
        $visited = [];
        $frontier = $this->directAtasanIds($submitter);
        $depth = 0;

        while ($frontier !== [] && $depth < 12) {
            $depth++;
            $nextFrontier = [];

            usort($frontier, function ($a, $b) {
                return $this->gradeRank($this->loadGrade($a)) <=> $this->gradeRank($this->loadGrade($b));
            });

            foreach ($frontier as $atasanId) {
                $atasanId = (int) $atasanId;
                if ($atasanId <= 0 || isset($visited[$atasanId])) {
                    continue;
                }
                $visited[$atasanId] = true;

                $person = MasterKaryawan::query()
                    ->where('id', $atasanId)
                    ->where('is_active', true)
                    ->first();

                if (!$person || (int) $person->id === 1) {
                    continue;
                }

                $grade = $this->normalizeGrade($person->grade ?? '');
                if ($this->isBlockedGrade($grade)) {
                    continue;
                }

                if ($this->isApproverGrade($grade) && $this->gradeRank($grade) > $submitterRank) {
                    $chain[] = (int) $person->id;
                }

                foreach ($this->directAtasanIds($person) as $upId) {
                    $upId = (int) $upId;
                    if ($upId > 0 && !isset($visited[$upId])) {
                        $nextFrontier[] = $upId;
                    }
                }
            }

            $frontier = array_values(array_unique($nextFrontier));
        }

        return array_values(array_unique($chain));
    }

    /** @return list<int> */
    private function directAtasanIds(MasterKaryawan $employee): array
    {
        $raw = $employee->atasan_langsung ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return [];
        }

        $ids = [];
        foreach ($decoded as $id) {
            $ids[] = (int) $id;
        }

        return array_values(array_filter(array_unique($ids), fn ($id) => $id > 0));
    }

    private function loadGrade(int $karyawanId): string
    {
        static $cache = [];
        if (!isset($cache[$karyawanId])) {
            $cache[$karyawanId] = $this->normalizeGrade(
                MasterKaryawan::query()->where('id', $karyawanId)->value('grade') ?? ''
            );
        }

        return $cache[$karyawanId];
    }

    private function normalizeGrade(?string $grade): string
    {
        $g = strtoupper(trim((string) $grade));
        $g = str_replace('_', ' ', $g);
        if ($g === 'SPV') {
            return 'SUPERVISOR';
        }

        return $g;
    }

    private function isApproverGrade(string $grade): bool
    {
        return in_array($grade, self::APPROVER_GRADES, true);
    }

    private function isBlockedGrade(string $grade): bool
    {
        foreach (self::BLOCKED_GRADES as $blocked) {
            if ($grade === $blocked || str_contains($grade, $blocked)) {
                return true;
            }
        }

        return false;
    }

    private function gradeRank(string $grade): int
    {
        if ($grade === 'SUPERVISOR') {
            return 1;
        }
        if ($grade === 'MANAGER') {
            return 2;
        }
        if ($grade === 'SENIOR MANAGER') {
            return 3;
        }

        return 99;
    }
}

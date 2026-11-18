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
    /**
     * @param list<int>|null $initialAtasanIds batasi BFS level-1 (mis. hanya atasan HO, tanpa SPV cabang)
     * @return list<int>
     */
    public function orderedApproverIds(MasterKaryawan $submitter, ?array $initialAtasanIds = null): array
    {
        return $this->buildApproverChain(
            $submitter,
            $initialAtasanIds,
            $this->submitterRankForChain($submitter)
        );
    }

    /**
     * BAK: verifikasi atasan tanpa SPV — hanya Manager dan Senior Manager di hirarki penandatangan (jika ada).
     *
     * @return list<int>
     */
    public function orderedApproverIdsForBak(MasterKaryawan $submitter): array
    {
        $chain = $this->buildApproverChain($submitter, null, $this->submitterRankForChain($submitter));

        return array_values(array_filter(
            $chain,
            fn (int $id): bool => in_array($this->gradeRank($this->loadGrade($id)), [2, 3], true)
        ));
    }

    /**
     * @param list<int>|null $initialAtasanIds
     * @return list<int>
     */
    private function buildApproverChain(MasterKaryawan $submitter, ?array $initialAtasanIds, int $submitterRank): array
    {
        $chain = [];
        $visited = [];
        $frontier = $initialAtasanIds !== null
            ? array_values(array_filter(array_map('intval', $initialAtasanIds), fn ($id) => $id > 0))
            : $this->directAtasanIds($submitter);
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

                if (!$person || (int) $person->id === 1 || (int) $person->id === (int) $submitter->id) {
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

        $unique = array_values(array_unique($chain));
        usort($unique, function (int $a, int $b): int {
            $ra = $this->gradeRank($this->loadGrade($a));
            $rb = $this->gradeRank($this->loadGrade($b));
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return $a <=> $b;
        });

        return $unique;
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
        if (in_array($grade, self::APPROVER_GRADES, true)) {
            return true;
        }

        if (preg_match('/^SUPERVISOR(\s|$)/', $grade) === 1) {
            return true;
        }

        if (preg_match('/^MANAGER(\s|$)/', $grade) === 1) {
            return true;
        }

        return preg_match('/^SENIOR\s+MANAGER(\s|$)/', $grade) === 1;
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
        if ($grade === 'SUPERVISOR' || preg_match('/^SUPERVISOR(\s|$)/', $grade) === 1) {
            return 1;
        }
        if ($grade === 'MANAGER' || preg_match('/^MANAGER(\s|$)/', $grade) === 1) {
            return 2;
        }
        if ($grade === 'SENIOR MANAGER' || preg_match('/^SENIOR\s+MANAGER(\s|$)/', $grade) === 1) {
            return 3;
        }

        return 99;
    }

    /**
     * Rank pembanding untuk "atasan di atas pengaju".
     * Staff/non-SPV pakai 0 agar SPV (1) & Manager (2) ikut chain — bukan 99 yang membuat 1 > 99 selalu false.
     */
    private function submitterRankForChain(MasterKaryawan $submitter): int
    {
        $grade = $this->normalizeGrade($submitter->grade ?? '');
        if ($this->isApproverGrade($grade)) {
            return $this->gradeRank($grade);
        }

        return 0;
    }
}

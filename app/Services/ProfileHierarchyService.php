<?php

namespace App\Services;

use App\Models\MasterKaryawan;

class ProfileHierarchyService
{
    private const TOP_GRADES = ['DIREKSI', 'DIRECTOR', 'DIREKTUR'];

    private const GRADE_RANK = [
        'DIREKSI'        => 100,
        'DIRECTOR'       => 100,
        'DIREKTUR'       => 100,
        'SENIOR MANAGER' => 80,
        'MANAGER'        => 60,
        'SUPERVISOR'     => 40,
        'STAFF'          => 20,
    ];

    public function buildOrgChart(int $userId): array
    {
        $employee = MasterKaryawan::where('id', $userId)->where('is_active', 1)->first();

        if (!$employee) {
            return [
                'focus_id'   => $userId,
                'focus_name' => null,
                'tree'       => null,
            ];
        }

        $ancestorChain = $this->walkUpChain($employee);
        $descendants   = $this->buildDescendantTree($employee->id, $userId);
        $tree          = $this->mergeChainAndSubtree($employee, $ancestorChain, $descendants, $userId);

        return [
            'focus_id'   => $userId,
            'focus_name' => $employee->nama_lengkap,
            'tree'       => $tree,
        ];
    }

    /**
     * Naik ke atasan langsung berulang sampai puncak (Director/Direksi).
     *
     * @return MasterKaryawan[] immediate boss first, top leader last
     */
    private function walkUpChain(MasterKaryawan $employee): array
    {
        $chain   = [];
        $current = $employee;
        $visited = [(int) $employee->id => true];

        while (true) {
            $superior = $this->pickPrimarySuperior($current);

            if (!$superior || isset($visited[(int) $superior->id])) {
                break;
            }

            $chain[(int) $superior->id] = $superior;
            $visited[(int) $superior->id] = true;
            $current = $superior;

            if ($this->isTopGrade($superior)) {
                break;
            }
        }

        return array_values($chain);
    }

    private function pickPrimarySuperior(MasterKaryawan $employee): ?MasterKaryawan
    {
        $superiorIds = json_decode($employee->atasan_langsung, true);

        if (empty($superiorIds) || !is_array($superiorIds)) {
            return null;
        }

        $superiors = MasterKaryawan::whereIn('id', $superiorIds)
            ->where('is_active', 1)
            ->where('id', '!=', 1)
            ->get();

        if ($superiors->isEmpty()) {
            return null;
        }

        return $superiors->sortByDesc(fn ($item) => $this->gradeRank($item->grade))->first();
    }

    /**
     * Bangun subtree bawahan rekursif (hanya karyawan aktif).
     */
    private function buildDescendantTree(int $rootId, int $focusUserId): array
    {
        $subordinates = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $rootId)
            ->where('is_active', 1)
            ->orderBy('nama_lengkap')
            ->get();

        $result = [];

        foreach ($subordinates as $subordinate) {
            $result[] = $this->toNode(
                $subordinate,
                $this->buildDescendantTree($subordinate->id, $focusUserId),
                $focusUserId
            );
        }

        return $result;
    }

    /**
     * Gabungkan rantai atasan (garis lurus ke atas) + subtree bawahan user fokus.
     */
    private function mergeChainAndSubtree(
        MasterKaryawan $employee,
        array $ancestorChain,
        array $descendants,
        int $focusUserId
    ): array {
        $node = $this->toNode($employee, $descendants, $focusUserId);

        foreach ($ancestorChain as $superior) {
            $node = $this->toNode($superior, [$node], $focusUserId);
        }

        return $node;
    }

    private function toNode(MasterKaryawan $employee, array $children, int $focusUserId): array
    {
        return [
            'id'       => (int) $employee->id,
            'name'     => $employee->nama_lengkap,
            'grade'    => $this->normalizeGrade($employee->grade),
            'jabatan'  => $employee->jabatan ?? '',
            'image'    => $employee->image,
            'is_me'    => (int) $employee->id === $focusUserId,
            'children' => $children,
        ];
    }

    private function normalizeGrade(?string $grade): string
    {
        $grade = strtoupper(trim((string) $grade));

        return $grade !== '' ? $grade : 'STAFF';
    }

    private function gradeRank(?string $grade): int
    {
        $grade = $this->normalizeGrade($grade);

        return self::GRADE_RANK[$grade] ?? 10;
    }

    private function isTopGrade(MasterKaryawan $employee): bool
    {
        return in_array($this->normalizeGrade($employee->grade), self::TOP_GRADES, true);
    }
}

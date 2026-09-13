<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use Illuminate\Support\Collection;

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
        $tree          = $this->mergeChainAndSubtree($employee, $ancestorChain, $userId);

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
     * Rekan sejajar: grade sama + atasan langsung utama sama.
     */
    private function getPeerColleagues(MasterKaryawan $employee, MasterKaryawan $superior): Collection
    {
        $employeeGrade = $this->normalizeGrade($employee->grade);
        $superiorId    = (int) $superior->id;

        return MasterKaryawan::whereJsonContains('atasan_langsung', (string) $superiorId)
            ->where('is_active', 1)
            ->orderBy('nama_lengkap')
            ->get()
            ->filter(function ($peer) use ($employeeGrade, $superiorId) {
                if ($this->normalizeGrade($peer->grade) !== $employeeGrade) {
                    return false;
                }

                $peerSuperior = $this->pickPrimarySuperior($peer);

                return $peerSuperior && (int) $peerSuperior->id === $superiorId;
            })
            ->values();
    }

    /**
     * STAFF melihat rekan STAFF di bawah atasan langsung yang sama.
     */
    private function shouldShowPeerColleagues(MasterKaryawan $employee): bool
    {
        return $this->normalizeGrade($employee->grade) === 'STAFF';
    }

    /**
     * Node level user fokus. Untuk STAFF bisa lebih dari satu (sejajar).
     *
     * @return array<int, array>
     */
    private function buildFocusLevelNodes(MasterKaryawan $employee, int $focusUserId): array
    {
        $descendants = $this->buildDescendantTree($employee->id, $focusUserId);

        if (!$this->shouldShowPeerColleagues($employee)) {
            return [$this->toNode($employee, $descendants, $focusUserId)];
        }

        $superior = $this->pickPrimarySuperior($employee);
        if (!$superior) {
            return [$this->toNode($employee, $descendants, $focusUserId)];
        }

        $peers = $this->getPeerColleagues($employee, $superior);
        if ($peers->count() <= 1) {
            return [$this->toNode($employee, $descendants, $focusUserId)];
        }

        return $peers
            ->map(fn ($peer) => $this->toNode(
                $peer,
                $this->buildDescendantTree($peer->id, $focusUserId),
                $focusUserId
            ))
            ->values()
            ->all();
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
     * Gabungkan rantai atasan (garis lurus ke atas) + level fokus (bisa sejajar untuk STAFF).
     */
    private function mergeChainAndSubtree(
        MasterKaryawan $employee,
        array $ancestorChain,
        int $focusUserId
    ): array {
        $focusLevelNodes = $this->buildFocusLevelNodes($employee, $focusUserId);

        if (count($focusLevelNodes) === 1) {
            $node = $focusLevelNodes[0];

            foreach ($ancestorChain as $superior) {
                $node = $this->toNode($superior, [$node], $focusUserId);
            }

            return $node;
        }

        if (empty($ancestorChain)) {
            return $focusLevelNodes[0];
        }

        $remainingChain  = $ancestorChain;
        $immediateBoss   = array_shift($remainingChain);
        $node            = $this->toNode($immediateBoss, $focusLevelNodes, $focusUserId);

        foreach ($remainingChain as $superior) {
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

<?php

namespace App\Services\Greatday;

use App\Models\MasterKaryawan;
use App\Support\Greatday\KaryawanPresentation;
use Illuminate\Support\Collection;

/**
 * Scope daftar karyawan Greatday (Employees) sesuai grade viewer.
 * STAFF: rekan + atasan sampai SPV (tanpa Manager ke atas).
 * SUPERVISOR: Manager di atas + diri + Staff bawahan.
 * MANAGER / SENIOR MANAGER: hanya ke bawah (kedalaman berbeda).
 */
final class MembersHierarchyService
{
    private const GRADE_RANK = [
        'DIREKSI' => 100,
        'DIRECTOR' => 100,
        'DIREKTUR' => 100,
        'SENIOR MANAGER' => 80,
        'MANAGER' => 60,
        'SUPERVISOR' => 40,
        'STAFF' => 20,
    ];

    /**
     * ID karyawan dalam scope viewer — dipakai controller untuk satu query Eloquent + eager load.
     *
     * @return array<int, int>
     */
    public function stakeholderIds(MasterKaryawan $viewer)
    {
        return $this->stakeholders($viewer)
            ->pluck('id')
            ->unique()
            ->filter()
            ->map(function ($id) {
                return (int) $id;
            })
            ->values()
            ->all();
    }

    /** @return Collection */
    public function stakeholders(MasterKaryawan $viewer)
    {
        if ((int) $viewer->id === 1) {
            return MasterKaryawan::where('is_active', 1)
                ->orderBy('nama_lengkap')
                ->get();
        }

        $grade = $this->normalizeGrade($viewer->grade);

        if ($grade === 'STAFF') {
            return $this->scopeStaff($viewer);
        }
        if ($grade === 'SUPERVISOR') {
            return $this->scopeSupervisor($viewer);
        }
        if ($grade === 'MANAGER') {
            return $this->scopeDownward($viewer, 3);
        }
        if ($grade === 'SENIOR MANAGER') {
            return $this->scopeDownward($viewer, 5);
        }

        return $this->scopeDownward($viewer, 2);
    }

    /**
     * Pohon hierarki untuk mode leaderboard (hanya node dalam scope).
     *
     * @return array|null
     */
    public function buildTree(MasterKaryawan $viewer, Collection $scoped)
    {
        $scopedIds = $scoped->pluck('id')->map(function ($id) {
            return (int) $id;
        })->flip();
        if ($scopedIds->isEmpty()) {
            return null;
        }

        $byId = $scoped->keyBy('id');
        $childMap = [];
        foreach ($scoped as $member) {
            $parentId = $this->primaryParentIdInScope($member, $scopedIds);
            if ($parentId === null) {
                continue;
            }
            $childMap[$parentId][] = (int) $member->id;
        }

        $roots = $scoped
            ->filter(function (MasterKaryawan $m) use ($scopedIds) {
                return $this->primaryParentIdInScope($m, $scopedIds) === null;
            })
            ->sortByDesc(function (MasterKaryawan $m) {
                return $this->gradeRank($m->grade);
            })
            ->values();

        if ($roots->isEmpty()) {
            $focus = $byId->get($viewer->id);
            if (!$focus) {
                $focus = $scoped->first();
            }

            return $this->toTreeNode($focus, $viewer->id, $childMap, $byId);
        }

        if ($roots->count() === 1) {
            return $this->toTreeNode($roots->first(), $viewer->id, $childMap, $byId);
        }

        $service = $this;

        return [
            'id' => 0,
            'name' => 'Team',
            'grade' => '',
            'jabatan' => '',
            'image' => null,
            'is_me' => false,
            'children' => $roots
                ->map(function (MasterKaryawan $root) use ($service, $viewer, $childMap, $byId) {
                    return $service->toTreeNode($root, $viewer->id, $childMap, $byId);
                })
                ->values()
                ->all(),
        ];
    }

    /** @return Collection */
    private function scopeStaff(MasterKaryawan $viewer)
    {
        $people = collect([$viewer]);

        foreach ($this->decodeAtasanIds($viewer) as $atasanId) {
            $spv = MasterKaryawan::where('id', $atasanId)->where('is_active', 1)->first();
            if (!$spv || $this->normalizeGrade($spv->grade) !== 'SUPERVISOR') {
                continue;
            }

            $people->push($spv);
            $peers = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $spv->id)
                ->where('is_active', 1)
                ->get()
                ->filter(function (MasterKaryawan $peer) {
                    return $this->normalizeGrade($peer->grade) === 'STAFF';
                });

            $people = $people->merge($peers);
        }

        return $this->uniqueActive($people);
    }

    /** @return Collection */
    private function scopeSupervisor(MasterKaryawan $viewer)
    {
        $people = collect([$viewer]);

        foreach ($this->decodeAtasanIds($viewer) as $atasanId) {
            $boss = MasterKaryawan::where('id', $atasanId)->where('is_active', 1)->first();
            if (!$boss) {
                continue;
            }
            $bossGrade = $this->normalizeGrade($boss->grade);
            if (in_array($bossGrade, ['MANAGER', 'SENIOR MANAGER'], true)) {
                $people->push($boss);
            }
        }

        $staff = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $viewer->id)
            ->where('is_active', 1)
            ->get()
            ->filter(function (MasterKaryawan $row) {
                return $this->normalizeGrade($row->grade) === 'STAFF';
            });

        $people = $people->merge($staff);

        return $this->uniqueActive($people);
    }

    /** @return Collection */
    private function scopeDownward(MasterKaryawan $viewer, $maxDepth)
    {
        $ids = [(int) $viewer->id => true];
        $frontier = [(int) $viewer->id];
        $depth = 0;

        while ($frontier !== [] && $depth < $maxDepth) {
            $next = [];
            foreach ($frontier as $parentId) {
                $children = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $parentId)
                    ->where('is_active', 1)
                    ->get();
                foreach ($children as $child) {
                    $cid = (int) $child->id;
                    if (!isset($ids[$cid])) {
                        $ids[$cid] = true;
                        $next[] = $cid;
                    }
                }
            }
            $frontier = $next;
            $depth++;
        }

        return MasterKaryawan::whereIn('id', array_keys($ids))
            ->where('is_active', 1)
            ->orderBy('nama_lengkap')
            ->get();
    }

    /**
     * @param  Collection  $scopedIds  flipped id => index
     * @return int|null
     */
    private function primaryParentIdInScope(MasterKaryawan $member, Collection $scopedIds)
    {
        $candidates = [];
        foreach ($this->decodeAtasanIds($member) as $atasanId) {
            if ($scopedIds->has($atasanId)) {
                $candidates[] = $atasanId;
            }
        }

        if ($candidates === []) {
            return null;
        }

        $parents = MasterKaryawan::whereIn('id', $candidates)->where('is_active', 1)->get();

        $best = $parents->sortByDesc(function (MasterKaryawan $p) {
            return $this->gradeRank($p->grade);
        })->first();

        return $best ? (int) $best->id : null;
    }

    /**
     * @param  array  $childMap
     * @param  Collection  $byId
     * @return array
     */
    private function toTreeNode(
        MasterKaryawan $employee,
        $focusUserId,
        array $childMap,
        Collection $byId
    ) {
        $id = (int) $employee->id;
        $childIds = isset($childMap[$id]) ? $childMap[$id] : [];
        $service = $this;
        usort($childIds, function ($a, $b) use ($byId, $service) {
            $rowA = $byId->get($a);
            $rowB = $byId->get($b);
            $ga = $service->gradeRank($rowA ? $rowA->grade : null);
            $gb = $service->gradeRank($rowB ? $rowB->grade : null);
            if ($ga !== $gb) {
                return $gb <=> $ga;
            }

            $nameA = $rowA ? (string) $rowA->nama_lengkap : '';
            $nameB = $rowB ? (string) $rowB->nama_lengkap : '';

            return strcasecmp($nameA, $nameB);
        });

        $children = [];
        foreach ($childIds as $childId) {
            $child = $byId->get($childId);
            if ($child) {
                $children[] = $this->toTreeNode($child, $focusUserId, $childMap, $byId);
            }
        }

        return [
            'id' => $id,
            'name' => $employee->nama_lengkap,
            'grade' => $this->normalizeGrade($employee->grade),
            'jabatan' => KaryawanPresentation::jabatanLabel($employee) ?: '',
            'image' => $employee->image,
            'is_me' => $id === (int) $focusUserId,
            'children' => $children,
        ];
    }

    /** @return array<int, int> */
    private function decodeAtasanIds(MasterKaryawan $employee)
    {
        $raw = json_decode($employee->atasan_langsung, true);
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $raw), function ($id) {
            return $id > 0;
        }));
    }

    /** @param  Collection  $people */
    private function uniqueActive(Collection $people)
    {
        return $people
            ->filter(function ($row) {
                return $row instanceof MasterKaryawan && (int) $row->is_active === 1;
            })
            ->unique('id')
            ->sortBy('nama_lengkap')
            ->values();
    }

    private function normalizeGrade($grade)
    {
        $grade = strtoupper(trim((string) $grade));
        if ($grade === 'SPV') {
            return 'SUPERVISOR';
        }

        return $grade !== '' ? $grade : 'STAFF';
    }

    private function gradeRank($grade)
    {
        $normalized = $this->normalizeGrade($grade);

        return isset(self::GRADE_RANK[$normalized]) ? self::GRADE_RANK[$normalized] : 10;
    }
}

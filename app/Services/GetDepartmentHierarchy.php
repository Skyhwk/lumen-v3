<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use Illuminate\Support\Collection;

class GetDepartmentHierarchy
{
    public function getByDepartment(
        string $department,
        string $mode = 'tree',
        int $maxLevel = 3
    ): Collection {

        $employees = MasterKaryawan::where('department', $department)
            ->where('is_active', true)
            ->get();

        if ($employees->isEmpty()) {
            return collect();
        }

        $roots = $this->findRoots($employees);

        return $mode === 'flatten'
            ? $this->buildFlatten($roots, $employees, $maxLevel)
            : $this->buildTree($roots, $employees, $maxLevel);
    }

    /* ================= ROOT DETECTION ================= */

    private function findRoots(Collection $employees): Collection
    {
        return $employees->filter(function ($emp) use ($employees) {

            $atasanIds = $this->decodeAtasan($emp->atasan_langsung);

            // tidak punya atasan
            if (empty($atasanIds)) {
                return true;
            }

            // hanya ["1"]
            if (count($atasanIds) === 1 && (string)$atasanIds[0] === '1') {
                return true;
            }

            // atasan tidak ada di department ini
            foreach ($atasanIds as $atasanId) {
                if ($employees->contains('id', (int)$atasanId)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function decodeAtasan($value): array
    {
        if (empty($value)) return [];

        if (is_array($value)) return $value;

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /* ================= TREE ================= */

    private function buildTree(
        Collection $roots,
        Collection $employees,
        int $maxLevel
    ): Collection {

        $visited = [];

        return $roots->map(function ($root) use ($employees, $maxLevel, &$visited) {
            return $this->buildTreeNode($root, $employees, 1, $maxLevel, $visited);
        })->values();
    }

    private function buildTreeNode(
        MasterKaryawan $employee,
        Collection $employees,
        int $level,
        int $maxLevel,
        array &$visited
    ): array {

        if (in_array($employee->id, $visited)) {
            return [];
        }

        $visited[] = $employee->id;

        $data = $employee->toArray();
        $data['employee_level'] = $level;
        $data['is_root'] = ($level === 1);

        if ($level >= $maxLevel) {
            $data['bawahan'] = [];
            return $data;
        }

        $subs = $this->findDirectSubs($employee->id, $employees);

        $data['bawahan'] = $subs->map(function ($sub) use ($employees, $level, $maxLevel, &$visited) {
            return $this->buildTreeNode($sub, $employees, $level + 1, $maxLevel, $visited);
        })
        ->filter()
        ->values()
        ->toArray();

        return $data;
    }

    /* ================= FLATTEN ================= */

    private function buildFlatten(
        Collection $roots,
        Collection $employees,
        int $maxLevel
    ): Collection {

        $flatten = collect();
        $visited = [];

        foreach ($roots as $root) {
            $this->flattenNode($root, $employees, 1, $maxLevel, $flatten, $visited);
        }

        return $flatten->values();
    }

    private function flattenNode(
        MasterKaryawan $employee,
        Collection $employees,
        int $level,
        int $maxLevel,
        Collection &$flatten,
        array &$visited
    ): void {

        if (in_array($employee->id, $visited)) {
            return;
        }

        $visited[] = $employee->id;

        $data = $employee->toArray();
        $data['employee_level'] = $level;
        $data['is_root'] = ($level === 1);

        $flatten->push($data);

        if ($level >= $maxLevel) {
            return;
        }

        $subs = $this->findDirectSubs($employee->id, $employees);

        foreach ($subs as $sub) {
            $this->flattenNode(
                $sub,
                $employees,
                $level + 1,
                $maxLevel,
                $flatten,
                $visited
            );
        }
    }

    /* ================= COMMON ================= */

    private function findDirectSubs(int $id, Collection $employees): Collection
    {
        return $employees->filter(function ($emp) use ($id) {
            $atasanIds = $this->decodeAtasan($emp->atasan_langsung);

            return in_array((string)$id, $atasanIds)
                || in_array($id, $atasanIds);
        })->values();
    }
}

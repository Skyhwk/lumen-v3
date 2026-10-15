<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use Illuminate\Support\Collection;

class SalesTeamHierarchyService
{
    private array $salesPosition = [148, 24];
    private array $managerIds      = [890];
    private static ?Collection $poolCache = null;
    /** @var array<int, array<int, int>> */
    private static array $descendantIdsCache = [];
    /** @var array<int, array<int, int>> */
    private static array $viewerScopeMemberIdsCache = [];
    private static ?array $orgWideScopeMemberIdsCache = null;

    public function salesExecutiveIds(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            array_map('trim', explode(',', (string) env('SALES_EXECUTIVE', '41')))
        )));
    }

    public function getPool(int $rootId = 890): Collection
    {
        if (self::$poolCache !== null) {
            return self::$poolCache;
        }

        self::$poolCache = GetBawahan::on('id', $rootId)->all()->keyBy('id');

        return self::$poolCache;
    }

    /** Payload untuk select2 di Dashboard POS */
    public function getSelectPayload(): array
    {
        $executives = MasterKaryawan::whereIn('id', $this->salesExecutiveIds())
            ->where('is_active', 1)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'jabatan', 'grade'])
            ->map(fn($item) => [
                'id'           => $item->id,
                'nama_lengkap' => $item->nama_lengkap,
                'jabatan'      => $item->jabatan,
                'position'     => 'Sales Executive',
            ])
            ->values()
            ->all();

        $branches = [];
        foreach ($this->managerIds as $rootId) {
            $pool = $this->getPool($rootId);
            if ($pool->isEmpty()) {
                continue;
            }

            $activeLeaves = $this->salesLeaves($pool)->filter(
                fn($item) => $this->isActiveMember($item)
            );

            if ($activeLeaves->isEmpty()) {
                continue;
            }

            $includedIds = $this->collectIncludedMemberIds($pool, $activeLeaves);

            foreach ($this->orderMembersByBranch($pool, $includedIds) as $branch) {
                $root = $branch['members'][0] ?? null;
                if (!$root) {
                    continue;
                }

                $includedSet = array_flip($includedIds);
                $members     = [];

                foreach ($branch['members'] as $index => $item) {
                    if ($index === 0) {
                        continue;
                    }

                    if (!$this->isDashboardSelectMember($item)) {
                        continue;
                    }

                    $members[] = [
                        'id'            => $item->id,
                        'nama_lengkap'  => $item->nama_lengkap,
                        'jabatan'       => $item->jabatan,
                        'position'      => $this->positionLabel($item),
                        'grade_level'   => $this->normalizeGradeForDisplay($item),
                        'superior_name' => $this->resolveSuperiorName($item, $pool, $includedSet),
                    ];
                }

                if (empty($members)) {
                    continue;
                }

                $branches[] = [
                    'id'           => $root->id,
                    'nama_lengkap' => $root->nama_lengkap,
                    'jabatan'      => $root->jabatan,
                    'position'     => $this->positionLabel($root),
                    'grade_level'  => $this->normalizeGradeForDisplay($root),
                    'members'      => $members,
                ];
            }
        }

        return [
            'executives' => $executives,
            'branches'   => $branches,
        ];
    }

    /** Scope id karyawan yang boleh dilihat viewer di Dashboard POS. */
    public function resolveViewerScopeMemberIds(MasterKaryawan $viewer): array
    {
        $viewerId = (int) $viewer->id;
        if (isset(self::$viewerScopeMemberIdsCache[$viewerId])) {
            return self::$viewerScopeMemberIdsCache[$viewerId];
        }

        if ($this->viewerHasOrgWideAccess($viewer)) {
            return self::$viewerScopeMemberIdsCache[$viewerId] = $this->resolveOrgWideScopeMemberIds();
        }

        if ($this->isSalesStaff($viewer) && !in_array($viewerId, $this->salesExecutiveIds(), true)) {
            return self::$viewerScopeMemberIdsCache[$viewerId] = [$viewerId];
        }

        if (in_array($viewerId, $this->salesExecutiveIds(), true)) {
            return self::$viewerScopeMemberIdsCache[$viewerId] = [$viewerId];
        }

        $pool = $this->getPool();
        if (!$pool->has($viewerId)) {
            return self::$viewerScopeMemberIdsCache[$viewerId] = [$viewerId];
        }

        return self::$viewerScopeMemberIdsCache[$viewerId] = $this->resolveDescendantIds($viewerId);
    }

    public function viewerCanUseAllOption(MasterKaryawan $viewer): bool
    {
        if ($this->viewerHasOrgWideAccess($viewer)) {
            return true;
        }

        if ($this->isSalesStaff($viewer) && !in_array((int) $viewer->id, $this->salesExecutiveIds(), true)) {
            return false;
        }

        $grade = strtoupper(trim((string) ($viewer->grade ?? '')));

        return in_array($grade, ['SENIOR MANAGER', 'MANAGER', 'SUPERVISOR'], true);
    }

    public function viewerCanSelectTeamRoot(MasterKaryawan $viewer, int $teamRootId): bool
    {
        if ($this->viewerHasOrgWideAccess($viewer)) {
            if ($teamRootId <= 0) {
                return false;
            }

            if (in_array($teamRootId, $this->salesExecutiveIds(), true)) {
                return true;
            }

            return $this->getPool()->has($teamRootId);
        }

        $viewerScope = array_flip($this->resolveViewerScopeMemberIds($viewer));

        foreach ($this->resolveDescendantIds($teamRootId) as $memberId) {
            if (!isset($viewerScope[$memberId])) {
                return false;
            }
        }

        return true;
    }

    public function viewerCanUseSelection(MasterKaryawan $viewer, string $mode, $karyawanId = null): bool
    {
        if ($mode === 'all') {
            return $this->viewerCanUseAllOption($viewer);
        }

        if ($mode === 'team') {
            $teamRootId = (int) str_replace('team_', '', (string) $karyawanId);

            return $teamRootId > 0 && $this->viewerCanSelectTeamRoot($viewer, $teamRootId);
        }

        if ($mode === 'single') {
            $memberId = (int) $karyawanId;

            $viewerScope = array_flip($this->resolveViewerScopeMemberIds($viewer));

            return $memberId > 0 && isset($viewerScope[$memberId]);
        }

        return false;
    }

    /** Payload select2 Dashboard POS sesuai hierarki user login. */
    public function getSelectPayloadForViewer(MasterKaryawan $viewer): array
    {
        $payload = $this->getSelectPayload();

        if ($this->viewerHasOrgWideAccess($viewer)) {
            $payload['branches'] = array_map(function (array $branch) {
                return array_merge($branch, [
                    'team_select_id'      => (int) $branch['id'],
                    'include_team_option' => true,
                ]);
            }, $payload['branches']);

            return $payload;
        }

        $scopeSet  = array_flip($this->resolveViewerScopeMemberIds($viewer));
        $viewerId  = (int) $viewer->id;
        $viewerGrade = $this->normalizeGradeForDisplay($viewer);

        $payload['executives'] = array_values(array_filter(
            $payload['executives'],
            fn ($executive) => isset($scopeSet[(int) $executive['id']])
        ));

        $branches = [];
        foreach ($payload['branches'] as $branch) {
            $visibleMembers = array_values(array_filter(
                $branch['members'],
                fn ($member) => isset($scopeSet[(int) $member['id']])
            ));

            $branchVisible = isset($scopeSet[(int) $branch['id']]) || !empty($visibleMembers);
            if (!$branchVisible) {
                continue;
            }

            $teamSelectId    = (int) $branch['id'];
            $includeTeam     = $this->viewerCanSelectTeamRoot($viewer, $teamSelectId);

            if (!$includeTeam && $viewerGrade === 'supervisor' && isset($scopeSet[$viewerId])) {
                $teamSelectId = $viewerId;
                $includeTeam  = $this->viewerCanSelectTeamRoot($viewer, $teamSelectId);
            }

            if ($viewerGrade === 'manager' && $viewerId === (int) $branch['id']) {
                $teamSelectId = (int) $branch['id'];
                $includeTeam  = true;
            }

            if (!$includeTeam && empty($visibleMembers)) {
                continue;
            }

            $branches[] = array_merge($branch, [
                'members'             => $visibleMembers,
                'team_select_id'      => $teamSelectId,
                'include_team_option' => $includeTeam,
            ]);
        }

        $payload['branches'] = $branches;

        return $payload;
    }

    public function getViewerMeta(MasterKaryawan $viewer): array
    {
        $canUseAll = $this->viewerCanUseAllOption($viewer);

        if ($canUseAll) {
            return [
                'karyawan_id'   => (int) $viewer->id,
                'grade_level'   => $this->normalizeGradeForDisplay($viewer),
                'can_use_all'   => true,
                'default_mode'  => 'all',
                'default_value' => 'all',
            ];
        }

        return [
            'karyawan_id'   => (int) $viewer->id,
            'grade_level'   => $this->normalizeGradeForDisplay($viewer),
            'can_use_all'   => false,
            'default_mode'  => 'single',
            'default_value' => (string) $viewer->id,
        ];
    }

    private function resolveOrgWideScopeMemberIds(): array
    {
        if (self::$orgWideScopeMemberIdsCache !== null) {
            return self::$orgWideScopeMemberIdsCache;
        }

        $ids = $this->salesExecutiveIds();
        foreach ($this->managerIds as $rootId) {
            $ids = array_merge($ids, $this->resolveDescendantIds($rootId));
        }

        self::$orgWideScopeMemberIdsCache = array_values(array_unique(array_map('intval', $ids)));

        return self::$orgWideScopeMemberIdsCache;
    }

    /** User di luar divisi sales: akses penuh seluruh data tim sales di dashboard POS. */
    public function viewerHasOrgWideAccess(MasterKaryawan $viewer): bool
    {
        if ((int) $viewer->id === 1) {
            return true;
        }

        return !$this->isSalesDivisionMember($viewer);
    }

    public function isSalesDivisionMember(MasterKaryawan $viewer): bool
    {
        $viewerId = (int) $viewer->id;

        if (in_array($viewerId, $this->salesExecutiveIds(), true)) {
            return true;
        }

        if ($this->isSalesStaff($viewer)) {
            return true;
        }

        if ($this->getPool()->has($viewerId)) {
            return true;
        }

        $viewer->loadMissing('divisi');
        $divisiName = strtoupper(trim((string) optional($viewer->divisi)->nama_divisi));

        if ($divisiName !== '' && $this->isSalesDivisionName($divisiName)) {
            return true;
        }

        return false;
    }

    private function isSalesDivisionName(string $divisiName): bool
    {
        return str_contains($divisiName, 'SALES')
            || str_contains($divisiName, 'PENJUALAN')
            || str_contains($divisiName, 'MARKETING SALES');
    }

    /**
     * Baris tabel hierarki untuk dashboard.
     *
     * @param callable $shouldShowStaff fn($member): bool
     */
    public function buildTableRows(
        callable $shouldShowStaff,
        ?array $scopeRootIds = null,
        ?array $scopeMemberIds = null
    ): array {
        $rows        = [];
        $addedIds    = [];
        $memberScope = $scopeMemberIds !== null ? array_flip($scopeMemberIds) : null;

        foreach ($this->salesExecutiveIds() as $executiveId) {
            if ($scopeRootIds !== null) {
                continue;
            }

            if ($memberScope !== null && !isset($memberScope[$executiveId])) {
                continue;
            }

            $executive = MasterKaryawan::find($executiveId);
            if (!$executive || in_array($executiveId, $addedIds, true)) {
                continue;
            }

            $addedIds[] = $executiveId;
            $rows[]     = $this->mapTableRow($executive, 'Sales Executive', 'manager', null);
        }

        foreach ($this->managerIds as $rootId) {
            $pool = $this->getPool($rootId);
            if ($pool->isEmpty()) {
                continue;
            }

            $visibleLeaves = $this->salesLeaves($pool)->filter(fn($staff) => $shouldShowStaff($staff));
            if ($visibleLeaves->isEmpty()) {
                continue;
            }

            $includedIds = $this->collectIncludedMemberIds($pool, $visibleLeaves);
            $includedSet = array_flip($includedIds);
            $branches    = $this->orderMembersByBranch($pool, $includedIds);

            foreach ($branches as $branch) {
                $branchRootId = (int) ($branch['members'][0]->id ?? 0);
                $teamName     = $branch['team_name'];

                if ($scopeRootIds !== null && !in_array($branchRootId, $scopeRootIds, true)) {
                    continue;
                }

                if ($memberScope !== null && $branchRootId > 0 && !isset($memberScope[$branchRootId])) {
                    foreach ($branch['members'] as $scopedAnchor) {
                        if (!isset($memberScope[(int) $scopedAnchor->id])) {
                            continue;
                        }

                        $teamName = $scopedAnchor->nama_lengkap;
                        break;
                    }
                }

                foreach ($branch['members'] as $item) {
                    if ($memberScope !== null && !isset($memberScope[(int) $item->id])) {
                        continue;
                    }

                    if (in_array($item->id, $addedIds, true)) {
                        continue;
                    }

                    $addedIds[] = $item->id;
                    $rows[]     = $this->mapTableRow(
                        $item,
                        $teamName,
                        $this->normalizeGradeForDisplay($item),
                        $this->resolveSuperiorName($item, $pool, $includedSet)
                    );
                }
            }
        }

        return $rows;
    }

    /** Scope id untuk mode team / single atasan */
    public function resolveDescendantIds(int $rootId): array
    {
        if (isset(self::$descendantIdsCache[$rootId])) {
            return self::$descendantIdsCache[$rootId];
        }

        $pool = $this->getPool();
        if (!$pool->has($rootId)) {
            return self::$descendantIdsCache[$rootId] = [$rootId];
        }

        $ids     = [];
        $queue   = [$rootId];
        $visited = [$rootId => true];

        while (!empty($queue)) {
            $supervisorId = array_shift($queue);
            $ids[]        = $supervisorId;

            foreach ($pool as $item) {
                if (isset($visited[$item->id])) {
                    continue;
                }

                if (!in_array($supervisorId, $this->parseAtasanIds($item->atasan_langsung ?? '[]'), true)) {
                    continue;
                }

                $visited[$item->id] = true;
                $queue[]            = $item->id;
            }
        }

        return self::$descendantIdsCache[$rootId] = array_values(array_unique($ids));
    }

    public function isSalesStaff($member): bool
    {
        return in_array((int) $member->id_jabatan, $this->salesPosition, true);
    }

    public function isResignedSalesStaff($member): bool
    {
        return $this->isSalesStaff($member) && !$this->isActiveMember($member);
    }

    private function mapTableRow($item, string $teamName, string $gradeLevel, ?string $superiorName): array
    {
        return [
            'karyawan_id'     => $item->id,
            'nama_lengkap'    => $item->nama_lengkap,
            'team_name'       => $teamName,
            'position'        => $this->positionLabel($item),
            'grade_level'     => $gradeLevel,
            'superior_name'   => $superiorName,
            'karyawan_active' => (int) ($item->is_active ?? 0),
            'is_resigned'     => $this->isResignedSalesStaff($item),
        ];
    }

    private function salesLeaves(Collection $pool): Collection
    {
        return $pool->filter(fn($item) => $this->isSalesStaff($item));
    }

    private function isActiveMember($member): bool
    {
        return (int) ($member->is_active ?? 0) === 1;
    }

    /** Anggota tim yang tampil di filter dashboard (SPV + sales lapangan). */
    private function isDashboardSelectMember($member): bool
    {
        if (!$this->isActiveMember($member)) {
            return false;
        }

        if ($this->isSalesStaff($member)) {
            return true;
        }

        return $this->normalizeGradeForDisplay($member) === 'supervisor';
    }

    private function collectIncludedMemberIds(Collection $pool, Collection $visibleLeaves): array
    {
        $included = [];

        foreach ($visibleLeaves as $staff) {
            $this->walkUpAncestors((int) $staff->id, $pool, $included);
        }

        return array_keys($included);
    }

    private function walkUpAncestors(int $memberId, Collection $pool, array &$included): void
    {
        $queue   = [$memberId];
        $visited = [];

        while (!empty($queue)) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId]) || !$pool->has($currentId)) {
                continue;
            }

            $visited[$currentId] = true;
            $member              = $pool->get($currentId);

            if ($this->isSeniorManager($member)) {
                continue;
            }

            if ($this->isSalesStaff($member) || $this->isActiveMember($member)) {
                $included[$currentId] = true;
            }

            foreach ($this->parseAtasanIds($member->atasan_langsung ?? '[]') as $atasanId) {
                if ($pool->has($atasanId)) {
                    $queue[] = $atasanId;
                }
            }
        }
    }

    private function orderMembersByBranch(Collection $pool, array $includedIds): array
    {
        $includedSet = array_flip($includedIds);
        $roots       = [];

        foreach ($includedIds as $id) {
            if ($this->findIncludedAncestorId($pool->get($id), $pool, $includedSet) === null) {
                $roots[] = $id;
            }
        }

        usort($roots, fn($a, $b) => strcmp($pool->get($a)->nama_lengkap, $pool->get($b)->nama_lengkap));

        $branches = [];

        foreach ($roots as $rootId) {
            $members = [];
            $visited = [];
            $this->appendMemberSubtree((int) $rootId, $pool, $includedSet, $visited, $members);

            $branches[] = [
                'team_name' => $pool->get($rootId)->nama_lengkap,
                'members'   => $members,
            ];
        }

        return $branches;
    }

    private function appendMemberSubtree(
        int $parentId,
        Collection $pool,
        array $includedSet,
        array &$visited,
        array &$ordered
    ): void {
        if (isset($visited[$parentId]) || !isset($includedSet[$parentId])) {
            return;
        }

        $visited[$parentId] = true;
        $ordered[]          = $pool->get($parentId);

        $children = $pool
            ->filter(function ($item) use ($parentId, $pool, $includedSet) {
                if (!isset($includedSet[$item->id])) {
                    return false;
                }

                return $this->findIncludedAncestorId($item, $pool, $includedSet) === $parentId;
            })
            ->sortBy('nama_lengkap');

        foreach ($children as $child) {
            $this->appendMemberSubtree((int) $child->id, $pool, $includedSet, $visited, $ordered);
        }
    }

    private function findIncludedAncestorId($member, Collection $pool, array $includedSet): ?int
    {
        $queue   = $this->parseAtasanIds($member->atasan_langsung ?? '[]');
        $visited = [];

        while (!empty($queue)) {
            $atasanId = array_shift($queue);

            if (isset($visited[$atasanId])) {
                continue;
            }

            $visited[$atasanId] = true;

            if (isset($includedSet[$atasanId])) {
                return $atasanId;
            }

            if (!$pool->has($atasanId)) {
                continue;
            }

            foreach ($this->parseAtasanIds($pool->get($atasanId)->atasan_langsung ?? '[]') as $nextId) {
                $queue[] = $nextId;
            }
        }

        return null;
    }

    private function resolveSuperiorName($member, Collection $pool, array $includedSet): ?string
    {
        $superiorId = $this->findIncludedAncestorId($member, $pool, $includedSet);

        if ($superiorId === null || !$pool->has($superiorId)) {
            return null;
        }

        return $pool->get($superiorId)->nama_lengkap;
    }

    private function normalizeGradeForDisplay($member): string
    {
        $grade = strtoupper(trim((string) ($member->grade ?? '')));

        if ($grade === 'MANAGER') {
            return 'manager';
        }

        if ($grade === 'SUPERVISOR') {
            return 'supervisor';
        }

        return 'sales';
    }

    private function positionLabel($member): string
    {
        $jabatan = (int) $member->id_jabatan;

        if ($jabatan === 24) {
            return 'SO';
        }

        if ($jabatan === 148) {
            return 'CRO';
        }

        if (in_array((int) $member->id, $this->salesExecutiveIds(), true)) {
            return 'SE';
        }

        $grade = strtoupper(trim((string) ($member->grade ?? '')));

        if ($grade === 'MANAGER') {
            return 'Manager';
        }

        if ($grade === 'SUPERVISOR') {
            return 'Supervisor';
        }

        return 'Staff';
    }

    private function parseAtasanIds($atasanLangsung): array
    {
        $decoded = is_array($atasanLangsung)
            ? $atasanLangsung
            : (json_decode($atasanLangsung ?? '[]', true) ?? []);

        return array_values(array_filter(array_map('intval', $decoded)));
    }

    private function isSeniorManager($member): bool
    {
        return strtoupper(trim((string) ($member->grade ?? ''))) === 'SENIOR MANAGER';
    }
}

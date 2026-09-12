<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ForecastSP;
use App\Models\MasterKaryawan;
use App\Services\ForecastSpAggregate;
use App\Services\GetBawahan;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DataForecastController extends Controller
{
    private array $salesPosition = [148, 24]; // 148 : CRO, 24 : SO
    private array $managerIds    = [890];
    private array $emptyOrder;

    public function index(Request $request)
    {
        $tahun            = (int) $request->year;
        $this->emptyOrder = $this->getEmptyOrder();

        $forecastPerSales = $this->getForecastPerSales($tahun);
        $allMembers       = $this->getAllTeamMembers($forecastPerSales);
        $teamsData        = $this->processTeamData($allMembers, $forecastPerSales);

        $allteam_total_periode = $this->emptyOrder;

        foreach ($teamsData as &$teamData) {
            $teamTotal      = $this->emptyOrder;
            $teamTotalStaff = $this->emptyOrder;

            foreach (['staff', 'supervisor', 'manager'] as $grade) {
                if (empty($teamData[$grade])) {
                    continue;
                }

                foreach ($teamData[$grade] as $member) {
                    foreach ($member['order'] as $month => $amount) {
                        $teamTotal[$month]             += $amount;
                        $allteam_total_periode[$month] += $amount;

                        if ($grade === 'staff') {
                            $teamTotalStaff[$month] += $amount;
                        }
                    }
                }
            }

            $teamData['team_total_periode']       = $teamTotal;
            $teamData['team_total']               = array_sum($teamTotal);
            $teamData['team_total_staff_periode'] = $teamTotalStaff;
            $teamData['team_total_staff']         = array_sum($teamTotalStaff);
        }

        return response()->json([
            'success'           => true,
            'year'              => $tahun,
            'data'              => array_values($teamsData),
            'all_total_periode' => $allteam_total_periode,
            'all_total'         => array_sum($allteam_total_periode),
            'message'           => 'Data berhasil diproses!',
        ], 200);
    }

    public function indexData(Request $request)
    {
        $data = ForecastSP::with('pelanggan')
            ->whereYear('tanggal_sampling_min', $request->year)
            ->whereNotIn('pelanggan_ID', ForecastSpAggregate::EXCLUDE_CUSTOMERS);

        return Datatables::of($data)
            ->addColumn('nama_perusahaan', function ($row) {
                return $row->pelanggan ? $row->pelanggan->nama_pelanggan : '-';
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->whereHas('pelanggan', function ($q) use ($keyword) {
                    $q->where('nama_pelanggan', 'like', "%{$keyword}%");
                });
            })
            ->make(true);
    }

    private function getForecastPerSales(int $tahun): array
    {
        $forecasts  = ForecastSP::whereYear('tanggal_sampling_min', $tahun)
            ->whereNotIn('pelanggan_ID', ForecastSpAggregate::EXCLUDE_CUSTOMERS)
            ->get();
        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
        $result     = [];

        foreach ($forecasts as $forecast) {
            $sid = (int) $forecast->sales_id;

            if (!isset($result[$sid])) {
                $result[$sid] = $this->emptyOrder;
            }

            $bulanNumber = (int) Carbon::parse($forecast->tanggal_sampling_min)->format('n');
            $bulan       = $monthNames[$bulanNumber] ?? null;

            if ($bulan === null) {
                continue;
            }

            $result[$sid][$bulan] += $forecast->revenue_forecast;
        }

        return $result;
    }

    private function getEmptyOrder(): array
    {
        return [
            'Jan' => 0, 'Feb' => 0, 'Mar' => 0,
            'Apr' => 0, 'Mei' => 0, 'Jun' => 0,
            'Jul' => 0, 'Agt' => 0, 'Sep' => 0,
            'Okt' => 0, 'Nov' => 0, 'Des' => 0,
        ];
    }

    private function processTeamData(array $allMembers, array $forecastPerSales): array
    {
        $teamsData = [];

        foreach ($allMembers as $member) {
            $teamIndex = $member['team_index'];
            $bucket    = $this->gradeBucket($member['grade']);

            if (!isset($teamsData[$teamIndex])) {
                $teamsData[$teamIndex] = [
                    'staff'      => [],
                    'supervisor' => [],
                    'manager'    => [],
                    'team_name'  => $member['team_name'] ?? ('Tim ' . ($teamIndex + 1)),
                ];
            }

            $order = $forecastPerSales[$member['id']] ?? $this->emptyOrder;

            $memberData = array_merge($member['data'], [
                'order'         => $order,
                'total_order'   => array_sum($order),
                'is_resigned'   => (bool) ($member['is_resigned'] ?? false),
                'position'      => $member['position'] ?? null,
                'superior_name' => $member['superior_name'] ?? null,
            ]);

            $teamsData[$teamIndex][$bucket][] = $memberData;
        }

        ksort($teamsData);

        return $teamsData;
    }

    private function gradeBucket(string $grade): string
    {
        $grade = strtolower($grade);

        if ($grade === 'manager') {
            return 'manager';
        }

        if ($grade === 'supervisor') {
            return 'supervisor';
        }

        return 'staff';
    }

    // =========================================================================
    // TEAM HELPERS — selaras ViewPerSalesController
    // =========================================================================
    private function salesExecutiveIds(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            array_map('trim', explode(',', (string) env('SALES_EXECUTIVE', '41')))
        )));
    }

    private function getAllTeamMembers(array $forecastPerSales): array
    {
        $allMembers = [];
        $addedIds   = [];

        foreach ($this->getSalesExecutiveMembers() as $index => $member) {
            $member['team_index'] = -1 - $index;
            $allMembers[]         = $member;
            $addedIds[]           = $member['id'];
        }

        foreach ($this->managerIds as $teamIndex => $managerId) {
            $pool = GetBawahan::on('id', $managerId)->all()->keyBy('id');
            if ($pool->isEmpty()) {
                continue;
            }

            $visibleLeaves = $this->salesLeaves($pool)->filter(
                fn($staff) => $this->shouldShowMember(
                    $staff,
                    $forecastPerSales[(int) $staff->id] ?? $this->emptyOrder
                )
            );

            if ($visibleLeaves->isEmpty()) {
                continue;
            }

            $includedIds = $this->collectIncludedMemberIds($pool, $visibleLeaves);
            if (empty($includedIds)) {
                continue;
            }

            $includedSet = array_flip($includedIds);
            $branches    = $this->orderMembersByBranch($pool, $includedIds);

            foreach ($branches as $branchIndex => $branch) {
                foreach ($branch['members'] as $item) {
                    if (in_array($item->id, $addedIds, true)) {
                        continue;
                    }

                    $addedIds[]   = $item->id;
                    $allMembers[] = $this->mapMemberRow(
                        $item,
                        ($teamIndex * 100) + $branchIndex,
                        $branch['team_name'],
                        null,
                        $pool,
                        $includedSet
                    );
                }
            }
        }

        return $allMembers;
    }

    private function getSalesExecutiveMembers(): array
    {
        $ids = $this->salesExecutiveIds();
        if (empty($ids)) {
            return [];
        }

        return MasterKaryawan::whereIn('id', $ids)
            ->orderBy('nama_lengkap')
            ->get()
            ->map(fn($item) => $this->mapMemberRow($item, -1, 'Sales Executive', 'manager'))
            ->values()
            ->all();
    }

    private function mapMemberRow(
        $item,
        int $teamIndex,
        string $teamName,
        ?string $gradeOverride = null,
        $pool = null,
        array $includedSet = []
    ): array {
        return [
            'id'            => $item->id,
            'team_index'    => $teamIndex,
            'team_name'     => $teamName,
            'grade'         => $gradeOverride ?? $this->normalizeGradeForDisplay($item),
            'jabatan'       => $item->id_jabatan,
            'position'      => $this->positionLabel($item),
            'superior_name' => $pool ? $this->resolveSuperiorName($item, $pool, $includedSet) : null,
            'is_resigned'   => $this->isResignedSalesStaff($item),
            'data'          => [
                'id'              => $item->id,
                'nama_lengkap'    => $item->nama_lengkap,
                'grade'           => $item->grade,
                'is_active'       => $item->is_active,
                'atasan_langsung' => $item->atasan_langsung,
                'image'           => $item->image,
                'id_jabatan'      => $item->id_jabatan,
            ],
        ];
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

    private function resolveSuperiorName($member, $pool, array $includedSet): ?string
    {
        $superiorId = $this->findIncludedAncestorId($member, $pool, $includedSet);

        if ($superiorId === null || !$pool->has($superiorId)) {
            return null;
        }

        return $pool->get($superiorId)->nama_lengkap;
    }

    private function salesLeaves($pool)
    {
        return $pool->filter(
            fn($item) => in_array((int) $item->id_jabatan, $this->salesPosition, true)
        );
    }

    /** Aktif selalu tampil. Resign hanya jika ada forecast di tahun berjalan. */
    private function shouldShowMember($member, array $forecastOrder): bool
    {
        if ($this->isActiveMember($member)) {
            return true;
        }

        return $this->hasForecastActivity($forecastOrder);
    }

    private function isActiveMember($member): bool
    {
        return (int) ($member->is_active ?? 0) === 1;
    }

    private function isResignedSalesStaff($member): bool
    {
        return in_array((int) $member->id_jabatan, $this->salesPosition, true)
            && !$this->isActiveMember($member);
    }

    private function hasForecastActivity(array $forecastOrder): bool
    {
        return array_sum($forecastOrder) > 0;
    }

    private function collectIncludedMemberIds($pool, $visibleLeaves): array
    {
        $included = [];

        foreach ($visibleLeaves as $staff) {
            $this->walkUpAncestors((int) $staff->id, $pool, $included);
        }

        return array_keys($included);
    }

    private function walkUpAncestors(int $memberId, $pool, array &$included): void
    {
        $queue   = [$memberId];
        $visited = [];

        while (!empty($queue)) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;

            if (!$pool->has($currentId)) {
                continue;
            }

            $member = $pool->get($currentId);

            if ($this->isSeniorManager($member)) {
                continue;
            }

            $isSalesStaff = in_array((int) $member->id_jabatan, $this->salesPosition, true);

            if ($isSalesStaff || $this->isActiveMember($member)) {
                $included[$currentId] = true;
            }

            foreach ($this->parseAtasanIds($member->atasan_langsung ?? '[]') as $atasanId) {
                if ($pool->has($atasanId)) {
                    $queue[] = $atasanId;
                }
            }
        }
    }

    private function orderMembersByBranch($pool, array $includedIds): array
    {
        $includedSet = array_flip($includedIds);
        $roots       = [];

        foreach ($includedIds as $id) {
            if ($this->findIncludedAncestorId($pool->get($id), $pool, $includedSet) === null) {
                $roots[] = $id;
            }
        }

        usort(
            $roots,
            fn($a, $b) => strcmp($pool->get($a)->nama_lengkap, $pool->get($b)->nama_lengkap)
        );

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
        $pool,
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

    private function findIncludedAncestorId($member, $pool, array $includedSet): ?int
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

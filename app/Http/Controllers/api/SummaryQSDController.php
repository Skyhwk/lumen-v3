<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ForecastSP;
use App\Models\MasterKaryawan;
use App\Services\GetBawahan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SummaryQSDController extends Controller
{
    private array $salesPosition = [148, 24]; // 148 : CRO, 24 : SO
    private array $managerIds    = [890];
    private array $emptyOrder;

    public function index(Request $request)
    {
        $year = (int) $request->input('tahun', Carbon::now()->year);
        $type = strtolower(trim($request->type ?? 'order'));

        $this->emptyOrder = $this->getEmptyOrder();

        $candidateIds     = $this->collectCandidateIds();
        $bulkDPP          = $this->getRevenueFromDailyQSD($candidateIds, $year, $type);
        $forecastPerSales = $this->getForecastPerSales($year, $type);

        $allMembers = $this->getAllTeamMembers($bulkDPP, $forecastPerSales);
        $teamsData  = $this->processTeamData($allMembers, $bulkDPP, $forecastPerSales);

        $allteam_total_periode          = $this->emptyOrder;
        $allteam_forecast_total_periode = $this->emptyOrder;

        foreach ($teamsData as &$teamData) {
            $teamTotal              = $this->emptyOrder;
            $teamTotalStaff         = $this->emptyOrder;
            $teamForecastTotal      = $this->emptyOrder;
            $teamForecastTotalStaff = $this->emptyOrder;

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

                    foreach ($member['forecast'] as $month => $amount) {
                        $teamForecastTotal[$month]              += $amount;
                        $allteam_forecast_total_periode[$month] += $amount;

                        if ($grade === 'staff') {
                            $teamForecastTotalStaff[$month] += $amount;
                        }
                    }
                }
            }

            $teamData['team_total_periode']          = $teamTotal;
            $teamData['team_total']                  = array_sum($teamTotal);
            $teamData['team_total_staff_periode']    = $teamTotalStaff;
            $teamData['team_total_staff']            = array_sum($teamTotalStaff);
            $teamData['team_forecast_total_periode'] = $teamForecastTotal;
            $teamData['team_forecast_total']         = array_sum($teamForecastTotal);
            $teamData['team_forecast_staff_periode'] = $teamForecastTotalStaff;
            $teamData['team_forecast_staff']         = array_sum($teamForecastTotalStaff);
        }

        return response()->json([
            'success'                => true,
            'type'                   => $type,
            'year'                   => $year,
            'data'                   => array_values($teamsData),
            'all_total_periode'      => $allteam_total_periode,
            'all_total'              => array_sum($allteam_total_periode),
            'forecast_total'         => array_sum($allteam_forecast_total_periode),
            'forecast_total_periode' => $allteam_forecast_total_periode,
            'message'                => 'Data berhasil diproses!',
        ], 200);
    }

    private function processTeamData(array $allMembers, array $bulkDPP, array $forecastPerSales): array
    {
        $teamsData = [];

        foreach ($allMembers as $member) {
            $teamIndex = $member['team_index'];
            $bucket    = $this->gradeBucket($member['grade']);
            $memberId  = $member['id'];

            if (!isset($teamsData[$teamIndex])) {
                $teamsData[$teamIndex] = [
                    'staff'      => [],
                    'supervisor' => [],
                    'manager'    => [],
                    'team_name'  => $member['team_name'] ?? ('Tim ' . ($teamIndex + 1)),
                ];
            }

            $forecastData = $forecastPerSales[$memberId] ?? null;

            $memberData = array_merge($member['data'], [
                'order'          => $bulkDPP[$memberId] ?? $this->emptyOrder,
                'total_order'    => array_sum($bulkDPP[$memberId] ?? $this->emptyOrder),
                'forecast'       => $forecastData['periode'] ?? $this->emptyOrder,
                'total_forecast' => $forecastData['total_tahun'] ?? 0,
                'is_resigned'    => (bool) ($member['is_resigned'] ?? false),
                'position'       => $member['position'] ?? null,
                'superior_name'  => $member['superior_name'] ?? null,
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

    private function getRevenueFromDailyQSD(array $allMemberIds, int $tahun, string $type = 'order'): array
    {
        if (empty($allMemberIds)) {
            return [];
        }

        $query = DB::table('daily_qsd')
            ->select(
                'sales_id',
                DB::raw('MONTH(tanggal_kelompok) as month_num'),
                DB::raw('SUM(total_revenue) as total_revenue')
            )
            ->whereNotIn('pelanggan_ID', ['SAIR02', 'T2PE01', 'SEMX01'])
            ->whereYear('tanggal_kelompok', $tahun)
            ->whereIn('sales_id', $allMemberIds);

        switch ($type) {
            case 'order':
                $query->whereNotNull('no_order')->where('no_order', '!=', '');
                break;
            case 'contract':
                $query->where('kontrak', 'C')->whereNotNull('no_order');
                break;
            case 'sampling':
                $query->whereIn('status_sampling', ['S', 'S24'])->whereNotNull('no_order');
                break;
            case 'sampel_diantar':
            case 'sd':
                $query->whereIn('status_sampling', ['SD', 'SAR', 'SP'])->whereNotNull('no_order');
                break;
            case 'new':
                $query->where('status_customer', 'new')->whereNotNull('no_order');
                break;
            default:
                return [];
        }

        $data = $query->groupBy('sales_id', 'month_num')->get();

        if ($data->isEmpty()) {
            return [];
        }

        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
        $result     = [];

        foreach ($data as $record) {
            $salesId = (int) $record->sales_id;

            if (!isset($result[$salesId])) {
                $result[$salesId] = $this->emptyOrder;
            }

            $monthKey                     = $monthNames[$record->month_num];
            $result[$salesId][$monthKey] += $record->total_revenue;
        }

        return $result;
    }

    private function getEmptyOrder(): array
    {
        return [
            'Jan' => 0, 'Feb' => 0, 'Mar' => 0, 'Apr' => 0,
            'Mei' => 0, 'Jun' => 0, 'Jul' => 0, 'Agt' => 0,
            'Sep' => 0, 'Okt' => 0, 'Nov' => 0, 'Des' => 0,
        ];
    }

    private function getForecastPerSales(int $tahun, string $type): array
    {
        $forecasts = ForecastSP::whereYear('tanggal_sampling_min', $tahun);

        switch ($type) {
            case 'contract':
                $forecasts->where('status_quotation', 'kontrak');
                break;
            case 'new':
                $forecasts->where('status_customer', 'new');
                break;
            default:
                break;
        }

        $forecasts  = $forecasts->get();
        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
        $result     = [];

        foreach ($forecasts as $forecast) {
            $sid = (int) $forecast->sales_id;

            if (!isset($result[$sid])) {
                $result[$sid] = [
                    'total_tahun' => 0,
                    'periode'     => $this->getEmptyOrder(),
                ];
            }

            $bulanNumber = (int) Carbon::parse($forecast->tanggal_sampling_min)->format('n');
            $bulan       = $monthNames[$bulanNumber] ?? null;

            if ($bulan === null) {
                continue;
            }

            $result[$sid]['periode'][$bulan] += $forecast->revenue_forecast;
            $result[$sid]['total_tahun']     += $forecast->revenue_forecast;
        }

        return $result;
    }

    // =========================================================================
    // TEAM HELPERS — selaras ViewPerSalesController / DataForecastController
    // =========================================================================
    private function salesExecutiveIds(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            array_map('trim', explode(',', (string) env('SALES_EXECUTIVE', '41')))
        )));
    }

    private function collectCandidateIds(): array
    {
        $candidateIds = [];

        foreach ($this->salesExecutiveIds() as $executiveId) {
            $candidateIds[$executiveId] = $executiveId;
        }

        foreach ($this->managerIds as $managerId) {
            $pool = GetBawahan::on('id', $managerId)->all()->keyBy('id');
            if ($pool->isEmpty()) {
                continue;
            }

            foreach ($this->collectIncludedMemberIds($pool, $this->salesLeaves($pool)) as $id) {
                $candidateIds[$id] = $id;
            }
        }

        return array_values($candidateIds);
    }

    private function getAllTeamMembers(array $bulkDPP, array $forecastPerSales): array
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
                    (int) $staff->id,
                    $bulkDPP,
                    $forecastPerSales
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

    /** Aktif selalu tampil. Resign hanya jika ada revenue atau forecast di tahun berjalan. */
    private function shouldShowMember($member, int $memberId, array $bulkDPP, array $forecastPerSales): bool
    {
        if ($this->isActiveMember($member)) {
            return true;
        }

        return $this->memberHasActivity($memberId, $bulkDPP, $forecastPerSales);
    }

    private function memberHasActivity(int $memberId, array $bulkDPP, array $forecastPerSales): bool
    {
        $order    = $bulkDPP[$memberId] ?? $this->emptyOrder;
        $forecast = $forecastPerSales[$memberId] ?? null;

        return array_sum($order) > 0 || ($forecast['total_tahun'] ?? 0) > 0;
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

<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Services\ForecastSpAggregate;
use App\Services\GetBawahan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecapDailyQuoteController extends Controller
{
    private array $salesPosition = [148, 24];
    private array $managerIds    = [890];

    public function index(Request $request)
    {
        $date = $request->date ?? Carbon::now()->format('Y-m-d');

        $quotationData = $this->getQuotationData($date);
        $allMembers    = $this->getAllTeamMembers($quotationData);
        $teamsData     = $this->processTeamDataQuotation($allMembers, $quotationData);

        $allteam_total = 0;

        foreach ($teamsData as &$teamData) {
            $teamTotal      = 0;
            $teamTotalStaff = 0;

            foreach (['staff', 'supervisor', 'manager'] as $grade) {
                if (empty($teamData[$grade])) {
                    continue;
                }

                foreach ($teamData[$grade] as $member) {
                    $memberTotal    = $member['total_biaya_akhir'] ?? 0;
                    $teamTotal     += $memberTotal;
                    $allteam_total += $memberTotal;

                    if ($grade === 'staff') {
                        $teamTotalStaff += $memberTotal;
                    }
                }
            }

            $teamData['team_total']       = $teamTotal;
            $teamData['team_total_staff'] = $teamTotalStaff;
        }

        return response()->json([
            'success'   => true,
            'data'      => array_values($teamsData),
            'all_total' => $allteam_total,
            'message'   => 'Data berhasil diproses!',
        ], 200);
    }

    private function getQuotationData(string $date): array
    {
        $exclude = ForecastSpAggregate::EXCLUDE_CUSTOMERS;

        $quotationNon = DB::table('request_quotation')
            ->leftJoin('order_header as oh', 'request_quotation.pelanggan_ID', '=', 'oh.id_pelanggan')
            ->select(
                'request_quotation.sales_id',
                DB::raw('COUNT(DISTINCT request_quotation.no_document) as total_request_quotation'),
                DB::raw('SUM(DISTINCT request_quotation.biaya_akhir) as total_biaya_akhir'),
                DB::raw('COUNT(DISTINCT CASE WHEN oh.id IS NOT NULL THEN request_quotation.no_document ELSE NULL END) as pelanggan_lama'),
                DB::raw('COUNT(DISTINCT CASE WHEN oh.id IS NULL THEN request_quotation.no_document ELSE NULL END) as pelanggan_baru'),
                DB::raw('SUM(DISTINCT CASE WHEN oh.id IS NOT NULL THEN request_quotation.biaya_akhir ELSE 0 END) as total_biaya_pelanggan_lama'),
                DB::raw('SUM(DISTINCT CASE WHEN oh.id IS NULL THEN request_quotation.biaya_akhir ELSE 0 END) as total_biaya_pelanggan_baru')
            )
            ->where('request_quotation.is_active', 1)
            ->whereDate('request_quotation.created_at', $date)
            ->whereNotIn('request_quotation.pelanggan_ID', $exclude)
            ->groupBy('request_quotation.sales_id');

        $quotationKon = DB::table('request_quotation_kontrak_H')
            ->leftJoin('order_header as oh', 'request_quotation_kontrak_H.pelanggan_ID', '=', 'oh.id_pelanggan')
            ->select(
                'request_quotation_kontrak_H.sales_id as sales_id',
                DB::raw('COUNT(DISTINCT request_quotation_kontrak_H.no_document) as total_request_quotation'),
                DB::raw('SUM(DISTINCT request_quotation_kontrak_H.biaya_akhir) as total_biaya_akhir'),
                DB::raw('COUNT(DISTINCT CASE WHEN oh.id IS NOT NULL THEN request_quotation_kontrak_H.no_document ELSE NULL END) as pelanggan_lama'),
                DB::raw('COUNT(DISTINCT CASE WHEN oh.id IS NULL THEN request_quotation_kontrak_H.no_document ELSE NULL END) as pelanggan_baru'),
                DB::raw('SUM(DISTINCT CASE WHEN oh.id IS NOT NULL THEN request_quotation_kontrak_H.biaya_akhir ELSE 0 END) as total_biaya_pelanggan_lama'),
                DB::raw('SUM(DISTINCT CASE WHEN oh.id IS NULL THEN request_quotation_kontrak_H.biaya_akhir ELSE 0 END) as total_biaya_pelanggan_baru')
            )
            ->where('request_quotation_kontrak_H.is_active', 1)
            ->whereDate('request_quotation_kontrak_H.created_at', $date)
            ->whereNotIn('request_quotation_kontrak_H.pelanggan_ID', $exclude)
            ->groupBy('request_quotation_kontrak_H.sales_id');

        $data = DB::query()
            ->fromSub($quotationNon->unionAll($quotationKon), 'x')
            ->select(
                'sales_id',
                DB::raw('SUM(total_request_quotation) as total_request_quotation'),
                DB::raw('SUM(total_biaya_akhir) as total_biaya_akhir'),
                DB::raw('SUM(pelanggan_lama) as pelanggan_lama'),
                DB::raw('SUM(pelanggan_baru) as pelanggan_baru'),
                DB::raw('SUM(total_biaya_pelanggan_lama) as total_biaya_pelanggan_lama'),
                DB::raw('SUM(total_biaya_pelanggan_baru) as total_biaya_pelanggan_baru')
            )
            ->groupBy('sales_id')
            ->get();

        $result = [];

        foreach ($data as $record) {
            $result[(int) $record->sales_id] = [
                'total_request_quotation'    => (int) $record->total_request_quotation,
                'total_biaya_akhir'          => (float) $record->total_biaya_akhir,
                'pelanggan_lama'             => (int) $record->pelanggan_lama,
                'pelanggan_baru'             => (int) $record->pelanggan_baru,
                'total_biaya_pelanggan_lama' => (float) $record->total_biaya_pelanggan_lama,
                'total_biaya_pelanggan_baru' => (float) $record->total_biaya_pelanggan_baru,
            ];
        }

        return $result;
    }

    private function processTeamDataQuotation(array $allMembers, array $quotationData): array
    {
        $teamsData = [];

        foreach ($allMembers as $member) {
            $teamIndex = $member['team_index'];
            $bucket    = $this->gradeBucket($member['grade']);
            $quotation = $quotationData[$member['id']] ?? null;

            if (!isset($teamsData[$teamIndex])) {
                $teamsData[$teamIndex] = [
                    'staff'      => [],
                    'supervisor' => [],
                    'manager'    => [],
                    'team_name'  => $member['team_name'] ?? ('Tim ' . ($teamIndex + 1)),
                ];
            }

            $memberData = array_merge($member['data'], [
                'total_request_quotation'    => $quotation['total_request_quotation'] ?? 0,
                'total_biaya_akhir'          => $quotation['total_biaya_akhir'] ?? 0,
                'pelanggan_lama'             => $quotation['pelanggan_lama'] ?? 0,
                'pelanggan_baru'             => $quotation['pelanggan_baru'] ?? 0,
                'total_biaya_pelanggan_lama' => $quotation['total_biaya_pelanggan_lama'] ?? 0,
                'total_biaya_pelanggan_baru' => $quotation['total_biaya_pelanggan_baru'] ?? 0,
                'is_resigned'                => (bool) ($member['is_resigned'] ?? false),
                'position'                   => $member['position'] ?? null,
                'superior_name'              => $member['superior_name'] ?? null,
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

    private function getAllTeamMembers(array $quotationData): array
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
                    $quotationData[(int) $staff->id] ?? null
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

    private function salesExecutiveIds(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            array_map('trim', explode(',', (string) env('SALES_EXECUTIVE', '41')))
        )));
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

    private function shouldShowMember($member, ?array $quotation): bool
    {
        if ($this->isActiveMember($member)) {
            return true;
        }

        return $this->hasQuotationActivity($quotation);
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

    private function hasQuotationActivity(?array $quotation): bool
    {
        if ($quotation === null) {
            return false;
        }

        return ($quotation['total_request_quotation'] ?? 0) > 0
            || ($quotation['total_biaya_akhir'] ?? 0) > 0;
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

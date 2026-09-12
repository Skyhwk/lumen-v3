<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GetBawahan;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{QuotationKontrakH, QuotationNonKontrak, DailyQsd, MasterKaryawan, MasterTargetSales};

class ViewPerSalesController extends Controller
{
    private array $indoMonths = [
        1 => 'januari',  2 => 'februari', 3  => 'maret',    4  => 'april',
        5 => 'mei',      6 => 'juni',     7  => 'juli',     8  => 'agustus',
        9 => 'september',10 => 'oktober', 11 => 'november', 12 => 'desember',
    ];

    private array $salesPosition = [148, 24]; // 148 : CRO, 24 : SO
    private array $managerIds = [890];
    private array $categoryStr;

    public function __construct()
    {
        Carbon::setLocale('id');
        $this->categoryStr = config('kategori.id');
    }

    // =========================================================================
    // ENTRY POINT
    // =========================================================================
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        
        try {
            $now            = Carbon::now();
            $currentYear    = (int) ($request->input('tahun') ?? $now->year);
            $currentMonth   = (int) ($request->input('bulan') ?? $now->month);
            $currentPeriode = $now->format('Y-m');
            $startOfMonth   = $now->copy()->startOfMonth();
            $tahun          = $request->input('tahun', $currentYear);
            
            // 1. Kumpulkan kandidat + bulk metrics untuk cek visibility
            $candidateIds = $this->collectCandidateIds();
            $bulkData     = $this->fetchAllBulkData($candidateIds, $currentYear, $currentMonth, $currentPeriode, $tahun);
            $metricsMap   = $this->buildMetricsMap($candidateIds, $currentMonth, $currentPeriode, $tahun, $bulkData);

            // 2. Filter: aktif selalu tampil; resign/non-aktif hanya jika ada jualan/order
            $members = $this->getAllTeamMembers($metricsMap);

            // 3. Build result — metric sudah dihitung di metricsMap
            $result = array_map(
                fn($member) => [
                    'sales_id'    => $member['id'],
                    'sales_name'  => $member['name'],
                    'team'        => $member['team_name'],
                    'grade'       => $member['grade'],
                    'jabatan'     => $member['jabatan'],
                    'is_resigned' => (bool) ($member['is_resigned'] ?? false),
                    'data'        => $metricsMap[$member['id']] ?? $this->emptyMetrics(),
                ],
                $members
            );
            return response()->json(['data' => $result]);
        } catch (\Throwable $th) {
            return response()->json(['line' => $th->getLine(), 'message' => $th->getMessage(),'file'=>$th->getFile()], 500);
        }
    }

    // =========================================================================
    // STEP 2: BULK FETCH — semua query dikumpulkan di sini, dipanggil SEKALI
    // Total query: 7 (tidak peduli berapa banyak sales)
    // =========================================================================
    private function fetchAllBulkData(
        array  $salesIds,
        int    $year,
        int    $month,
        string $periode,
        int    $tahun
    ): array {
        $statusExcluded = ['ordered', 'rejected', 'void'];
        $statusOrdered  = 'ordered';

        // ── Query 1: Quotation Kontrak (count + amount, grouped by sales & status bucket) ──
        // Kita ambil raw lalu classify di PHP — 1 query untuk semua kombinasi
        $kontrakRows = QuotationKontrakH::whereIn('sales_id', $salesIds)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->with(['detail' => fn($q) => 
                $q->select('id_request_quotation_kontrak_h', 'biaya_akhir', 'total_ppn')
            ])
            ->where('is_active',true)
            ->select('id', 'sales_id', 'flag_status')
            ->get();

        // ── Query 2: Quotation Non-Kontrak (count + amount, grouped by sales & status bucket) ──
        $nonKontrakRows = QuotationNonKontrak::whereIn('sales_id', $salesIds)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('is_active',true)
            ->select('sales_id', 'flag_status', DB::raw('(biaya_akhir - total_ppn) as net_amount'))
            ->get();

        // ── Query 3: DailyQsd + relasi (1 query dengan eager load) ──
        $dailyQsdAll = DailyQsd::with(['orderHeader' => fn($q) => $q->with('orderDetail:id,id_order_header,periode,kategori_3')])
            ->whereIn('sales_id', $salesIds)
            ->whereYear('tanggal_kelompok', $year)
            ->whereMonth('tanggal_kelompok', $month)
            ->select('sales_id', 'tanggal_kelompok', 'total_revenue', 'status_customer', 'kontrak', 'periode', 'no_order')
            ->get()
            ->each(function ($qsd) {
                // Filter orderDetail per periode di PHP (sudah eager loaded)
                if ($qsd->periode && optional($qsd->orderHeader)->orderDetail) {
                    $filtered = $qsd->orderHeader->orderDetail->filter(
                        fn($od) => $od->periode === $qsd->periode
                    )->values();
                    $qsd->orderHeader->setRelation('orderDetail', $filtered);
                }
            })
            ->groupBy('sales_id');

        // ── Query 4: MasterTargetSales (semua aktif untuk tahun ini) ──
        $targetAll = MasterTargetSales::whereIn('karyawan_id', $salesIds)
            ->where(['is_active' => true, 'tahun' => $tahun])
            ->latest()
            ->get()
            ->keyBy('karyawan_id');   // map by karyawan_id untuk O(1) lookup

        return compact('kontrakRows', 'nonKontrakRows', 'dailyQsdAll', 'targetAll');
    }

    // =========================================================================
    // STEP 3: BUILD METRIC PER SALES — pure in-memory, 0 query
    // =========================================================================
    private function buildMetrics(
        int    $salesId,
        int    $currentMonth,
        string $currentPeriode,
        int    $tahun,
        array  $bulkData
    ): array {
        ['kontrakRows'    => $kontrakRows,
         'nonKontrakRows' => $nonKontrakRows,
         'dailyQsdAll'    => $dailyQsdAll,
         'targetAll'      => $targetAll] = $bulkData;

        $statusExcluded = ['ordered', 'rejected', 'void'];
        $statusOrdered  = 'ordered';

        // ── Kontrak milik sales ini ──
        $kontrak = $kontrakRows->where('sales_id', $salesId);

        $kontrakNew   = $kontrak->whereNotIn('flag_status', $statusExcluded);
        $kontrakExist = $kontrak->where('flag_status', $statusOrdered);

        $countQtNew   = $kontrakNew->count();
        $countQtExist = $kontrakExist->count();

        $amountQtNew   = $kontrakNew->flatMap->detail->sum(fn($d) => $d->biaya_akhir - $d->total_ppn);
        $amountQtExist = $kontrakExist->flatMap->detail->sum(fn($d) => $d->biaya_akhir - $d->total_ppn);

        // ── Non-Kontrak milik sales ini ──
        $nonKontrak = $nonKontrakRows->where('sales_id', $salesId);

        $countQtNew   += $nonKontrak->whereNotIn('flag_status', $statusExcluded)->count();
        $countQtExist += $nonKontrak->where('flag_status', $statusOrdered)->count();

        $amountQtNew   += $nonKontrak->whereNotIn('flag_status', $statusExcluded)->sum('net_amount');
        $amountQtExist += $nonKontrak->where('flag_status', $statusOrdered)->sum('net_amount');

        // ── DailyQsd milik sales ini ──
        $qsdList    = $dailyQsdAll->get($salesId, collect());
        $revenue    = $qsdList->sum('total_revenue');
        $newCust    = $qsdList->where('status_customer', 'new')->count();
        $existCust  = $qsdList->where('status_customer', 'exist')->count();
        $ordNew     = $qsdList->where('status_customer', 'new')->sum('total_revenue');
        $ordExist   = $qsdList->where('status_customer', 'exist')->sum('total_revenue');
        $ordKontrak = $qsdList->where('kontrak', 'C')->sum('total_revenue');
        $ordNonK    = $qsdList->where('kontrak', 'N')->sum('total_revenue');

        // ── Target ──
        $targetSales    = $targetAll->get($salesId);
        $targetAmount   = 0;
        $targetKategori = '0/0';
        $achieved       = 0;
        $targetCount    = 0;

        if ($targetSales) {
            $monthKey       = $this->indoMonths[$currentMonth];
            $targetByCategory = collect($targetSales->$monthKey ?? [])->filter(fn($v) => $v > 0);
            $targetCount    = $targetByCategory->count();

            // Flatten semua orderDetail dari qsdList sekali saja
            $allOrderDetails = $qsdList->flatMap(fn($q) => optional($q->orderHeader)->orderDetail ?? collect());

           $achievedSum = $targetByCategory->map(function ($target, $category) use ($allOrderDetails) {
                $achieved = $allOrderDetails
                    ->filter(fn($od) => collect($this->categoryStr[$category])->contains($od->kategori_3))
                    ->count();
                return ($target && $achieved) ? floor($achieved / $target) : 0;
            })->sum();

            $achieved     = $achievedSum === 0 ? 1 : $achievedSum;
            $targetKategori = $achieved . '/' . $targetCount;

            // Target amount
            $targetJson   = json_decode($targetSales->target ?? '[]', true);
            $targetAmount = $targetJson[$currentPeriode] ?? 0;
        }

        return [
            'new_customers'     => $newCust,
            'exist_customers'   => $existCust,
            'all_qt_new'        => $countQtNew,
            'all_qt_exist'      => $countQtExist,
            'amount_qt_new'     => $amountQtNew,
            'amount_qt_exist'   => $amountQtExist,
            'revenue'           => $revenue,
            'target_amount'     => $targetAmount,
            'target_kategori'   => $targetKategori,
            'order_new'         => $ordNew,
            'order_existing'    => $ordExist,
            'order_kontrak'     => $ordKontrak,
            'order_non_kontrak' => $ordNonK,
        ];
    }

    // =========================================================================
    // TEAM HELPERS
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

    /**
     * Sales Executive: selalu tampil, grade manager (tanpa bawahan), punya data penjualan sendiri.
     */
    private function getSalesExecutiveMembers(): array
    {
        $ids = $this->salesExecutiveIds();
        if (empty($ids)) {
            return [];
        }

        return MasterKaryawan::whereIn('id', $ids)
            ->orderBy('nama_lengkap')
            ->get()
            ->map(fn($item) => [
                'id'          => $item->id,
                'name'        => $item->nama_lengkap,
                'team_index'  => -1,
                'team_name'   => 'Sales Executive',
                'grade'       => 'manager',
                'jabatan'     => $item->id_jabatan,
                'is_resigned' => $this->isResignedSalesStaff($item),
            ])
            ->values()
            ->all();
    }

    private function getAllTeamMembers(array $metricsMap): array
    {
        $allMembers = [];
        $addedIds   = [];

        foreach ($this->getSalesExecutiveMembers() as $member) {
            $allMembers[] = $member;
            $addedIds[]   = $member['id'];
        }

        foreach ($this->managerIds as $teamIndex => $managerId) {
            $pool = GetBawahan::on('id', $managerId)->all()->keyBy('id');
            if ($pool->isEmpty()) {
                continue;
            }

            $visibleLeaves = $this->salesLeaves($pool)->filter(
                fn($staff) => $this->shouldShowMember(
                    $staff,
                    $metricsMap[(int) $staff->id] ?? $this->emptyMetrics()
                )
            );

            if ($visibleLeaves->isEmpty()) {
                continue;
            }

            $includedIds = $this->collectIncludedMemberIds($pool, $visibleLeaves);
            if (empty($includedIds)) {
                continue;
            }

            $orderedMembers = $this->orderMembersHierarchy($pool, $includedIds);

            foreach ($orderedMembers as $item) {
                if (in_array($item->id, $addedIds, true)) {
                    continue;
                }

                $addedIds[] = $item->id;
                $allMembers[] = [
                    'id'          => $item->id,
                    'name'        => $item->nama_lengkap,
                    'team_index'  => $teamIndex,
                    'team_name'   => 'Tim ' . ($teamIndex + 1),
                    'grade'       => strtolower($item->grade),
                    'jabatan'     => $item->id_jabatan,
                    'is_resigned' => $this->isResignedSalesStaff($item),
                ];
            }
        }

        return $allMembers;
    }

    private function buildMetricsMap(
        array $ids,
        int $currentMonth,
        string $currentPeriode,
        int $tahun,
        array $bulkData
    ): array {
        $map = [];

        foreach ($ids as $id) {
            $map[(int) $id] = $this->buildMetrics((int) $id, $currentMonth, $currentPeriode, $tahun, $bulkData);
        }

        return $map;
    }

    private function salesLeaves($pool)
    {
        return $pool->filter(
            fn($item) => in_array((int) $item->id_jabatan, $this->salesPosition, true)
        );
    }

    /**
     * Aktif selalu tampil. Non-aktif/resign hanya jika ada nilai jualan atau order di periode ini.
     */
    private function shouldShowMember($member, array $metrics): bool
    {
        if ($this->isActiveMember($member)) {
            return true;
        }

        return $this->hasSalesActivity($metrics);
    }

    private function isActiveMember($member): bool
    {
        return (int) ($member->is_active ?? 0) === 1;
    }

    /** SO/CRO non-aktif — ditandai resign di frontend (table-danger). */
    private function isResignedSalesStaff($member): bool
    {
        return in_array((int) $member->id_jabatan, $this->salesPosition, true)
            && !$this->isActiveMember($member);
    }

    private function hasSalesActivity(array $metrics): bool
    {
        return ($metrics['revenue'] ?? 0) > 0
            || ($metrics['all_qt_new'] ?? 0) > 0
            || ($metrics['all_qt_exist'] ?? 0) > 0
            || ($metrics['amount_qt_new'] ?? 0) > 0
            || ($metrics['amount_qt_exist'] ?? 0) > 0
            || ($metrics['order_new'] ?? 0) > 0
            || ($metrics['order_existing'] ?? 0) > 0
            || ($metrics['order_kontrak'] ?? 0) > 0
            || ($metrics['order_non_kontrak'] ?? 0) > 0;
    }

    private function emptyMetrics(): array
    {
        return [
            'new_customers'     => 0,
            'exist_customers'   => 0,
            'all_qt_new'        => 0,
            'all_qt_exist'      => 0,
            'amount_qt_new'     => 0,
            'amount_qt_exist'   => 0,
            'revenue'           => 0,
            'target_amount'     => 0,
            'target_kategori'   => '0/0',
            'order_new'         => 0,
            'order_existing'    => 0,
            'order_kontrak'     => 0,
            'order_non_kontrak' => 0,
        ];
    }

    /**
     * Kumpulkan SO/CRO (visibleLeaves) + seluruh atasan dalam pool.
     * Senior Manager tidak ikut — hanya dipakai sebagai root GetBawahan.
     */
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

            // Staff: sudah difilter di visibleLeaves. Atasan: wajib masih aktif.
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

    /** Urutkan member DFS per cabang: Manager → Supervisor → Staff */
    private function orderMembersHierarchy($pool, array $includedIds): array
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

        $ordered = [];
        $visited = [];

        foreach ($roots as $rootId) {
            $this->appendMemberSubtree((int) $rootId, $pool, $includedSet, $visited, $ordered);
        }

        return $ordered;
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

    /** Cari atasan terdekat yang ikut ditampilkan (lewati atasan resign/non-aktif). */
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

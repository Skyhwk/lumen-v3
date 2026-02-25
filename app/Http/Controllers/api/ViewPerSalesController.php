<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GetBawahan;
use App\Services\GetDepartmentHierarchy;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{QuotationKontrakH, QuotationNonKontrak, OrderHeader, DailyQsd, MasterTargetSales};

class ViewPerSalesController extends Controller
{
    private array $indoMonths = [
        1 => 'januari',  2 => 'februari', 3  => 'maret',    4  => 'april',
        5 => 'mei',      6 => 'juni',     7  => 'juli',     8  => 'agustus',
        9 => 'september',10 => 'oktober', 11 => 'november', 12 => 'desember',
    ];

    private array $managerIds = [19, 41, 14];
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

            // 1. Ambil seluruh member tim (flat array)
            $members   = $this->getAllTeamMembers();                    // [{id, name, team_name, grade, ...}]
            $salesIds  = array_column($members, 'id');                 // [1, 2, 3, ...]

            // 2. Ambil SEMUA data sekaligus (bulk — hanya N query total, bukan N × query)
            $bulkData  = $this->fetchAllBulkData($salesIds, $currentYear, $currentMonth, $currentPeriode, $tahun);

            // 3. Build result — hitung metric dari collection in-memory (0 query tambahan)
            $result = array_map(
                fn($member) => [
                    'sales_id'   => $member['id'],
                    'sales_name' => $member['name'],
                    'team'       => $member['team_name'],
                    'grade'      => $member['grade'],
                    'data'       => $this->buildMetrics($member['id'], $currentMonth, $currentPeriode, $tahun, $bulkData),
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
            ->whereYear('tanggal_penawaran', $year)
            ->whereMonth('tanggal_penawaran', $month)
            ->with(['detail' => fn($q) => $q->where('periode_kontrak', $periode)
                ->select('id_request_quotation_kontrak_h', 'biaya_akhir', 'total_ppn')
            ])
            ->select('id', 'sales_id', 'flag_status')
            ->get();

        // ── Query 2: Quotation Non-Kontrak (count + amount, grouped by sales & status bucket) ──
        $nonKontrakRows = QuotationNonKontrak::whereIn('sales_id', $salesIds)
            ->whereYear('tanggal_penawaran', $year)
            ->whereMonth('tanggal_penawaran', $month)
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
    private function getAllTeamMembers(): array
    {
        $allMembers = [];
        $addedIds   = [];

        foreach ($this->managerIds as $teamIndex => $managerId) {
            $members = GetBawahan::on('id', $managerId)
                ->all()
                ->filter(function ($item) use (&$addedIds) {
                    // 2. Cek apakah ID sudah pernah ditambahkan (duplikasi)
                    if (in_array($item->id, $addedIds)) {
                        return false;
                    }
                    // Simpan ID agar tidak duplikat dan loloskan filter
                    $addedIds[] = $item->id;
                    return true;
                });

            foreach ($members as $item) {
                $allMembers[] = [
                    'id'         => $item->id,
                    'name'       => $item->nama_lengkap,
                    'team_index' => $teamIndex,
                    'team_name'  => 'Tim ' . ($teamIndex + 1),
                    'grade'      => strtolower($item->grade),
                ];
            }
        }

        return $allMembers;
    }
}

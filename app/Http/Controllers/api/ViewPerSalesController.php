<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GetBawahan;
use App\Services\GetDepartmentHierarchy;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{QuotationKontrakH,QuotationNonKontrak,OrderHeader,DailyQsd};

class ViewPerSalesController extends Controller
{

    /* public function index(Request $request)
    {
        try {
            $year = $request->input('tahun', Carbon::now()->year);

            $this->emptyMonths = $this->getEmptyOrder();

            $emptySummary = [
                'penawaran' => $this->emptyMonths,
                'order'     => $this->emptyMonths,
                'target'    => $this->emptyMonths,
            ];

            $allMembers   = $this->getAllTeamMembers();
            $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();
            $this->teamLookup = $this->buildTeamLookupMap($allMembers);
            
            $bulkData = $this->getSalesSummaryData($allMemberIds, $year);

            // Cek resigned members
            $resignedMemberIds = array_diff(array_keys($bulkData), $allMemberIds);
            if (!empty($resignedMemberIds)) {
                $resignedMembers = $this->getResignedMembersWithTeam($resignedMemberIds);
                $allMembers      = array_merge($allMembers, $resignedMembers);
            }

            $teamsData = $this->processTeamSummaryData($allMembers, $bulkData, $emptySummary);

            $allteam_total_periode = $emptySummary;

            foreach ($teamsData as &$teamData) {
                $teamTotal      = $emptySummary;
                $teamTotalStaff = $emptySummary;

                foreach (['staff', 'supervisor', 'manager'] as $grade) {
                    if (!empty($teamData[$grade])) {
                        foreach ($teamData[$grade] as &$member) {
                            foreach (['penawaran', 'order', 'target'] as $aspect) {
                                foreach ($member[$aspect] as $month => $amount) {
                                    $teamTotal[$aspect][$month] += $amount;
                                    $allteam_total_periode[$aspect][$month] += $amount;

                                    if ($grade === 'staff') {
                                        $teamTotalStaff[$aspect][$month] += $amount;
                                    }
                                }
                            }
                        }
                    }
                }

                $teamData['team_total_periode']       = $teamTotal;
                $teamData['team_total_staff_periode'] = $teamTotalStaff;

                $teamData['team_total'] = [
                    'penawaran' => array_sum($teamTotal['penawaran']),
                    'order'     => array_sum($teamTotal['order']),
                    'target'    => array_sum($teamTotal['target']),
                ];
            }

        } catch (\Throwable $th) {
            return response()->json([
                "line"    => $th->getLine(),
                "message" => $th->getMessage(),
                "file"    => $th->getFile()
            ], 500);
        }

        return response()->json([
            'success'           => true,
            'year'              => $year,
            'data'              => array_values($teamsData),
            'all_total_periode' => [
                'penawaran'       => $allteam_total_periode['penawaran'],
                'order'           => $allteam_total_periode['order'],
                'target'          => $allteam_total_periode['target'],
                'grand_penawaran' => array_sum($allteam_total_periode['penawaran']),
                'grand_target'    => array_sum($allteam_total_periode['target']),
            ],
            'all_total' => array_sum($allteam_total_periode['order']), // total order tahunan
            'message'   => 'Data summary 3 aspek berhasil diproses!',
        ], 200);
    } */
     
    public function index (Request $request)
    {
        try {
            //code...
        
        // 1. Persiapan Variabel Waktu & Parameter
        $salesId = 40; // Ganti dengan parameter sales_id yang sedang dicari
        $startOfMonth = Carbon::now()->startOfMonth();
        $now = Carbon::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;
        $currentPeriode = $now->format('Y-m'); // Format tahun-bulan (contoh: 2026-02)

        $statusNewExcluded = ['ordered', 'rejected', 'void'];
        $statusExistIncluded = 'ordered';

        // =========================================================================
        // === METRIC 1 & 2: COUNT (All QT New & All QT Exist) ===
        // Logic waktu: menggunakan tanggal_penawaran (Header/Non-Kontrak)
        // =========================================================================

        // 1. All QT New (Count)
        $countKontrakNew = QuotationKontrakH::where('sales_id', $salesId)
            ->whereYear('tanggal_penawaran', $currentYear)
            ->whereMonth('tanggal_penawaran', $currentMonth)
            ->whereNotIn('flag_status', $statusNewExcluded)
            ->count();

        $countNonKontrakNew = QuotationNonKontrak::where('sales_id', $salesId)
            ->whereYear('tanggal_penawaran', $currentYear)
            ->whereMonth('tanggal_penawaran', $currentMonth)
            ->whereNotIn('flag_status', $statusNewExcluded)
            ->count();

        $totalCountQtNew = $countKontrakNew + $countNonKontrakNew;
        // 2. All QT Exist (Count)
        $countKontrakExist = QuotationKontrakH::where('sales_id', $salesId)
            ->whereYear('tanggal_penawaran', $currentYear)
            ->whereMonth('tanggal_penawaran', $currentMonth)
            ->where('flag_status', $statusExistIncluded)
            ->count();
        $countNonKontrakExist = QuotationNonKontrak::where('sales_id', $salesId)
            ->where('tanggal_penawaran', '>=', $startOfMonth)
            ->whereMonth('tanggal_penawaran', $currentMonth)
            ->where('flag_status', $statusExistIncluded)
            ->count();
        $totalCountQtExist = $countKontrakExist + $countNonKontrakExist;
        // =========================================================================
        // === METRIC 3 & 4: REVENUE (Amount QT New & Amount QT Exist) ===
        // Logic waktu Kontrak: menggunakan periode_kontrak di tabel Detail
        // Logic waktu Non-Kontrak: menggunakan tanggal_penawaran
        // =========================================================================

        // 3. Amount QT New (Revenue)
        // KONTRAK: Filter header sales_id & status, lalu filter relasi detail berdasarkan periode_kontrak
        $amountKontrakNew = QuotationKontrakH::where('sales_id', $salesId)
            ->whereYear('tanggal_penawaran', $currentYear)
            ->whereMonth('tanggal_penawaran', $currentMonth)
            ->whereNotIn('flag_status', $statusNewExcluded)
            ->with(['detail' => function($query) use ($currentPeriode) {
                $query->where('periode_kontrak', $currentPeriode);
            }])
            ->get()
            // Menggabungkan semua detail yang lolos filter dan menjumlahkan rumusnya
            ->flatMap->details
            ->sum(function ($detail) {
                return $detail->biaya_akhir - $detail->total_ppn;
            });
        
        // NON-KONTRAK: Filter langsung di tabel yang sama
        $amountNonKontrakNew = QuotationNonKontrak::where('sales_id', $salesId)
            ->whereYear('tanggal_penawaran', $currentYear)
            ->whereMonth('tanggal_penawaran', $currentMonth)
            ->whereNotIn('flag_status', $statusNewExcluded)
            ->sum(DB::raw('biaya_akhir - total_ppn'));

        $totalAmountQtNew = $amountKontrakNew + $amountNonKontrakNew;
        // 4. Amount QT Exist (Revenue)
        // KONTRAK
        $amountKontrakExist = QuotationKontrakH::where('sales_id', $salesId)
            ->where('flag_status', $statusExistIncluded)
            ->with(['detail' => function($query) use ($currentPeriode) {
                $query->where('periode_kontrak', $currentPeriode);
            }])
            ->get()
            ->flatMap->details
            ->sum(function ($detail) {
                return $detail->biaya_akhir - $detail->total_ppn;
            });

        // NON-KONTRAK
        $amountNonKontrakExist = QuotationNonKontrak::where('sales_id', $salesId)
            ->whereYear('tanggal_penawaran', $currentYear)
            ->whereMonth('tanggal_penawaran', $currentMonth)
            ->where('flag_status', $statusExistIncluded)
            ->sum(DB::raw('biaya_akhir - total_ppn'));

        $totalAmountQtExist = $amountKontrakExist + $amountNonKontrakExist;
        //================================================================
        //======================= ORDER SECTION ==========================
        //================================================================
        $newOrders = OrderHeader::where('sales_id', $salesId)
            ->where('tanggal_order', '>=', $startOfMonth)
            ->whereNotIn('id_pelanggan', function($query) use ($startOfMonth) {
                $query->select('id_pelanggan')
                    ->from('order_header')
                    ->where('tanggal_order', '<', $startOfMonth)
                    ->where('flag_status', 'ordered'); // Pastikan hanya menghitung yang sukses
            })
            ->count();
        $existingOrders = OrderHeader::where('sales_id', $salesId)
            ->where('tanggal_order', '>=', $startOfMonth)
            ->whereIn('id_pelanggan', function($query) use ($startOfMonth) {
                $query->select('id_pelanggan')
                    ->from('order_header')
                    ->where('tanggal_order', '<', $startOfMonth)
                    ->where('flag_status', 'ordered');
            })
            ->count();
        // 1. Ambil semua data sekaligus (hanya ambil kolom yang dibutuhkan agar ringan)
            $allOrders = DailyQsd::where('sales_id', 40)
                ->where(function ($query) use ($currentPeriode) {
                    $query->where(function ($q) use ($currentPeriode) {
                        $q->where('no_quotation', 'like', '%/QTC/%')
                        ->where('periode', $currentPeriode);
                    })
                    ->orWhere(function ($q) use ($currentPeriode) {
                        $q->where('no_quotation', 'like', '%/QT/%')
                        ->where('no_quotation', 'not like', '%/QTC/%')
                        ->where('tanggal_sampling_min', 'like', $currentPeriode . '%');
                    });
                })
                ->get(['no_quotation', 'status_customer', 'total_revenue']);
            
            $duplikat = $allOrders->groupBy('no_quotation')
                ->filter(fn($group) => $group->count() > 1);
            dd([
                'total_rows'       => $allOrders->count(),
                'unique_quotation' => $allOrders->pluck('no_quotation')->unique()->count(),
                'duplikat'         => $duplikat->keys(), // no_quotation yang duplikat
                'exist_count'      => $allOrders->where('status_customer', 'exist')->count(),
                'new_count'        => $allOrders->where('status_customer', 'new')->count(),
            ]);

            // 2. Mapping Data menggunakan Collection
            // Mapping Data menggunakan Collection
            $mappingResult = [
                // --- GABUNGAN (TOTAL ORDER) ---
                'total_order'        => $allOrders->sum('total_revenue'),
                'order_new'          => $allOrders->where('status_customer', 'new')->sum('total_revenue'),
                'order_exist'        => $allOrders->where('status_customer', 'exist')->sum('total_revenue'),
                'customer_exist'        => $allOrders->where('status_customer', 'exist')->count(),
                'customer_new'        => $allOrders->where('status_customer', 'new')->count(),

                // --- KATEGORI KONTRAK (QTC) & NON-KONTRAK (QT) ---
                'order_kontrak'      => $allOrders->filter(fn($item) => str_contains($item->no_quotation, 'QTC'))->sum('total_revenue'),
                'order_non_kontrak'  => $allOrders->filter(fn($item) => str_contains($item->no_quotation, 'QT') && !str_contains($item->no_quotation, 'QTC'))->sum('total_revenue')
            ];
        return [
            'customer_new'       => $mappingResult['customer_new'],
            'customer_exist'       => $mappingResult['customer_exist'],
            'all_qt_new_count'       => $totalCountQtNew,
            'all_qt_exist_count'     => $totalCountQtExist,
            'amount_qt_new_revenue'  => $totalAmountQtNew,
            'amount_qt_exist_revenue'=> $totalAmountQtExist,
            'total_order'=> $mappingResult['total_order'],
            'order_new'=> $mappingResult['order_new'],
            'order_exist'=> $mappingResult['order_exist'],
            'order_kontrak'=> $mappingResult['order_kontrak'],
            'order_non_kontrak'=> $mappingResult['order_non_kontrak'],
        ];
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(["line"=>$th->getLine(),"message"=>$th->getMessage()],500);
        }
    }

    private function getEmptyOrder(): array
    {
        return [
            'Jan' => 0, 'Feb' => 0, 'Mar' => 0, 'Apr' => 0,
            'Mei' => 0, 'Jun' => 0, 'Jul' => 0, 'Agt' => 0,
            'Sep' => 0, 'Okt' => 0, 'Nov' => 0, 'Des' => 0,
        ];
    }

    private function getSalesSummaryData(array $memberIds, int $year): array
    {
        $emptyMonths = $this->getEmptyOrder();

        $data = [];
        foreach ($memberIds as $id) {
            $data[$id] = [
                'penawaran' => $emptyMonths,
                'order'     => $emptyMonths,
                'target'    => $emptyMonths,
            ];
        }

        // Map integer bulan ke key string Indonesia
        $monthMap = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4  => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8  => 'Agt',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        // Target month map (kolom DB pakai nama Inggris)
        $targetMonthMap = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4  => 'apr',
            5 => 'may', 6 => 'jun', 7 => 'jul', 8  => 'aug',
            9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dec',
        ];

        // 1. PENAWARAN - Non Kontrak
        $nonKontrak = \App\Models\QuotationNonKontrak::selectRaw(
                'sales_id, MONTH(tanggal_penawaran) as month, SUM(biaya_akhir) as total'
            )
            ->where('is_active',1)
            ->whereIn('sales_id', $memberIds)
            ->whereYear('tanggal_penawaran', $year)
            ->groupBy('sales_id', 'month')
            ->get();

        foreach ($nonKontrak as $row) {
            $key = $monthMap[(int)$row->month];
            $data[$row->sales_id]['penawaran'][$key] += (float)$row->total;
        }

        // 2. PENAWARAN - Kontrak (group by periode_kontrak di detail)
        $kontrak = \App\Models\QuotationKontrakD::from('request_quotation_kontrak_D as detail')
            ->join('request_quotation_kontrak_H as header', 'header.id', '=', 'detail.id_request_quotation_kontrak_h')
            ->selectRaw(
                'header.sales_id, 
                CAST(SUBSTRING(detail.periode_kontrak, 6, 2) AS UNSIGNED) as month, 
                SUM(detail.biaya_akhir) as total'
            )
            ->where('header.is_active',1)
            ->whereYear('header.tanggal_penawaran')
            ->whereIn('header.sales_id', $memberIds)
            ->whereRaw('LEFT(detail.periode_kontrak, 4) = ?', [$year])
            ->groupBy('header.sales_id', 'month')
            ->get();

        foreach ($kontrak as $row) {
            $key = $monthMap[(int)$row->month];
            $data[$row->sales_id]['penawaran'][$key] += (float)$row->total;
        }

        // 3. ORDER
        $orders = \App\Models\OrderHeader::selectRaw(
                'sales_id, MONTH(tanggal_penawaran) as month, SUM(biaya_akhir - total_ppn) as total'
            )
            ->whereIn('sales_id', $memberIds)
            ->whereYear('tanggal_penawaran', $year)
            ->groupBy('sales_id', 'month')
            ->get();

        foreach ($orders as $row) {
            $key = $monthMap[(int)$row->month];
            $data[$row->sales_id]['order'][$key] += (float)$row->total;
        }

        // 4. TARGET
        $targets = \DB::table('target_sales')
            ->whereIn('user_id', $memberIds)
            ->where('year', $year)
            ->get();

        foreach ($targets as $row) {
            foreach ($targetMonthMap as $num => $col) {
                $key = $monthMap[$num];
                $data[$row->user_id]['target'][$key] += (float)$row->{$col};
            }
        }

        return $data;
    }

    private function processTeamSummaryData(array $allMembers, array $bulkData, array $emptySummary): array
    {
        $teams = [];

        foreach ($allMembers as $member) {
            $teamId  = $member['team_index'];
            $salesId = $member['id'];
            $grade   = strtolower($member['grade']);

            if (!isset($teams[$teamId])) {
                $teams[$teamId] = [
                    'team_id'    => $teamId,
                    'team_name'  => $member['team_name'],
                    'manager'    => [],
                    'supervisor' => [],
                    'staff'      => [],
                ];
            }

            $memberData = $bulkData[$salesId] ?? $emptySummary;

            // Hitung total_order (sum semua bulan)
            $totalOrder     = array_sum($memberData['order']);
            $totalPenawaran = array_sum($memberData['penawaran']);
            $totalTarget    = array_sum($memberData['target']);

            // atasan_langsung: pastikan array agar .includes() di frontend tidak error
            $atasanLangsung = $member['data']['atasan_langsung'] ?? [];
            if (!is_array($atasanLangsung)) {
                // Jika string/null, convert ke array
                 $atasanLangsung = json_decode($atasanLangsung, true) ?? [];
            }
            if (is_array($atasanLangsung)) {
                $atasanLangsung = collect($atasanLangsung)->flatMap(function($item) {
                    if (is_string($item) && str_starts_with(trim($item), '[')) {
                        return json_decode($item, true) ?? [$item];
                    }
                    return [$item];
                })->map(fn($id) => (int)$id) // cast ke integer agar konsisten
                ->values()
                ->toArray();
            }

            $teams[$teamId][$grade][] = [
                'id'              => $salesId,
                'name'            => $member['name'],
                'nama_lengkap'    => $member['name'],
                'atasan_langsung' => $atasanLangsung,
                'is_active'       => $member['data']['is_active'] ?? 1,
                'image'           => $member['data']['image'] ?? null,
                'total_order'     => $totalOrder,
                'total_penawaran' => $totalPenawaran,
                'total_target'    => $totalTarget,
                'penawaran'       => $memberData['penawaran'],
                'order'           => $memberData['order'],
                'target'          => $memberData['target'],
            ];
        }

        return $teams;
    }

    private function getAllTeamMembers(): array
    {
        $teams      = $this->getTeams();
        $allMembers = [];

        foreach ($teams as $teamIndex => $team) {
            // team_name bisa diambil dari nama manager jika perlu,
            // atau sesuaikan dengan struktur GetBawahan Anda
            foreach ($team as $member) {
                $allMembers[] = [
                    'id'         => $member['id'],
                    'name'       => $member['nama_lengkap'],  // <-- perbaikan: ambil nama
                    'team_index' => $teamIndex,
                    'team_name'  => 'Tim ' . ($teamIndex + 1), // sesuaikan jika ada nama tim
                    'grade'      => strtolower($member['grade']),
                    'data'       => $member,
                ];
            }
        }

        return $allMembers;
    }

    private function getTeams(): array
    {
        $teams    = [];
        $addedIds = [];

        foreach ($this->managerIds as $manager) {
            $team = GetBawahan::on('id', $manager)
                ->all()
                ->filter(function ($item) use (&$addedIds) {
                    if (in_array($item->id, $addedIds)) {
                        return false;
                    }
                    $addedIds[] = $item->id;
                    return true;
                })
                ->map(function ($item) {
                    return [
                        'id'              => $item->id,
                        'nama_lengkap'    => $item->nama_lengkap,
                        'grade'           => $item->grade,
                        'is_active'       => $item->is_active,
                        'atasan_langsung' => $item->atasan_langsung,
                        'image'           => $item->image,
                    ];
                })
                ->values()
                ->toArray();

            $teams[] = $team;
        }

        return $teams;
    }

    private function buildTeamLookupMap(array $allMembers): array
    {
        $lookup = [];

        foreach ($allMembers as $member) {
            $lookup[$member['id']] = $member['team_index'];
        }

        foreach ($this->managerIds as $index => $managerId) {
            $lookup[$managerId] = $index;
        }

        return $lookup;
    }
}

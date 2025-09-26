<?php
namespace App\Services;

use App\Services\GetBawahan;
use App\Models\{
    QuotationKontrakH,
    QuotationNonKontrak,
    OrderHeader,
    Jadwal,
    SummaryQSD
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class SummaryQSDServices
{
    private $managerIds;
    private $year;

    public function __construct()
    {
        $this->managerIds = [19, 41, 600]; // Siti Nur Faidhah, Novva Novita Ayu Putri Rukmana, Akhbar Siliwangi Miharja
    }

    public function year($value)
    {
        $this->year = $value;
        return $this;
    }

    public function run()
    {

        try {
            Log::channel('summary_qsd')->info("[WorkerSummaryQSD] Running untuk tahun {$this->year}");

            $this->order($this->year);
            $this->forecast($this->year);
            $this->sampling($this->year);
            $this->sampelDiantar($this->year);
            $this->contract($this->year);
            $this->new($this->year);

            return response()->json([
                'status' => 'success',
                'message' => 'Fetch summary qsd completed successfully.',
            ], 200);
        } catch (\Throwable $th) {
            Log::channel('summary_qsd')->error('[WorkerSummaryQSD] Error : %s, Line : %s, File : %s', [$th->getMessage(), $th->getLine(), $th->getFile()]);
            throw $th;
        }
    }

    private function getTeams()
    {
        $teams = [];
        $addedIds = collect();

        foreach ($this->managerIds as $manager) {
            $team = GetBawahan::on('id', $manager)
                ->all()
                ->filter(function ($item) use ($addedIds) {
                    return !$addedIds->contains($item->id);
                })
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'nama_lengkap' => $item->nama_lengkap,
                        'grade' => $item->grade,
                        'is_active' => $item->is_active,
                        'atasan_langsung' => $item->atasan_langsung,
                        'image' => $item->image
                    ];
                })
                ->unique('id') // hilangkan duplikat dalam satu tim
                ->values();

            $addedIds = $addedIds->merge($team->pluck('id'));

            $teams[] = $team;
        }

        return $teams;
    }

    private function getAllTeamMembers()
    {
        $teams = $this->getTeams();
        $allMembers = [];

        foreach ($teams as $teamIndex => $team) {
            $teamMembers = $team->groupBy('grade');
            foreach ($teamMembers as $grade => $members) {
                foreach ($members as $member) {
                    $allMembers[] = [
                        'id' => $member['id'],
                        'team_index' => $teamIndex,
                        'grade' => $grade,
                        'data' => $member
                    ];
                }
            }
        }

        return $allMembers;
    }

    private function getOrderedDPP($allMemberIds, $tahun, $mode = null)
    {
        $year = $tahun;

        $kontrakQuotations = QuotationKontrakH::whereIn('sales_id', $allMemberIds)
            ->whereNotIn('pelanggan_ID', ['SAIR02', 'T2PE01'])
            ->where('is_active', true)
            ->select('no_document', 'sales_id')
            ->get();

        if ($mode == 'contract') {
            $allQuotations = $kontrakQuotations;
        } else {
            $nonKontrakQuotations = QuotationNonKontrak::whereIn('sales_id', $allMemberIds)
                ->whereNotIn('pelanggan_ID', ['SAIR02', 'T2PE01'])
                ->where('is_active', true)
                ->select('no_document', 'sales_id')
                ->get();

            $allQuotations = $kontrakQuotations->concat($nonKontrakQuotations);
        }

        $allDocuments = $allQuotations->pluck('no_document')->toArray();

        if (empty($allDocuments)) {
            return [];
        }

        if ($mode == 'new') {
            $firstOrderIds = OrderHeader::select('id_pelanggan')
                ->where('is_active', 1)
                ->groupBy('id_pelanggan')
                ->havingRaw('COUNT(*) = 1')
                ->pluck('id_pelanggan')
                ->toArray();
        }

        $orders = OrderHeader::with([
            'quotationKontrakH.detail' => function ($q) {
                $q->select('id_request_quotation_kontrak_h', 'periode_kontrak', 'total_dpp');
            },
            'orderDetail' => function ($q) use ($year, $mode) {
                $q->select('id_order_header', 'no_quotation', 'tanggal_sampling')
                    ->whereNotNull('tanggal_sampling')
                    ->whereNotNull('tanggal_terima')
                    ->whereYear('tanggal_sampling', $year);

                if ($mode == 'sd') {
                    $q->where('kategori_1', 'SD');
                } else if ($mode == 'sampling') {
                    $q->whereIn('kategori_1', ['S', 'S24']);
                }
            }
        ])
            ->select('id', 'no_document', 'total_dpp')
            ->whereIn('no_document', $allDocuments)
            ->whereHas('orderDetail', function ($q) use ($year, $mode) {
                $q->whereNotNull('tanggal_sampling')
                    ->whereNotNull('tanggal_terima')
                    ->whereYear('tanggal_sampling', $year);

                if ($mode == 'sd') {
                    $q->where('kategori_1', 'SD');
                } else if ($mode == 'sampling') {
                    $q->whereIn('kategori_1', ['S', 'S24']);
                }
            })
            ->where('is_active', 1);
        if ($mode == 'new') {
            $orders->whereIn('id_pelanggan', $firstOrderIds);
        }
        $orders = $orders->get();

        $quotationsBySales = $allQuotations->groupBy('sales_id');

        $result = [];
        foreach ($quotationsBySales as $salesId => $quotations) {
            $salesDocuments = $quotations->pluck('no_document')->toArray();
            $salesOrders = $orders->whereIn('no_document', $salesDocuments);

            $details = $this->initializeMonthlyDetails($tahun);

            foreach ($salesOrders as $order) {
                if (str_contains($order->no_document, '/QTC/')) {
                    $this->processKontrakOrder($order, $details);
                } elseif (str_contains($order->no_document, '/QT/')) {
                    $this->processNonKontrakOrder($order, $details);
                }
            }

            $result[$salesId] = array_map(fn($values) => array_sum($values), $details);
        }

        return $result;
    }

    private function processKontrakOrder($order, &$details)
    {
        if (empty($order->orderDetail))
            return;

        $periode = $order->orderDetail->map(function ($item) {
            return Carbon::parse($item->tanggal_sampling)->format('Y-m');
        })->unique()->values()->toArray();

        $penawaran = $order->quotationKontrakH->detail ?? collect();
        if ($penawaran->isEmpty())
            return;

        $periode_kontrak = $penawaran->pluck('periode_kontrak')->toArray();
        $periode_match = array_intersect($periode, $periode_kontrak);

        $matchedPenawaran = $penawaran->whereIn('periode_kontrak', $periode_match);

        foreach ($matchedPenawaran as $item) {
            $monthKey = Carbon::parse($item->periode_kontrak)->locale('id')->translatedFormat('M');
            $details[$monthKey][] = floor($item->total_dpp);
        }
    }

    private function processNonKontrakOrder($order, &$details)
    {
        if (empty($order->orderDetail) || $order->orderDetail->isEmpty())
            return;

        $monthKey = Carbon::parse($order->orderDetail[0]->tanggal_sampling)->locale('id')->translatedFormat('M');
        $details[$monthKey][] = floor($order->total_dpp);
    }

    private function initializeMonthlyDetails($tahun)
    {
        $details = [];
        $startDate = Carbon::parse($tahun)->startOfYear();

        for ($i = 0; $i < 12; $i++) {
            $monthKey = $startDate->copy()->addMonths($i)->locale('id')->translatedFormat('M');
            $details[$monthKey] = [0];
        }

        return $details;
    }

    private function getEmptyOrder($tahun)
    {
        $emptyOrder = [];
        $startDate = Carbon::parse($tahun)->startOfYear();

        for ($i = 0; $i < 12; $i++) {
            $monthKey = $startDate->copy()->addMonths($i)->locale('id')->translatedFormat('M');
            $emptyOrder[$monthKey] = 0;
        }

        return $emptyOrder;
    }

    private function getForecastDPP($allMemberIds, $tahun)
    {
        $year = $tahun;

        $kontrakQuotations = QuotationKontrakH::with([
            'detail' => function ($q) use ($year) {
                $q->select('id_request_quotation_kontrak_h', 'periode_kontrak', 'total_dpp')
                    ->where('periode_kontrak', 'like', $year . '%');
            }
        ])
            ->whereHas('detail', function ($q) use ($year) {
                $q->where('periode_kontrak', 'like', $year . '%');
            })
            ->where('flag_status', 'sp')
            ->whereIn('sales_id', $allMemberIds)
            ->whereNotIn('pelanggan_ID', ['SAIR02', 'T2PE01'])
            ->where(function ($query) {
                $query->whereNull('data_lama')
                    ->orWhereRaw("JSON_EXTRACT(data_lama, '$.no_order') IS NULL");
            })
            ->where('is_active', true)
            ->select('id', 'no_document', 'sales_id')
            ->get();

        $nonKontrakQuotations = QuotationNonKontrak::whereIn('sales_id', $allMemberIds)
            ->where('flag_status', 'sp')
            ->whereNotIn('pelanggan_ID', ['SAIR02', 'T2PE01'])
            ->where(function ($query) {
                $query->whereNull('data_lama')
                    ->orWhereRaw("JSON_EXTRACT(data_lama, '$.no_order') IS NULL");
            })
            ->where('is_active', true)
            ->select('no_document', 'sales_id')
            ->get();

        $allQuotations = $kontrakQuotations->concat($nonKontrakQuotations);
        $allDocuments = $allQuotations->pluck('no_document')->toArray();

        if (empty($allDocuments)) {
            return [];
        }

        $jadwals = Jadwal::with([
            'quotationKontrakH' => function ($query) use ($year) {
                $query->with([
                    'detail' => function ($q) use ($year) {
                        $q->select('id_request_quotation_kontrak_h', 'periode_kontrak', 'total_dpp')
                            ->where('periode_kontrak', 'like', $year . '%');
                    }
                ])
                    ->select('id', 'no_document', 'sales_id');
            },
            'quotationNonKontrak:no_document,total_dpp'
        ])
            ->selectRaw('no_quotation, GROUP_CONCAT(tanggal) as tanggal, GROUP_CONCAT(periode) as periode_kontrak')
            ->whereNull('parsial')
            ->where(function ($query) use ($year) {
                $query->where('periode', 'like', $year . '%')
                    ->orWhereYear('tanggal', $year);
            })
            ->whereIn('no_quotation', $allDocuments)
            ->where('is_active', 1)
            ->groupBy('no_quotation')
            ->get();
        $quotationsBySales = $allQuotations->groupBy('sales_id');
        $result = [];
        foreach ($quotationsBySales as $salesId => $quotations) {
            $salesDocuments = $quotations->pluck('no_document')->toArray();
            $salesJadwals = $jadwals->whereIn('no_quotation', $salesDocuments);

            $details = $this->initializeMonthlyDetails($tahun);

            foreach ($salesJadwals as $jadwal) {
                if (str_contains($jadwal->no_quotation, '/QTC/')) {
                    $this->processKontrakForecast($jadwal, $details);
                } elseif (str_contains($jadwal->no_quotation, '/QT/')) {
                    $this->processNonKontrakForecast($jadwal, $details);
                }
            }

            $result[$salesId] = array_map(fn($values) => array_sum($values), $details);
        }

        return $result;
    }

    private function processKontrakForecast($jadwal, &$details)
    {
        if (empty($jadwal))
            return;

        $periode = collect(explode(',', $jadwal->periode_kontrak))
            ->map(fn($item) => trim($item))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $penawaran = $jadwal->quotationKontrakH->detail ?? collect();
        if ($penawaran->isEmpty())
            return;

        $periode_kontrak = $penawaran->pluck('periode_kontrak')->toArray();
        $periode_match = array_intersect($periode, $periode_kontrak);

        $matchedPenawaran = $penawaran->whereIn('periode_kontrak', $periode_match);

        foreach ($matchedPenawaran as $item) {
            $monthKey = Carbon::parse($item->periode_kontrak)->locale('id')->translatedFormat('M');
            $details[$monthKey][] = floor($item->total_dpp);
        }
    }

    private function processNonKontrakForecast($jadwal, &$details)
    {
        if (!$jadwal->quotationNonKontrak || empty($jadwal->quotationNonKontrak))
            return;

        $tanggal = array_unique(explode(',', $jadwal->tanggal));

        $monthKey = Carbon::parse($tanggal[0])->locale('id')->translatedFormat('M');
        $details[$monthKey][] = floor($jadwal->quotationNonKontrak->total_dpp);
    }

    public function order($tahun)
    {
        $allMembers = $this->getAllTeamMembers();
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
            $bulkDPP = $this->getOrderedDPP($allMemberIds, $tahun);
            $emptyOrder = $this->getEmptyOrder($tahun);

            $teamsData = [];
            foreach ($allMembers as $member) {
                $teamIndex = $member['team_index'];
                $grade = strtolower($member['grade']);
                $memberId = $member['id'];

                if (!isset($teamsData[$teamIndex])) {
                    $teamsData[$teamIndex] = [
                        'staff' => [],
                        'supervisor' => [],
                        'manager' => []
                    ];
                }

                $memberData = $member['data'];
                $memberData['order'] = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$teamIndex][$grade][] = $memberData;
                }
            }

            $allteam_total_periode = $emptyOrder;
            foreach ($teamsData as $teamIndex => &$teamData) {
                $teamTotal = $emptyOrder;
                $teamTotalStaff = $emptyOrder;

                foreach (['staff', 'supervisor', 'manager'] as $grade) {
                    if (isset($teamData[$grade])) {
                        foreach ($teamData[$grade] as &$member) {
                            foreach ($member['order'] as $month => $amount) {
                                $teamTotal[$month] += $amount;

                                if ($grade === 'staff') {
                                    $teamTotalStaff[$month] += $amount;
                                }
                            }
                        }
                    }
                }

                $teamData['team_total_periode'] = $teamTotal;
                $teamData['team_total'] = array_sum($teamTotal);
                $teamData['team_total_staff_periode'] = $teamTotalStaff;
                $teamData['team_total_staff'] = array_sum($teamTotalStaff);

                foreach ($teamTotal as $month => $amount) {
                    $allteam_total_periode[$month] += $amount;
                }
            }

            // $forecast = $this->forecast($tahun)['all_total_periode'];
            // $order_with_forecast = collect($allteam_total_periode)
            //     ->mapWithKeys(fn($total, $bulan) => [
            //         $bulan => $total + ($forecast[$bulan] ?? 0)
            //     ])
            //     ->toArray();

            SummaryQSD::insert([
                'tahun' => $tahun,
                'type' => 'order',
                'data' => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
                // 'order_forecast' => array_sum($order_with_forecast),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data' => $teamsData,
                'all_total_periode' => $allteam_total_periode,
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => $order_with_forecast,
                // 'order_forecast' => array_sum($order_with_forecast)
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function orderAll($tahun)
    {
        $allMembers = $this->getAllTeamMembers();
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
            $bulkDPP = $this->getOrderedDPP($allMemberIds, $tahun);
            $emptyOrder = $this->getEmptyOrder($tahun);

            $teamsData = [
                'staff' => [],
                'supervisor' => [],
                'manager' => [],
            ];

            foreach ($allMembers as $member) {
                $grade = strtolower($member['grade']);
                $memberId = $member['id'];

                $memberData = $member['data'];
                $memberData['order'] = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$grade][] = $memberData;
                }
            }

            $teamTotal = $emptyOrder;
            $teamTotalStaff = $emptyOrder;
            $teamTotalUpper = $emptyOrder;
            foreach (['staff', 'supervisor', 'manager'] as $grade) {
                if (isset($teamsData[$grade])) {
                    foreach ($teamsData[$grade] as &$member) {
                        $member['total_order'] = array_sum($member['order']);

                        foreach ($member['order'] as $month => $amount) {
                            $teamTotal[$month] += $amount;

                            if ($grade === 'staff') {
                                $teamTotalStaff[$month] += $amount;
                            } else {
                                $teamTotalUpper[$month] += $amount;
                            }

                        }
                    }
                }
            }

            // $forecast = $this->forecast($tahun)['all_total_periode'];
            // $order_with_forecast = collect($teamTotal)
            //     ->mapWithKeys(fn($total, $bulan) => [
            //         $bulan => $total + ($forecast[$bulan] ?? 0)
            //     ])
            //     ->toArray();

            SummaryQSD::insert([
                'tahun' => $tahun,
                'type' => 'order_all',
                'data' => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'team_total_periode' => json_encode($teamTotal, JSON_UNESCAPED_UNICODE),
                'team_total' => array_sum($teamTotal),
                'team_total_staff_periode' => json_encode($teamTotalStaff, JSON_UNESCAPED_UNICODE),
                'team_total_staff' => array_sum($teamTotalStaff),
                'team_total_upper_periode' => json_encode($teamTotalUpper, JSON_UNESCAPED_UNICODE),
                'team_total_upper' => array_sum($teamTotalUpper),
                // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
                // 'order_forecast' => array_sum($order_with_forecast),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data' => $teamsData,
                'team_total_periode' => $teamTotal,
                'team_total' => array_sum($teamTotal),
                'team_total_staff_periode' => $teamTotalStaff,
                'team_total_staff' => array_sum($teamTotalStaff),
                'team_total_upper_periode' => $teamTotalUpper,
                'team_total_upper' => array_sum($teamTotalUpper),
                // 'order_forecast_periode' => $order_with_forecast,
                // 'order_forecast' => array_sum($order_with_forecast)
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function forecast($tahun)
    {
        $allMembers = $this->getAllTeamMembers();
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
            $bulkDPP = $this->getForecastDPP($allMemberIds, $tahun);
            $emptyOrder = $this->getEmptyOrder($tahun);

            $teamsData = [];
            foreach ($allMembers as $member) {
                $teamIndex = $member['team_index'];
                $grade = strtolower($member['grade']);
                $memberId = $member['id'];

                if (!isset($teamsData[$teamIndex])) {
                    $teamsData[$teamIndex] = [
                        'staff' => [],
                        'supervisor' => [],
                        'manager' => []
                    ];
                }

                $memberData = $member['data'];
                $memberData['order'] = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$teamIndex][$grade][] = $memberData;
                }
            }

            $allteam_total_periode = $emptyOrder;
            foreach ($teamsData as $teamIndex => &$teamData) {
                $teamTotal = $emptyOrder;
                $teamTotalStaff = $emptyOrder;

                foreach (['staff', 'supervisor', 'manager'] as $grade) {
                    if (isset($teamData[$grade])) {
                        foreach ($teamData[$grade] as &$member) {
                            foreach ($member['order'] as $month => $amount) {
                                $teamTotal[$month] += $amount;

                                if ($grade === 'staff') {
                                    $teamTotalStaff[$month] += $amount;
                                }
                            }
                        }
                    }
                }

                $teamData['team_total_periode'] = $teamTotal;
                $teamData['team_total'] = array_sum($teamTotal);
                $teamData['team_total_staff_periode'] = $teamTotalStaff;
                $teamData['team_total_staff'] = array_sum($teamTotalStaff);

                foreach ($teamTotal as $month => $amount) {
                    $allteam_total_periode[$month] += $amount;
                }
            }

            // $order = $this->order($tahun)['all_total_periode'];
            // $order_with_forecast = collect($allteam_total_periode)
            //     ->mapWithKeys(fn($total, $bulan) => [
            //         $bulan => $total + ($order[$bulan] ?? 0)
            //     ])
            //     ->toArray();

            SummaryQSD::insert([
                'tahun' => $tahun,
                'type' => 'forecast',
                'data' => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
                // 'order_forecast' => array_sum($order_with_forecast),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data' => $teamsData,
                'all_total_periode' => $allteam_total_periode,
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => $order_with_forecast,
                // 'order_forecast' => array_sum($order_with_forecast)
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function sampling($tahun)
    {
        $allMembers = $this->getAllTeamMembers();
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
            $bulkDPP = $this->getOrderedDPP($allMemberIds, $tahun, 'sampling');
            $emptyOrder = $this->getEmptyOrder($tahun);

            $teamsData = [];
            foreach ($allMembers as $member) {
                $teamIndex = $member['team_index'];
                $grade = strtolower($member['grade']);
                $memberId = $member['id'];

                if (!isset($teamsData[$teamIndex])) {
                    $teamsData[$teamIndex] = [
                        'staff' => [],
                        'supervisor' => [],
                        'manager' => []
                    ];
                }

                $memberData = $member['data'];
                $memberData['order'] = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$teamIndex][$grade][] = $memberData;
                }
            }

            $allteam_total_periode = $emptyOrder;
            foreach ($teamsData as $teamIndex => &$teamData) {
                $teamTotal = $emptyOrder;
                $teamTotalStaff = $emptyOrder;

                foreach (['staff', 'supervisor', 'manager'] as $grade) {
                    if (isset($teamData[$grade])) {
                        foreach ($teamData[$grade] as &$member) {
                            foreach ($member['order'] as $month => $amount) {
                                $teamTotal[$month] += $amount;

                                if ($grade === 'staff') {
                                    $teamTotalStaff[$month] += $amount;
                                }
                            }
                        }
                    }
                }

                $teamData['team_total_periode'] = $teamTotal;
                $teamData['team_total'] = array_sum($teamTotal);
                $teamData['team_total_staff_periode'] = $teamTotalStaff;
                $teamData['team_total_staff'] = array_sum($teamTotalStaff);

                foreach ($teamTotal as $month => $amount) {
                    $allteam_total_periode[$month] += $amount;
                }
            }

            // $order = $this->order($tahun)['all_total_periode'];
            // $forecast = $this->forecast($tahun)['all_total_periode'];
            // $order_with_forecast = collect($order)
            //     ->mapWithKeys(fn($total, $bulan) => [
            //         $bulan => $total + ($forecast[$bulan] ?? 0)
            //     ])
            //     ->toArray();

            SummaryQSD::insert([
                'tahun' => $tahun,
                'type' => 'sampling',
                'data' => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
                // 'order_forecast' => array_sum($order_with_forecast),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data' => $teamsData,
                'all_total_periode' => $allteam_total_periode,
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => $order_with_forecast,
                // 'order_forecast' => array_sum($order_with_forecast)
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function sampelDiantar($tahun)
    {
        $allMembers = $this->getAllTeamMembers();
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
            $bulkDPP = $this->getOrderedDPP($allMemberIds, $tahun, 'sd');
            $emptyOrder = $this->getEmptyOrder($tahun);

            $teamsData = [];
            foreach ($allMembers as $member) {
                $teamIndex = $member['team_index'];
                $grade = strtolower($member['grade']);
                $memberId = $member['id'];

                if (!isset($teamsData[$teamIndex])) {
                    $teamsData[$teamIndex] = [
                        'staff' => [],
                        'supervisor' => [],
                        'manager' => []
                    ];
                }

                $memberData = $member['data'];
                $memberData['order'] = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$teamIndex][$grade][] = $memberData;
                }
            }

            $allteam_total_periode = $emptyOrder;
            foreach ($teamsData as $teamIndex => &$teamData) {
                $teamTotal = $emptyOrder;
                $teamTotalStaff = $emptyOrder;

                foreach (['staff', 'supervisor', 'manager'] as $grade) {
                    if (isset($teamData[$grade])) {
                        foreach ($teamData[$grade] as &$member) {
                            foreach ($member['order'] as $month => $amount) {
                                $teamTotal[$month] += $amount;

                                if ($grade === 'staff') {
                                    $teamTotalStaff[$month] += $amount;
                                }
                            }
                        }
                    }
                }

                $teamData['team_total_periode'] = $teamTotal;
                $teamData['team_total'] = array_sum($teamTotal);
                $teamData['team_total_staff_periode'] = $teamTotalStaff;
                $teamData['team_total_staff'] = array_sum($teamTotalStaff);

                foreach ($teamTotal as $month => $amount) {
                    $allteam_total_periode[$month] += $amount;
                }
            }

            // $order = $this->order($tahun)['all_total_periode'];
            // $forecast = $this->forecast($tahun)['all_total_periode'];
            // $order_with_forecast = collect($order)
            //     ->mapWithKeys(fn($total, $bulan) => [
            //         $bulan => $total + ($forecast[$bulan] ?? 0)
            //     ])
            //     ->toArray();

            SummaryQSD::insert([
                'tahun' => $tahun,
                'type' => 'sampel_diantar',
                'data' => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
                // 'order_forecast' => array_sum($order_with_forecast),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data' => $teamsData,
                'all_total_periode' => $allteam_total_periode,
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => $order_with_forecast,
                // 'order_forecast' => array_sum($order_with_forecast)
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function contract($tahun)
    {
        $allMembers = $this->getAllTeamMembers();
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
            $bulkDPP = $this->getOrderedDPP($allMemberIds, $tahun, 'contract');
            $emptyOrder = $this->getEmptyOrder($tahun);

            $teamsData = [];
            foreach ($allMembers as $member) {
                $teamIndex = $member['team_index'];
                $grade = strtolower($member['grade']);
                $memberId = $member['id'];

                if (!isset($teamsData[$teamIndex])) {
                    $teamsData[$teamIndex] = [
                        'staff' => [],
                        'supervisor' => [],
                        'manager' => []
                    ];
                }

                $memberData = $member['data'];
                $memberData['order'] = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$teamIndex][$grade][] = $memberData;
                }
            }

            $allteam_total_periode = $emptyOrder;
            foreach ($teamsData as $teamIndex => &$teamData) {
                $teamTotal = $emptyOrder;
                $teamTotalStaff = $emptyOrder;

                foreach (['staff', 'supervisor', 'manager'] as $grade) {
                    if (isset($teamData[$grade])) {
                        foreach ($teamData[$grade] as &$member) {
                            foreach ($member['order'] as $month => $amount) {
                                $teamTotal[$month] += $amount;

                                if ($grade === 'staff') {
                                    $teamTotalStaff[$month] += $amount;
                                }
                            }
                        }
                    }
                }

                $teamData['team_total_periode'] = $teamTotal;
                $teamData['team_total'] = array_sum($teamTotal);
                $teamData['team_total_staff_periode'] = $teamTotalStaff;
                $teamData['team_total_staff'] = array_sum($teamTotalStaff);

                foreach ($teamTotal as $month => $amount) {
                    $allteam_total_periode[$month] += $amount;
                }
            }

            // $order = $this->order($tahun)['all_total_periode'];
            // $forecast = $this->forecast($tahun)['all_total_periode'];
            // $order_with_forecast = collect($order)
            //     ->mapWithKeys(fn($total, $bulan) => [
            //         $bulan => $total + ($forecast[$bulan] ?? 0)
            //     ])
            //     ->toArray();

            SummaryQSD::insert([
                'tahun' => $tahun,
                'type' => 'contract',
                'data' => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
                // 'order_forecast' => array_sum($order_with_forecast),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data' => $teamsData,
                'all_total_periode' => $allteam_total_periode,
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => $order_with_forecast,
                // 'order_forecast' => array_sum($order_with_forecast)
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function new($tahun)
    {
        $allMembers = $this->getAllTeamMembers();
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
            $bulkDPP = $this->getOrderedDPP($allMemberIds, $tahun, 'new');
            $emptyOrder = $this->getEmptyOrder($tahun);

            $teamsData = [];
            foreach ($allMembers as $member) {
                $teamIndex = $member['team_index'];
                $grade = strtolower($member['grade']);
                $memberId = $member['id'];

                if (!isset($teamsData[$teamIndex])) {
                    $teamsData[$teamIndex] = [
                        'staff' => [],
                        'supervisor' => [],
                        'manager' => []
                    ];
                }

                $memberData = $member['data'];
                $memberData['order'] = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$teamIndex][$grade][] = $memberData;
                }
            }

            $allteam_total_periode = $emptyOrder;
            foreach ($teamsData as $teamIndex => &$teamData) {
                $teamTotal = $emptyOrder;
                $teamTotalStaff = $emptyOrder;

                foreach (['staff', 'supervisor', 'manager'] as $grade) {
                    if (isset($teamData[$grade])) {
                        foreach ($teamData[$grade] as &$member) {
                            foreach ($member['order'] as $month => $amount) {
                                $teamTotal[$month] += $amount;

                                if ($grade === 'staff') {
                                    $teamTotalStaff[$month] += $amount;
                                }
                            }
                        }
                    }
                }

                $teamData['team_total_periode'] = $teamTotal;
                $teamData['team_total'] = array_sum($teamTotal);
                $teamData['team_total_staff_periode'] = $teamTotalStaff;
                $teamData['team_total_staff'] = array_sum($teamTotalStaff);

                foreach ($teamTotal as $month => $amount) {
                    $allteam_total_periode[$month] += $amount;
                }
            }

            // $order = $this->order($tahun)['all_total_periode'];
            // $forecast = $this->forecast($tahun)['all_total_periode'];
            // $order_with_forecast = collect($order)
            //     ->mapWithKeys(fn($total, $bulan) => [
            //         $bulan => $total + ($forecast[$bulan] ?? 0)
            //     ])
            //     ->toArray();

            SummaryQSD::insert([
                'tahun' => $tahun,
                'type' => 'new',
                'data' => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
                // 'order_forecast' => array_sum($order_with_forecast),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data' => $teamsData,
                'all_total_periode' => $allteam_total_periode,
                'all_total' => array_sum($allteam_total_periode),
                // 'order_forecast_periode' => $order_with_forecast,
                // 'order_forecast' => array_sum($order_with_forecast)
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
<?php
namespace App\Services;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SummaryQSD;use App\Services\GetBawahan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SummaryQSDServices
{
    private $managerIds;
    private $year;

    public function __construct()
    {
        $this->managerIds = [19, 41, 14]; // Siti Nur Faidhah, Novva Novita Ayu Putri Rukmana, Eka Yassica Simbolon

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

            $this->backupAndClearData($this->year);

            $this->order($this->year);
            $this->sampling($this->year);
            $this->sampelDiantar($this->year);
            $this->contract($this->year);
            $this->new($this->year);

            // $this->forecast($this->year);


            return response()->json([
                'status'  => 'success',
                'message' => 'Fetch summary qsd completed successfully.',
            ], 200);
        } catch (\Throwable $th) {
            Log::channel('summary_qsd')->error('[WorkerSummaryQSD] Error : %s, Line : %s, File : %s', [$th->getMessage(), $th->getLine(), $th->getFile()]);
            throw $th;
        }
    }

    private function backupAndClearData($tahun)
    {
        DB::beginTransaction();
        try {
            // Cek apakah ada data untuk tahun ini
            $existingData = SummaryQSD::where('tahun', $tahun)->get();

            if ($existingData->isNotEmpty()) {
                Log::channel('summary_qsd')->info("[WorkerSummaryQSD] Backup {$existingData->count()} records untuk tahun {$tahun}");

                // Insert ke backup table
                foreach ($existingData as $data) {
                    DB::table('summary_qsd_backup')->insert([
                        'tahun'                    => $data->tahun,
                        'type'                     => $data->type,
                        'data'                     => $data->data,
                        'all_total_periode'        => $data->all_total_periode,
                        'all_total'                => $data->all_total,
                        'team_total_periode'       => $data->team_total_periode ?? null,
                        'team_total'               => $data->team_total ?? null,
                        'team_total_staff_periode' => $data->team_total_staff_periode ?? null,
                        'team_total_staff'         => $data->team_total_staff ?? null,
                        'team_total_upper_periode' => $data->team_total_upper_periode ?? null,
                        'team_total_upper'         => $data->team_total_upper ?? null,
                        'created_at'               => $data->created_at,
                        'backup_at'                => Carbon::now(),
                    ]);
                }

                // Delete data tahun ini dari summary_qsd
                SummaryQSD::where('tahun', $tahun)->delete();

                Log::channel('summary_qsd')->info("[WorkerSummaryQSD] Data tahun {$tahun} berhasil di-backup dan di-clear");
            } else {
                Log::channel('summary_qsd')->info("[WorkerSummaryQSD] Tidak ada data untuk tahun {$tahun}, skip backup");
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('summary_qsd')->error('[WorkerSummaryQSD] Backup Error : %s', [$th->getMessage()]);
            throw $th;
        }
    }

    private function getTeams()
    {
        $teams    = [];
        $addedIds = collect();

        foreach ($this->managerIds as $manager) {
            $team = GetBawahan::on('id', $manager)
                ->all()
                ->filter(function ($item) use ($addedIds) {
                    return ! $addedIds->contains($item->id);
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
                ->unique('id')
                ->values();

            $addedIds = $addedIds->merge($team->pluck('id'));

            $teams[] = $team;
        }

        // dd($teams);

        return $teams;
    }

    private function getAllTeamMembers()
    {
        $teams      = $this->getTeams();
        $allMembers = [];

        foreach ($teams as $teamIndex => $team) {
            $teamMembers = $team->groupBy('grade');
            foreach ($teamMembers as $grade => $members) {
                foreach ($members as $member) {
                    $allMembers[] = [
                        'id'         => $member['id'],
                        'team_index' => $teamIndex,
                        'grade'      => $grade,
                        'data'       => $member,
                    ];
                }
            }
        }

        return $allMembers;
    }


    public function getRevenueFromDailyQSD($allMemberIds, $tahun, $type = 'order')
    {
        $year = $tahun;

        $query = DB::table('daily_qsd')
            ->select(
                'sales_id',
                DB::raw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m') as periode"),
                DB::raw('SUM(total_revenue) as total_revenue'))
        // ->whereIn('sales_id', $allMemberIds)
            ->whereNotIn('pelanggan_ID', ['SAIR02', 'T2PE01'])
            ->whereYear('tanggal_sampling_min', $year);

        switch ($type) {
            case 'order':
                $query->whereNotNull('no_order')
                    ->where('no_order', '!=', '');
                break;

            case 'contract':
                $query->where('kontrak', 'C')
                    ->whereNotNull('no_order');
                break;

            case 'sampling':
                $query->whereIn('status_sampling', ['S', 'S24'])
                    ->whereNotNull('no_order');
                break;

            case 'sd':
                $query->whereIn('status_sampling', ['SD', 'SP'])
                    ->whereNotNull('no_order');
                break;

            case 'new':
                $firstOrderCustomers = DB::table('daily_qsd')
                    ->select('pelanggan_ID')
                    ->whereNotNull('no_order')
                    ->groupBy('pelanggan_ID')
                    ->havingRaw('COUNT(DISTINCT no_order) = 1')
                    ->pluck('pelanggan_ID')
                    ->toArray();

                $query->whereIn('pelanggan_ID', $firstOrderCustomers)
                    ->whereNotNull('no_order');
                break;
        }

        $query->groupBy('sales_id', DB::raw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m')"));

        $data = $query->get();

        // dd($data);

        $dataBySales = $data->groupBy('sales_id');
        $result      = [];

        foreach ($dataBySales as $salesId => $records) {
            $month = $this->initializeMonthlyDetails($tahun);

            // dd($records);

            // dd($month);

            foreach ($records as $record) {

                $monthKey           = Carbon::parse($record->periode)->locale('id')->translatedFormat('M');
                $month[$monthKey][] = $record->total_revenue;

            }
            // dd($records);

            $result[$salesId] = array_map(fn($values) => array_sum($values), $month);

        }
        // dd($result);
        return $result;
    }

    private function processNonKontrakOrder($order, &$details)
    {
        if (empty($order->orderDetail) || $order->orderDetail->isEmpty()) {
            return;
        }

        $monthKey             = Carbon::parse($order->orderDetail[0]->tanggal_sampling)->locale('id')->translatedFormat('M');
        $details[$monthKey][] = floor($order->total_dpp);
    }

    private function initializeMonthlyDetails($tahun)
    {
        $details   = [];
        $startDate = Carbon::parse($tahun)->startOfYear();

        for ($i = 0; $i < 12; $i++) {
            $monthKey           = $startDate->copy()->addMonths($i)->locale('id')->translatedFormat('M');
            $details[$monthKey] = [0];
        }

        return $details;
    }

    private function getEmptyOrder($tahun)
    {
        $emptyOrder = [];
        $startDate  = Carbon::parse($tahun)->startOfYear();

        for ($i = 0; $i < 12; $i++) {
            $monthKey              = $startDate->copy()->addMonths($i)->locale('id')->translatedFormat('M');
            $emptyOrder[$monthKey] = 0;
        }

        return $emptyOrder;
    }


    public function order($tahun)
    {
        $allMembers   = $this->getAllTeamMembers(); // Active members only
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
                                                                                 
            $bulkDPP = $this->getRevenueFromDailyQSD([], $tahun, 'order');

            // Cari user yang ada di daily_qsd tapi tidak di active members
            $resignedMemberIds = array_diff(array_keys($bulkDPP), $allMemberIds);

            // Ambil info user resign dan tentukan tim mereka
            if (! empty($resignedMemberIds)) {
                $resignedMembers = $this->getResignedMembersWithTeam($resignedMemberIds, $tahun);
                $allMembers      = array_merge($allMembers, $resignedMembers);
            }

            $emptyOrder = $this->getEmptyOrder($tahun);
            $teamsData  = [];

            foreach ($allMembers as $member) {
                $teamIndex  = $member['team_index'];
                $grade      = strtolower($member['grade']);
                $memberId   = $member['id'];
                $isResigned = $member['is_resigned'] ?? false;

                if (! isset($teamsData[$teamIndex])) {
                    $teamsData[$teamIndex] = [
                        'staff'      => [],
                        'supervisor' => [],
                        'manager'    => [],
                    ];
                }

                $memberData                = $member['data'];
                $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);
                $memberData['is_resigned'] = $isResigned;

                // Tambahkan penanda (Resign) di nama
                if ($isResigned) {
                    $memberData['nama_lengkap'] = $memberData['nama_lengkap'];
                }

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$teamIndex][$grade][] = $memberData;
                }
            }

            $allteam_total_periode = $emptyOrder;
            foreach ($teamsData as $teamIndex => &$teamData) {
                $teamTotal      = $emptyOrder;
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

                $teamData['team_total_periode']       = $teamTotal;
                $teamData['team_total']               = array_sum($teamTotal);
                $teamData['team_total_staff_periode'] = $teamTotalStaff;
                $teamData['team_total_staff']         = array_sum($teamTotalStaff);

                foreach ($teamTotal as $month => $amount) {
                    $allteam_total_periode[$month] += $amount;
                }
            }

            SummaryQSD::insert([
                'tahun'             => $tahun,
                'type'              => 'order',
                'data'              => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
                'all_total'         => array_sum($allteam_total_periode),
                'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data'              => $teamsData,
                'all_total_periode' => $allteam_total_periode,
                'all_total'         => array_sum($allteam_total_periode),
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function sampling($tahun)
    {
        $allMembers   = $this->getAllTeamMembers(); // Active members only
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
                                                                                 
            $bulkDPP = $this->getRevenueFromDailyQSD([], $tahun, 'sampling');

            // Cari user yang ada di daily_qsd tapi tidak di active members
            $resignedMemberIds = array_diff(array_keys($bulkDPP), $allMemberIds);

            // Ambil info user resign dan tentukan tim mereka
            if (! empty($resignedMemberIds)) {
                $resignedMembers = $this->getResignedMembersWithTeam($resignedMemberIds, $tahun);
                $allMembers      = array_merge($allMembers, $resignedMembers);
            }

            $emptyOrder = $this->getEmptyOrder($tahun);
            $teamsData  = [];

            foreach ($allMembers as $member) {
                $teamIndex  = $member['team_index'];
                $grade      = strtolower($member['grade']);
                $memberId   = $member['id'];
                $isResigned = $member['is_resigned'] ?? false;

                if (! isset($teamsData[$teamIndex])) {
                    $teamsData[$teamIndex] = [
                        'staff'      => [],
                        'supervisor' => [],
                        'manager'    => [],
                    ];
                }

                $memberData                = $member['data'];
                $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);
                $memberData['is_resigned'] = $isResigned;

                // Tambahkan penanda (Resign) di nama
                if ($isResigned) {
                    $memberData['nama_lengkap'] = $memberData['nama_lengkap'];
                }

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$teamIndex][$grade][] = $memberData;
                }
            }

            $allteam_total_periode = $emptyOrder;
            foreach ($teamsData as $teamIndex => &$teamData) {
                $teamTotal      = $emptyOrder;
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

                $teamData['team_total_periode']       = $teamTotal;
                $teamData['team_total']               = array_sum($teamTotal);
                $teamData['team_total_staff_periode'] = $teamTotalStaff;
                $teamData['team_total_staff']         = array_sum($teamTotalStaff);

                foreach ($teamTotal as $month => $amount) {
                    $allteam_total_periode[$month] += $amount;
                }
            }

            SummaryQSD::insert([
                'tahun'             => $tahun,
                'type'              => 'sampling',
                'data'              => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
                'all_total'         => array_sum($allteam_total_periode),
                'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data'              => $teamsData,
                'all_total_periode' => $allteam_total_periode,
                'all_total'         => array_sum($allteam_total_periode),
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function sampelDiantar($tahun)
    {
        $allMembers   = $this->getAllTeamMembers(); // Active members only
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
                                                                                 
            $bulkDPP = $this->getRevenueFromDailyQSD([], $tahun, 'sd');

            // Cari user yang ada di daily_qsd tapi tidak di active members
            $resignedMemberIds = array_diff(array_keys($bulkDPP), $allMemberIds);

            // Ambil info user resign dan tentukan tim mereka
            if (! empty($resignedMemberIds)) {
                $resignedMembers = $this->getResignedMembersWithTeam($resignedMemberIds, $tahun);
                $allMembers      = array_merge($allMembers, $resignedMembers);
            }

            $emptyOrder = $this->getEmptyOrder($tahun);
            $teamsData  = [];

            foreach ($allMembers as $member) {
                $teamIndex  = $member['team_index'];
                $grade      = strtolower($member['grade']);
                $memberId   = $member['id'];
                $isResigned = $member['is_resigned'] ?? false;

                if (! isset($teamsData[$teamIndex])) {
                    $teamsData[$teamIndex] = [
                        'staff'      => [],
                        'supervisor' => [],
                        'manager'    => [],
                    ];
                }

                $memberData                = $member['data'];
                $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);
                $memberData['is_resigned'] = $isResigned;

                // Tambahkan penanda (Resign) di nama
                if ($isResigned) {
                    $memberData['nama_lengkap'] = $memberData['nama_lengkap'];
                }

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$teamIndex][$grade][] = $memberData;
                }
            }

            $allteam_total_periode = $emptyOrder;
            foreach ($teamsData as $teamIndex => &$teamData) {
                $teamTotal      = $emptyOrder;
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

                $teamData['team_total_periode']       = $teamTotal;
                $teamData['team_total']               = array_sum($teamTotal);
                $teamData['team_total_staff_periode'] = $teamTotalStaff;
                $teamData['team_total_staff']         = array_sum($teamTotalStaff);

                foreach ($teamTotal as $month => $amount) {
                    $allteam_total_periode[$month] += $amount;
                }
            }

            SummaryQSD::insert([
                'tahun'             => $tahun,
                'type'              => 'sampel_diantar',
                'data'              => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
                'all_total'         => array_sum($allteam_total_periode),
                'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data'              => $teamsData,
                'all_total_periode' => $allteam_total_periode,
                'all_total'         => array_sum($allteam_total_periode),
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function contract($tahun)
    {
        $allMembers   = $this->getAllTeamMembers(); // Active members only
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
                                                                                 
            $bulkDPP = $this->getRevenueFromDailyQSD([], $tahun, 'contract');

            // Cari user yang ada di daily_qsd tapi tidak di active members
            $resignedMemberIds = array_diff(array_keys($bulkDPP), $allMemberIds);

            // Ambil info user resign dan tentukan tim mereka
            if (! empty($resignedMemberIds)) {
                $resignedMembers = $this->getResignedMembersWithTeam($resignedMemberIds, $tahun);
                $allMembers      = array_merge($allMembers, $resignedMembers);
            }

            $emptyOrder = $this->getEmptyOrder($tahun);
            $teamsData  = [];

            foreach ($allMembers as $member) {
                $teamIndex  = $member['team_index'];
                $grade      = strtolower($member['grade']);
                $memberId   = $member['id'];
                $isResigned = $member['is_resigned'] ?? false;

                if (! isset($teamsData[$teamIndex])) {
                    $teamsData[$teamIndex] = [
                        'staff'      => [],
                        'supervisor' => [],
                        'manager'    => [],
                    ];
                }

                $memberData                = $member['data'];
                $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);
                $memberData['is_resigned'] = $isResigned;

                // Tambahkan penanda (Resign) di nama
                if ($isResigned) {
                    $memberData['nama_lengkap'] = $memberData['nama_lengkap'];
                }

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$teamIndex][$grade][] = $memberData;
                }
            }

            $allteam_total_periode = $emptyOrder;
            foreach ($teamsData as $teamIndex => &$teamData) {
                $teamTotal      = $emptyOrder;
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

                $teamData['team_total_periode']       = $teamTotal;
                $teamData['team_total']               = array_sum($teamTotal);
                $teamData['team_total_staff_periode'] = $teamTotalStaff;
                $teamData['team_total_staff']         = array_sum($teamTotalStaff);

                foreach ($teamTotal as $month => $amount) {
                    $allteam_total_periode[$month] += $amount;
                }
            }

            SummaryQSD::insert([
                'tahun'             => $tahun,
                'type'              => 'contract',
                'data'              => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
                'all_total'         => array_sum($allteam_total_periode),
                'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data'              => $teamsData,
                'all_total_periode' => $allteam_total_periode,
                'all_total'         => array_sum($allteam_total_periode),
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function new($tahun)
    {
        $allMembers   = $this->getAllTeamMembers(); // Active members only
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        DB::beginTransaction();
        try {
                                                                                 
            $bulkDPP = $this->getRevenueFromDailyQSD([], $tahun, 'new');

            // Cari user yang ada di daily_qsd tapi tidak di active members
            $resignedMemberIds = array_diff(array_keys($bulkDPP), $allMemberIds);

            // Ambil info user resign dan tentukan tim mereka
            if (! empty($resignedMemberIds)) {
                $resignedMembers = $this->getResignedMembersWithTeam($resignedMemberIds, $tahun);
                $allMembers      = array_merge($allMembers, $resignedMembers);
            }

            $emptyOrder = $this->getEmptyOrder($tahun);
            $teamsData  = [];

            foreach ($allMembers as $member) {
                $teamIndex  = $member['team_index'];
                $grade      = strtolower($member['grade']);
                $memberId   = $member['id'];
                $isResigned = $member['is_resigned'] ?? false;

                if (! isset($teamsData[$teamIndex])) {
                    $teamsData[$teamIndex] = [
                        'staff'      => [],
                        'supervisor' => [],
                        'manager'    => [],
                    ];
                }

                $memberData                = $member['data'];
                $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
                $memberData['total_order'] = array_sum($memberData['order']);
                $memberData['is_resigned'] = $isResigned;

                // Tambahkan penanda (Resign) di nama
                if ($isResigned) {
                    $memberData['nama_lengkap'] = $memberData['nama_lengkap'];
                }

                if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                    $teamsData[$teamIndex][$grade][] = $memberData;
                }
            }

            $allteam_total_periode = $emptyOrder;
            foreach ($teamsData as $teamIndex => &$teamData) {
                $teamTotal      = $emptyOrder;
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

                $teamData['team_total_periode']       = $teamTotal;
                $teamData['team_total']               = array_sum($teamTotal);
                $teamData['team_total_staff_periode'] = $teamTotalStaff;
                $teamData['team_total_staff']         = array_sum($teamTotalStaff);

                foreach ($teamTotal as $month => $amount) {
                    $allteam_total_periode[$month] += $amount;
                }
            }

            SummaryQSD::insert([
                'tahun'             => $tahun,
                'type'              => 'new',
                'data'              => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
                'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
                'all_total'         => array_sum($allteam_total_periode),
                'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return [
                'data'              => $teamsData,
                'all_total_periode' => $allteam_total_periode,
                'all_total'         => array_sum($allteam_total_periode),
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    private function getResignedMembersWithTeam($resignedMemberIds, $tahun)
    {
        // Ambil data user resign
        $resignedUsers = DB::table('master_karyawan')
            ->whereIn('id', $resignedMemberIds)
            ->get()
            ->keyBy('id');

        $resignedMembers = [];

        foreach ($resignedMemberIds as $userId) {
            $user = $resignedUsers->get($userId);

            if (! $user) {
                continue;
            }

            // Cari atasan langsung dari user
            $atasanId = $user->atasan_langsung;

            // Tentukan team_index berdasarkan atasan
            $teamIndex = $this->findTeamIndexByAtasan($atasanId);

            // Default ke team 0 jika tetap tidak ketemu
            if ($teamIndex === null) {
                $teamIndex = 0;
            }

            $resignedMembers[] = [
                'id'          => $user->id,
                'team_index'  => $teamIndex,
                'grade'       => strtolower($user->grade ?? 'staff'),
                'is_resigned' => true,
                'data'        => [
                    'id'              => $user->id,
                    'nama_lengkap'    => $user->nama_lengkap,
                    'grade'           => $user->grade,
                    'is_active'       => 0,
                    'atasan_langsung' => $user->atasan_langsung,
                    'image'           => $user->image ?? null,
                ],
            ];
        }

        return $resignedMembers;
    }

/**
 * Cari team index berdasarkan atasan langsung
 */
    private function findTeamIndexByAtasan($atasanId)
    {
        if (! $atasanId) {
            return null;
        }

        $teams = $this->getTeams();

        foreach ($teams as $teamIndex => $team) {
            foreach ($team as $member) {
                if ($member['id'] == $atasanId) {
                    return $teamIndex;
                }
            }
        }

        foreach ($this->managerIds as $index => $managerId) {
            if ($managerId == $atasanId) {
                return $index;
            }
        }

        return null;
    }


     // private function getForecastDPP($allMemberIds, $tahun)
    // {
    //     $year = $tahun;

    //     // Ambil data forecast (quotation yang belum jadi order)
    //     // Asumsi: jika no_order kosong atau belum ada di daily_qsd, maka masih forecast

    //     // Opsi 1: Jika ada field flag_status di daily_qsd
    //     $forecastData = DB::table('daily_qsd')
    //         ->whereIn('sales_id', $allMemberIds)
    //         ->whereNotIn('pelanggan_ID', ['SAIR02', 'T2PE01'])
    //         ->where(function ($q) {
    //             $q->whereNull('no_order')
    //                 ->orWhere('no_order', '');
    //         })
    //         ->whereYear('tanggal_sampling_min', $year)
    //         ->get();

    //     // Opsi 2: Atau ambil dari quotation yang belum ada di daily_qsd
    //     // Sesuaikan dengan business logic Anda

    //     if ($forecastData->isEmpty()) {
    //         return [];
    //     }

    //     $dataBySales = $forecastData->groupBy('sales_id');

    //     $result = [];
    //     foreach ($dataBySales as $salesId => $records) {
    //         $details = $this->initializeMonthlyDetails($tahun);

    //         foreach ($records as $record) {
    //             $this->processDailyQsdRecord($record, $details);
    //         }

    //         $result[$salesId] = array_map(fn($values) => array_sum($values), $details);
    //     }

    //     return $result;
    // }

    // private function processKontrakForecast($jadwal, &$details)
    // {
    //     if (empty($jadwal)) {
    //         return;
    //     }

    //     $periode = collect(explode(',', $jadwal->periode_kontrak))
    //         ->map(fn($item) => trim($item))
    //         ->filter()
    //         ->unique()
    //         ->sort()
    //         ->values()
    //         ->toArray();

    //     $penawaran = $jadwal->quotationKontrakH->detail ?? collect();
    //     if ($penawaran->isEmpty()) {
    //         return;
    //     }

    //     $periode_kontrak = $penawaran->pluck('periode_kontrak')->toArray();
    //     $periode_match   = array_intersect($periode, $periode_kontrak);

    //     $matchedPenawaran = $penawaran->whereIn('periode_kontrak', $periode_match);

    //     foreach ($matchedPenawaran as $item) {
    //         $monthKey             = Carbon::parse($item->periode_kontrak)->locale('id')->translatedFormat('M');
    //         $details[$monthKey][] = floor($item->total_dpp);
    //     }
    // }

    // private function processNonKontrakForecast($jadwal, &$details)
    // {
    //     if (! $jadwal->quotationNonKontrak || empty($jadwal->quotationNonKontrak)) {
    //         return;
    //     }

    //     $tanggal = array_unique(explode(',', $jadwal->tanggal));

    //     $monthKey             = Carbon::parse($tanggal[0])->locale('id')->translatedFormat('M');
    //     $details[$monthKey][] = floor($jadwal->quotationNonKontrak->total_dpp);
    // }

    // public function order($tahun)
    // {
    //     $allMembers   = $this->getAllTeamMembers();
    //     $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

    //     DB::beginTransaction();
    //     try {
    //         $bulkDPP    = $this->getRevenueFromDailyQSD($allMemberIds, $tahun, 'order');
    //         $emptyOrder = $this->getEmptyOrder($tahun);
    //         // dd($bulkDPP);
    //         $teamsData = [];
    //         // dd($allMembers);
    //         foreach ($allMembers as $member) {
    //             $teamIndex = $member['team_index'];
    //             $grade     = strtolower($member['grade']);
    //             $memberId  = $member['id'];

    //             if (! isset($teamsData[$teamIndex])) {
    //                 $teamsData[$teamIndex] = [
    //                     'staff'      => [],
    //                     'supervisor' => [],
    //                     'manager'    => [],
    //                 ];
    //             }

    //             $memberData                = $member['data'];
    //             $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
    //             $memberData['total_order'] = array_sum($memberData['order']);

    //             if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
    //                 $teamsData[$teamIndex][$grade][] = $memberData;
    //             }
    //         }

    //         // dd($teamsData);

    //         $allteam_total_periode = $emptyOrder;
    //         foreach ($teamsData as $teamIndex => &$teamData) {
    //             $teamTotal      = $emptyOrder;
    //             $teamTotalStaff = $emptyOrder;

    //             foreach (['staff', 'supervisor', 'manager'] as $grade) {
    //                 if (isset($teamData[$grade])) {
    //                     foreach ($teamData[$grade] as &$member) {
    //                         foreach ($member['order'] as $month => $amount) {
    //                             $teamTotal[$month] += $amount;

    //                             if ($grade === 'staff') {
    //                                 $teamTotalStaff[$month] += $amount;
    //                             }
    //                         }
    //                     }
    //                 }
    //             }

    //             $teamData['team_total_periode']       = $teamTotal;
    //             $teamData['team_total']               = array_sum($teamTotal);
    //             $teamData['team_total_staff_periode'] = $teamTotalStaff;
    //             $teamData['team_total_staff']         = array_sum($teamTotalStaff);

    //             foreach ($teamTotal as $month => $amount) {
    //                 $allteam_total_periode[$month] += $amount;
    //             }
    //         }

    //         // $forecast = $this->forecast($tahun)['all_total_periode'];
    //         // $order_with_forecast = collect($allteam_total_periode)
    //         //     ->mapWithKeys(fn($total, $bulan) => [
    //         //         $bulan => $total + ($forecast[$bulan] ?? 0)
    //         //     ])
    //         //     ->toArray();

    //         // dd($allteam_total_periode);

    //         dd($teamsData, $allteam_total_periode, $allteam_total_periode);

    //         SummaryQSD::insert([
    //             'tahun'             => $tahun,
    //             'type'              => 'order',
    //             'data'              => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
    //             'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
    //             // 'order_forecast' => array_sum($order_with_forecast),
    //             'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
    //         ]);

    //         DB::commit();
    //         return [
    //             'data'              => $teamsData,
    //             'all_total_periode' => $allteam_total_periode,
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => $order_with_forecast,
    //             // 'order_forecast' => array_sum($order_with_forecast)
    //         ];
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         throw $th;
    //     }
    // }

    // public function orderAll($tahun)
    // {
    //     $allMembers   = $this->getAllTeamMembers();
    //     $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

    //     DB::beginTransaction();
    //     try {
    //         $bulkDPP    = $this->getOrderedDPP($allMemberIds, $tahun);
    //         $emptyOrder = $this->getEmptyOrder($tahun);

    //         $teamsData = [
    //             'staff'      => [],
    //             'supervisor' => [],
    //             'manager'    => [],
    //         ];

    //         foreach ($allMembers as $member) {
    //             $grade    = strtolower($member['grade']);
    //             $memberId = $member['id'];

    //             $memberData                = $member['data'];
    //             $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
    //             $memberData['total_order'] = array_sum($memberData['order']);

    //             if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
    //                 $teamsData[$grade][] = $memberData;
    //             }
    //         }

    //         $teamTotal      = $emptyOrder;
    //         $teamTotalStaff = $emptyOrder;
    //         $teamTotalUpper = $emptyOrder;
    //         foreach (['staff', 'supervisor', 'manager'] as $grade) {
    //             if (isset($teamsData[$grade])) {
    //                 foreach ($teamsData[$grade] as &$member) {
    //                     $member['total_order'] = array_sum($member['order']);

    //                     foreach ($member['order'] as $month => $amount) {
    //                         $teamTotal[$month] += $amount;

    //                         if ($grade === 'staff') {
    //                             $teamTotalStaff[$month] += $amount;
    //                         } else {
    //                             $teamTotalUpper[$month] += $amount;
    //                         }

    //                     }
    //                 }
    //             }
    //         }

    //         // $forecast = $this->forecast($tahun)['all_total_periode'];
    //         // $order_with_forecast = collect($teamTotal)
    //         //     ->mapWithKeys(fn($total, $bulan) => [
    //         //         $bulan => $total + ($forecast[$bulan] ?? 0)
    //         //     ])
    //         //     ->toArray();

    //         SummaryQSD::insert([
    //             'tahun'                    => $tahun,
    //             'type'                     => 'order_all',
    //             'data'                     => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
    //             'team_total_periode'       => json_encode($teamTotal, JSON_UNESCAPED_UNICODE),
    //             'team_total'               => array_sum($teamTotal),
    //             'team_total_staff_periode' => json_encode($teamTotalStaff, JSON_UNESCAPED_UNICODE),
    //             'team_total_staff'         => array_sum($teamTotalStaff),
    //             'team_total_upper_periode' => json_encode($teamTotalUpper, JSON_UNESCAPED_UNICODE),
    //             'team_total_upper'         => array_sum($teamTotalUpper),
    //             // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
    //             // 'order_forecast' => array_sum($order_with_forecast),
    //             'created_at'               => Carbon::now()->format('Y-m-d H:i:s'),
    //         ]);

    //         DB::commit();
    //         return [
    //             'data'                     => $teamsData,
    //             'team_total_periode'       => $teamTotal,
    //             'team_total'               => array_sum($teamTotal),
    //             'team_total_staff_periode' => $teamTotalStaff,
    //             'team_total_staff'         => array_sum($teamTotalStaff),
    //             'team_total_upper_periode' => $teamTotalUpper,
    //             'team_total_upper'         => array_sum($teamTotalUpper),
    //             // 'order_forecast_periode' => $order_with_forecast,
    //             // 'order_forecast' => array_sum($order_with_forecast)
    //         ];
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         throw $th;
    //     }
    // }

    // public function forecast($tahun)
    // {
    //     $allMembers   = $this->getAllTeamMembers();
    //     $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

    //     DB::beginTransaction();
    //     try {
    //         $bulkDPP    = $this->getDPPFromDailyQSD($allMemberIds, $tahun, 'forecast');
    //         $emptyOrder = $this->getEmptyOrder($tahun);

    //         $teamsData = [];
    //         foreach ($allMembers as $member) {
    //             $teamIndex = $member['team_index'];
    //             $grade     = strtolower($member['grade']);
    //             $memberId  = $member['id'];

    //             if (! isset($teamsData[$teamIndex])) {
    //                 $teamsData[$teamIndex] = [
    //                     'staff'      => [],
    //                     'supervisor' => [],
    //                     'manager'    => [],
    //                 ];
    //             }

    //             $memberData                = $member['data'];
    //             $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
    //             $memberData['total_order'] = array_sum($memberData['order']);

    //             if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
    //                 $teamsData[$teamIndex][$grade][] = $memberData;
    //             }
    //         }

    //         $allteam_total_periode = $emptyOrder;
    //         foreach ($teamsData as $teamIndex => &$teamData) {
    //             $teamTotal      = $emptyOrder;
    //             $teamTotalStaff = $emptyOrder;

    //             foreach (['staff', 'supervisor', 'manager'] as $grade) {
    //                 if (isset($teamData[$grade])) {
    //                     foreach ($teamData[$grade] as &$member) {
    //                         foreach ($member['order'] as $month => $amount) {
    //                             $teamTotal[$month] += $amount;

    //                             if ($grade === 'staff') {
    //                                 $teamTotalStaff[$month] += $amount;
    //                             }
    //                         }
    //                     }
    //                 }
    //             }

    //             $teamData['team_total_periode']       = $teamTotal;
    //             $teamData['team_total']               = array_sum($teamTotal);
    //             $teamData['team_total_staff_periode'] = $teamTotalStaff;
    //             $teamData['team_total_staff']         = array_sum($teamTotalStaff);

    //             foreach ($teamTotal as $month => $amount) {
    //                 $allteam_total_periode[$month] += $amount;
    //             }
    //         }

    //         // $order = $this->order($tahun)['all_total_periode'];
    //         // $order_with_forecast = collect($allteam_total_periode)
    //         //     ->mapWithKeys(fn($total, $bulan) => [
    //         //         $bulan => $total + ($order[$bulan] ?? 0)
    //         //     ])
    //         //     ->toArray();

    //         SummaryQSD::insert([
    //             'tahun'             => $tahun,
    //             'type'              => 'forecast',
    //             'data'              => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
    //             'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
    //             // 'order_forecast' => array_sum($order_with_forecast),
    //             'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
    //         ]);

    //         DB::commit();
    //         return [
    //             'data'              => $teamsData,
    //             'all_total_periode' => $allteam_total_periode,
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => $order_with_forecast,
    //             // 'order_forecast' => array_sum($order_with_forecast)
    //         ];
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         throw $th;
    //     }
    // }

    // public function sampling($tahun)
    // {
    //     $allMembers   = $this->getAllTeamMembers();
    //     $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

    //     DB::beginTransaction();
    //     try {
    //         $bulkDPP    = $this->getDPPFromDailyQSD($allMemberIds, $tahun, 'sampling');
    //         $emptyOrder = $this->getEmptyOrder($tahun);

    //         $teamsData = [];
    //         foreach ($allMembers as $member) {
    //             $teamIndex = $member['team_index'];
    //             $grade     = strtolower($member['grade']);
    //             $memberId  = $member['id'];

    //             if (! isset($teamsData[$teamIndex])) {
    //                 $teamsData[$teamIndex] = [
    //                     'staff'      => [],
    //                     'supervisor' => [],
    //                     'manager'    => [],
    //                 ];
    //             }

    //             $memberData                = $member['data'];
    //             $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
    //             $memberData['total_order'] = array_sum($memberData['order']);

    //             if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
    //                 $teamsData[$teamIndex][$grade][] = $memberData;
    //             }
    //         }

    //         $allteam_total_periode = $emptyOrder;
    //         foreach ($teamsData as $teamIndex => &$teamData) {
    //             $teamTotal      = $emptyOrder;
    //             $teamTotalStaff = $emptyOrder;

    //             foreach (['staff', 'supervisor', 'manager'] as $grade) {
    //                 if (isset($teamData[$grade])) {
    //                     foreach ($teamData[$grade] as &$member) {
    //                         foreach ($member['order'] as $month => $amount) {
    //                             $teamTotal[$month] += $amount;

    //                             if ($grade === 'staff') {
    //                                 $teamTotalStaff[$month] += $amount;
    //                             }
    //                         }
    //                     }
    //                 }
    //             }

    //             $teamData['team_total_periode']       = $teamTotal;
    //             $teamData['team_total']               = array_sum($teamTotal);
    //             $teamData['team_total_staff_periode'] = $teamTotalStaff;
    //             $teamData['team_total_staff']         = array_sum($teamTotalStaff);

    //             foreach ($teamTotal as $month => $amount) {
    //                 $allteam_total_periode[$month] += $amount;
    //             }
    //         }

    //         // $order = $this->order($tahun)['all_total_periode'];
    //         // $forecast = $this->forecast($tahun)['all_total_periode'];
    //         // $order_with_forecast = collect($order)
    //         //     ->mapWithKeys(fn($total, $bulan) => [
    //         //         $bulan => $total + ($forecast[$bulan] ?? 0)
    //         //     ])
    //         //     ->toArray();

    //         SummaryQSD::insert([
    //             'tahun'             => $tahun,
    //             'type'              => 'sampling',
    //             'data'              => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
    //             'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
    //             // 'order_forecast' => array_sum($order_with_forecast),
    //             'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
    //         ]);

    //         DB::commit();
    //         return [
    //             'data'              => $teamsData,
    //             'all_total_periode' => $allteam_total_periode,
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => $order_with_forecast,
    //             // 'order_forecast' => array_sum($order_with_forecast)
    //         ];
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         throw $th;
    //     }
    // }

    // public function sampelDiantar($tahun)
    // {
    //     $allMembers   = $this->getAllTeamMembers();
    //     $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

    //     DB::beginTransaction();
    //     try {
    //         $bulkDPP    = $this->getDPPFromDailyQSD($allMemberIds, $tahun, 'sd');
    //         $emptyOrder = $this->getEmptyOrder($tahun);

    //         $teamsData = [];
    //         foreach ($allMembers as $member) {
    //             $teamIndex = $member['team_index'];
    //             $grade     = strtolower($member['grade']);
    //             $memberId  = $member['id'];

    //             if (! isset($teamsData[$teamIndex])) {
    //                 $teamsData[$teamIndex] = [
    //                     'staff'      => [],
    //                     'supervisor' => [],
    //                     'manager'    => [],
    //                 ];
    //             }

    //             $memberData                = $member['data'];
    //             $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
    //             $memberData['total_order'] = array_sum($memberData['order']);

    //             if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
    //                 $teamsData[$teamIndex][$grade][] = $memberData;
    //             }
    //         }

    //         $allteam_total_periode = $emptyOrder;
    //         foreach ($teamsData as $teamIndex => &$teamData) {
    //             $teamTotal      = $emptyOrder;
    //             $teamTotalStaff = $emptyOrder;

    //             foreach (['staff', 'supervisor', 'manager'] as $grade) {
    //                 if (isset($teamData[$grade])) {
    //                     foreach ($teamData[$grade] as &$member) {
    //                         foreach ($member['order'] as $month => $amount) {
    //                             $teamTotal[$month] += $amount;

    //                             if ($grade === 'staff') {
    //                                 $teamTotalStaff[$month] += $amount;
    //                             }
    //                         }
    //                     }
    //                 }
    //             }

    //             $teamData['team_total_periode']       = $teamTotal;
    //             $teamData['team_total']               = array_sum($teamTotal);
    //             $teamData['team_total_staff_periode'] = $teamTotalStaff;
    //             $teamData['team_total_staff']         = array_sum($teamTotalStaff);

    //             foreach ($teamTotal as $month => $amount) {
    //                 $allteam_total_periode[$month] += $amount;
    //             }
    //         }

    //         // $order = $this->order($tahun)['all_total_periode'];
    //         // $forecast = $this->forecast($tahun)['all_total_periode'];
    //         // $order_with_forecast = collect($order)
    //         //     ->mapWithKeys(fn($total, $bulan) => [
    //         //         $bulan => $total + ($forecast[$bulan] ?? 0)
    //         //     ])
    //         //     ->toArray();

    //         SummaryQSD::insert([
    //             'tahun'             => $tahun,
    //             'type'              => 'sampel_diantar',
    //             'data'              => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
    //             'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
    //             // 'order_forecast' => array_sum($order_with_forecast),
    //             'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
    //         ]);

    //         DB::commit();
    //         return [
    //             'data'              => $teamsData,
    //             'all_total_periode' => $allteam_total_periode,
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => $order_with_forecast,
    //             // 'order_forecast' => array_sum($order_with_forecast)
    //         ];
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         throw $th;
    //     }
    // }

    // public function contract($tahun)
    // {
    //     $allMembers   = $this->getAllTeamMembers();
    //     $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

    //     DB::beginTransaction();
    //     try {
    //         $bulkDPP    = $this->getDPPFromDailyQSD($allMemberIds, $tahun, 'contract');
    //         $emptyOrder = $this->getEmptyOrder($tahun);

    //         $teamsData = [];
    //         foreach ($allMembers as $member) {
    //             $teamIndex = $member['team_index'];
    //             $grade     = strtolower($member['grade']);
    //             $memberId  = $member['id'];

    //             if (! isset($teamsData[$teamIndex])) {
    //                 $teamsData[$teamIndex] = [
    //                     'staff'      => [],
    //                     'supervisor' => [],
    //                     'manager'    => [],
    //                 ];
    //             }

    //             $memberData                = $member['data'];
    //             $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
    //             $memberData['total_order'] = array_sum($memberData['order']);

    //             if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
    //                 $teamsData[$teamIndex][$grade][] = $memberData;
    //             }
    //         }

    //         $allteam_total_periode = $emptyOrder;
    //         foreach ($teamsData as $teamIndex => &$teamData) {
    //             $teamTotal      = $emptyOrder;
    //             $teamTotalStaff = $emptyOrder;

    //             foreach (['staff', 'supervisor', 'manager'] as $grade) {
    //                 if (isset($teamData[$grade])) {
    //                     foreach ($teamData[$grade] as &$member) {
    //                         foreach ($member['order'] as $month => $amount) {
    //                             $teamTotal[$month] += $amount;

    //                             if ($grade === 'staff') {
    //                                 $teamTotalStaff[$month] += $amount;
    //                             }
    //                         }
    //                     }
    //                 }
    //             }

    //             $teamData['team_total_periode']       = $teamTotal;
    //             $teamData['team_total']               = array_sum($teamTotal);
    //             $teamData['team_total_staff_periode'] = $teamTotalStaff;
    //             $teamData['team_total_staff']         = array_sum($teamTotalStaff);

    //             foreach ($teamTotal as $month => $amount) {
    //                 $allteam_total_periode[$month] += $amount;
    //             }
    //         }

    //         // $order = $this->order($tahun)['all_total_periode'];
    //         // $forecast = $this->forecast($tahun)['all_total_periode'];
    //         // $order_with_forecast = collect($order)
    //         //     ->mapWithKeys(fn($total, $bulan) => [
    //         //         $bulan => $total + ($forecast[$bulan] ?? 0)
    //         //     ])
    //         //     ->toArray();

    //         SummaryQSD::insert([
    //             'tahun'             => $tahun,
    //             'type'              => 'contract',
    //             'data'              => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
    //             'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
    //             // 'order_forecast' => array_sum($order_with_forecast),
    //             'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
    //         ]);

    //         DB::commit();
    //         return [
    //             'data'              => $teamsData,
    //             'all_total_periode' => $allteam_total_periode,
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => $order_with_forecast,
    //             // 'order_forecast' => array_sum($order_with_forecast)
    //         ];
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         throw $th;
    //     }
    // }

    // public function new ($tahun)
    // {
    //     $allMembers   = $this->getAllTeamMembers();
    //     $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

    //     DB::beginTransaction();
    //     try {
    //         $bulkDPP    = $this->getDPPFromDailyQSD($allMemberIds, $tahun, 'new');
    //         $emptyOrder = $this->getEmptyOrder($tahun);

    //         $teamsData = [];
    //         foreach ($allMembers as $member) {
    //             $teamIndex = $member['team_index'];
    //             $grade     = strtolower($member['grade']);
    //             $memberId  = $member['id'];

    //             if (! isset($teamsData[$teamIndex])) {
    //                 $teamsData[$teamIndex] = [
    //                     'staff'      => [],
    //                     'supervisor' => [],
    //                     'manager'    => [],
    //                 ];
    //             }

    //             $memberData                = $member['data'];
    //             $memberData['order']       = $bulkDPP[$memberId] ?? $emptyOrder;
    //             $memberData['total_order'] = array_sum($memberData['order']);

    //             if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
    //                 $teamsData[$teamIndex][$grade][] = $memberData;
    //             }
    //         }

    //         $allteam_total_periode = $emptyOrder;
    //         foreach ($teamsData as $teamIndex => &$teamData) {
    //             $teamTotal      = $emptyOrder;
    //             $teamTotalStaff = $emptyOrder;

    //             foreach (['staff', 'supervisor', 'manager'] as $grade) {
    //                 if (isset($teamData[$grade])) {
    //                     foreach ($teamData[$grade] as &$member) {
    //                         foreach ($member['order'] as $month => $amount) {
    //                             $teamTotal[$month] += $amount;

    //                             if ($grade === 'staff') {
    //                                 $teamTotalStaff[$month] += $amount;
    //                             }
    //                         }
    //                     }
    //                 }
    //             }

    //             $teamData['team_total_periode']       = $teamTotal;
    //             $teamData['team_total']               = array_sum($teamTotal);
    //             $teamData['team_total_staff_periode'] = $teamTotalStaff;
    //             $teamData['team_total_staff']         = array_sum($teamTotalStaff);

    //             foreach ($teamTotal as $month => $amount) {
    //                 $allteam_total_periode[$month] += $amount;
    //             }
    //         }

    //         // $order = $this->order($tahun)['all_total_periode'];
    //         // $forecast = $this->forecast($tahun)['all_total_periode'];
    //         // $order_with_forecast = collect($order)
    //         //     ->mapWithKeys(fn($total, $bulan) => [
    //         //         $bulan => $total + ($forecast[$bulan] ?? 0)
    //         //     ])
    //         //     ->toArray();

    //         SummaryQSD::insert([
    //             'tahun'             => $tahun,
    //             'type'              => 'new',
    //             'data'              => json_encode($teamsData, JSON_UNESCAPED_UNICODE),
    //             'all_total_periode' => json_encode($allteam_total_periode, JSON_UNESCAPED_UNICODE),
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => json_encode($order_with_forecast, JSON_UNESCAPED_UNICODE),
    //             // 'order_forecast' => array_sum($order_with_forecast),
    //             'created_at'        => Carbon::now()->format('Y-m-d H:i:s'),
    //         ]);

    //         DB::commit();
    //         return [
    //             'data'              => $teamsData,
    //             'all_total_periode' => $allteam_total_periode,
    //             'all_total'         => array_sum($allteam_total_periode),
    //             // 'order_forecast_periode' => $order_with_forecast,
    //             // 'order_forecast' => array_sum($order_with_forecast)
    //         ];
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         throw $th;
    //     }
    // }

     /**
     * Process single daily_qsd record
     */
    // private function processDailyQsdRecord($record, &$details)
    // {
    //     // dd($record, $details);
    //     // Tentukan periode berdasarkan kontrak atau tanggal sampling
    //     if ($record->kontrak == 'C' && ! empty($record->periode)) {
    //         // Untuk kontrak, gunakan periode
    //         $monthKey = Carbon::parse($record->periode)->locale('id')->translatedFormat('M');
    //     } else {
    //         // Untuk non-kontrak, gunakan tanggal_sampling_min
    //         $monthKey = Carbon::parse($record->tanggal_sampling_min)->locale('id')->translatedFormat('M');
    //     }

    //     // Gunakan total_revenue atau biaya_akhir sebagai DPP
    //     $dpp = $record->total_revenue ?? 0;

    //     $details[$monthKey][] = floor($dpp);
    // }

    // private function processKontrakOrder($order, &$details)
    // {
    //     if (empty($order->orderDetail)) {
    //         return;
    //     }

    //     $periode = $order->orderDetail->map(function ($item) {
    //         return Carbon::parse($item->tanggal_sampling)->format('Y-m');
    //     })->unique()->values()->toArray();

    //     $penawaran = $order->quotationKontrakH->detail ?? collect();
    //     if ($penawaran->isEmpty()) {
    //         return;
    //     }

    //     $periode_kontrak = $penawaran->pluck('periode_kontrak')->toArray();
    //     $periode_match   = array_intersect($periode, $periode_kontrak);

    //     $matchedPenawaran = $penawaran->whereIn('periode_kontrak', $periode_match);

    //     foreach ($matchedPenawaran as $item) {
    //         $monthKey             = Carbon::parse($item->periode_kontrak)->locale('id')->translatedFormat('M');
    //         $details[$monthKey][] = floor($item->total_dpp);
    //     }
    // }

        // private function getDPPFromDailyQSD($allMemberIds, $tahun, $type = 'order')
    // {
    //     $year = $tahun;

    //     $query = DB::table('daily_qsd')
    //         ->whereIn('sales_id', $allMemberIds)
    //         ->whereNotIn('pelanggan_ID', ['SAIR02', 'T2PE01'])
    //         ->whereYear('tanggal_sampling_min', $year);

    //     // Filter berdasarkan type
    //     switch ($type) {
    //         // case 'forecast':
    //         //     $query->where(function ($q) {
    //         //         $q->whereNull('no_order')->orWhere('no_order', '');
    //         //     });
    //         //     break;

    //         case 'order':
    //             $query->whereNotNull('no_order')
    //                 ->where('no_order', '!=', '');
    //             break;

    //         case 'contract':
    //             $query->where('kontrak', 'C')
    //                 ->whereNotNull('no_order');
    //             break;

    //         case 'sampling':
    //             $query->whereIn('status_sampling', ['S', 'S24'])
    //                 ->whereNotNull('no_order');
    //             break;

    //         case 'sd':
    //             $query->whereIn('status_sampling', ['SD', 'SP'])
    //                 ->whereNotNull('no_order');
    //             break;

    //         case 'new':
    //             $firstOrderCustomers = DB::table('daily_qsd')
    //                 ->select('pelanggan_ID')
    //                 ->whereNotNull('no_order')
    //                 ->groupBy('pelanggan_ID')
    //                 ->havingRaw('COUNT(DISTINCT no_order) = 1')
    //                 ->pluck('pelanggan_ID')
    //                 ->toArray();

    //             $query->whereIn('pelanggan_ID', $firstOrderCustomers)
    //                 ->whereNotNull('no_order');
    //             break;
    //     }

    //     $data = $query->get();

    //     if ($data->isEmpty()) {
    //         return [];
    //     }

    //     $dataBySales = $data->groupBy('sales_id');

    //     $result = [];
    //     foreach ($dataBySales as $salesId => $records) {
    //         $details = $this->initializeMonthlyDetails($tahun);

    //         foreach ($records as $record) {
    //             $this->processDailyQsdRecord($record, $details);
    //         }

    //         $result[$salesId] = array_map(fn($values) => array_sum($values), $details);
    //     }

    //     return $result;
    // }
}

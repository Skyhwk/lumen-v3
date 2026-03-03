<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ForecastSP;
use App\Services\GetBawahan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SummaryQSDController extends Controller
{
    private $managerIds;
    private $teamLookup;
    private $emptyOrder;

    public function __construct()
    {
        $this->managerIds = [19, 41, 14];
    }

    public function index(Request $request)
    {
        $year = $request->input('tahun', Carbon::now()->year);
        $type = strtolower(trim($request->type ?? 'order'));

        // Initialize empty order once
        $this->emptyOrder = $this->getEmptyOrder();

        // Get all team members with hierarchy
        $allMembers   = $this->getAllTeamMembers();
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        // Build team lookup map once
        $this->teamLookup = $this->buildTeamLookupMap($allMembers);

        // Get revenue data
        $bulkDPP = $this->getRevenueFromDailyQSD($allMemberIds, $year, $type);

        // Get forecast per sales (single query)
        $forecastPerSales = $this->getForecastPerSales($year, $type);

        // Find resigned members from revenue data
        $resignedFromRevenue = array_diff(array_keys($bulkDPP), $allMemberIds);
        // Find resigned members from forecast data
        $resignedFromForecast = array_diff(array_keys($forecastPerSales), $allMemberIds);
        // Merge unique resigned ids
        $resignedMemberIds = array_unique(array_merge($resignedFromRevenue, $resignedFromForecast));

        if (! empty($resignedMemberIds)) {
            $resignedMembers = $this->getResignedMembersWithTeam($resignedMemberIds);
            $allMembers      = array_merge($allMembers, $resignedMembers);
        }

        $teamsData = $this->processTeamData($allMembers, $bulkDPP, $forecastPerSales);

        // Calculate team totals
        $allteam_total_periode          = $this->emptyOrder;
        $allteam_forecast_total_periode = $this->emptyOrder;

        foreach ($teamsData as &$teamData) {
            $teamTotal              = $this->emptyOrder;
            $teamTotalStaff         = $this->emptyOrder;
            $teamForecastTotal      = $this->emptyOrder;
            $teamForecastTotalStaff = $this->emptyOrder;

            foreach (['staff', 'supervisor', 'manager'] as $grade) {
                if (! empty($teamData[$grade])) {
                    foreach ($teamData[$grade] as &$member) {
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

        $forecastTotal = array_sum($allteam_forecast_total_periode);

        return response()->json([
            'success'                => true,
            'type'                   => $type,
            'year'                   => $year,
            'data'                   => array_values($teamsData),
            'all_total_periode'      => $allteam_total_periode,
            'all_total'              => array_sum($allteam_total_periode),
            'forecast_total'         => $forecastTotal,
            'forecast_total_periode' => $allteam_forecast_total_periode,
            'message'                => 'Data berhasil diproses!',
        ], 200);
    }

    private function getTeams()
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
                ->values();

            $teams[] = $team;
        }

        return $teams;
    }

    private function getAllTeamMembers()
    {
        $teams      = $this->getTeams();
        $allMembers = [];

        foreach ($teams as $teamIndex => $team) {
            foreach ($team as $member) {
                $allMembers[] = [
                    'id'         => $member['id'],
                    'team_index' => $teamIndex,
                    'grade'      => strtolower($member['grade']),
                    'data'       => $member,
                ];
            }
        }

        return $allMembers;
    }

    // OPTIMASI: Build lookup map untuk team index
    private function buildTeamLookupMap($allMembers)
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

    // OPTIMASI: Process team data dalam satu loop, inject forecast per member
    private function processTeamData($allMembers, $bulkDPP, $forecastPerSales)
    {
        $teamsData = [];
        foreach ($allMembers as $member) {
            $teamIndex  = $member['team_index'];
            $grade      = $member['grade'];
            $memberId   = $member['id'];
            $isResigned = $member['is_resigned'] ?? false;

            if (! isset($teamsData[$teamIndex])) {
                $teamsData[$teamIndex] = [
                    'staff'      => [],
                    'supervisor' => [],
                    'manager'    => [],
                ];
            }

            $forecastData = $forecastPerSales[$memberId] ?? null;

            $memberData                   = $member['data'];
            $memberData['order']          = $bulkDPP[$memberId] ?? $this->emptyOrder;
            $memberData['total_order']    = array_sum($memberData['order']);
            $memberData['forecast']       = $forecastData['periode'] ?? $this->emptyOrder;
            $memberData['total_forecast'] = $forecastData['total_tahun'] ?? 0;
            $memberData['is_resigned']    = $isResigned;

            if ($memberData['total_order'] > 0 || $memberData['total_forecast'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                $teamsData[$teamIndex][$grade][] = $memberData;
            }
        }

        return $teamsData;
    }

    // OPTIMASI: Query database yang lebih efisien
    private function getRevenueFromDailyQSD($allMemberIds, $tahun, $type = 'order')
    {
        if (empty($allMemberIds)) {
            return [];
        }

        $query = DB::table('daily_qsd')
            ->select(
                'sales_id',
                DB::raw("MONTH(tanggal_kelompok) as month_num"),
                DB::raw('SUM(total_revenue) as total_revenue')
            )
            ->whereNotIn('pelanggan_ID', ['SAIR02', 'T2PE01'])
            ->whereYear('tanggal_kelompok', $tahun);

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

            case 'sampel_diantar':
            case 'sd':
                $query->whereIn('status_sampling', ['SD', 'SP'])
                    ->whereNotNull('no_order');
                break;

            case 'new':
                $query->where('status_customer', 'new')
                    ->whereNotNull('no_order');
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
            $salesId = $record->sales_id;

            if (! isset($result[$salesId])) {
                $result[$salesId] = $this->emptyOrder;
            }

            $monthKey                     = $monthNames[$record->month_num];
            $result[$salesId][$monthKey] += $record->total_revenue;
        }

        return $result;
    }

    // OPTIMASI: Static array tanpa loop
    private function getEmptyOrder()
    {
        return [
            'Jan' => 0, 'Feb' => 0, 'Mar' => 0, 'Apr' => 0,
            'Mei' => 0, 'Jun' => 0, 'Jul' => 0, 'Agt' => 0,
            'Sep' => 0, 'Okt' => 0, 'Nov' => 0, 'Des' => 0,
        ];
    }

    // OPTIMASI: Batch query dengan lookup map
    private function getResignedMembersWithTeam($resignedMemberIds)
    {
        $resignedUsers = DB::table('master_karyawan')
            ->select('id', 'nama_lengkap', 'grade', 'atasan_langsung', 'image')
            ->whereIn('id', $resignedMemberIds)
            ->get();

        $resignedMembers = [];

        foreach ($resignedUsers as $user) {
            $atasanList = json_decode($user->atasan_langsung, true) ?? [];

            $teamIndex = 0; 

            foreach ($atasanList as $atasanId) {
                $atasanId = (int) $atasanId;

                if (isset($this->teamLookup[$atasanId])) {
                    $teamIndex = $this->teamLookup[$atasanId];
                    break; 
                }
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
                    'image'           => $user->image,
                ],
            ];
        }

        return $resignedMembers;
    }

    private function getForecastPerSales(int $tahun, string $type)
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

        $forecasts = $forecasts->get();

        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

        $result = [];

        foreach ($forecasts as $forecast) {
            $sid = $forecast->sales_id;

            if (! isset($result[$sid])) {
                $result[$sid] = [
                    'total_tahun' => 0,
                    'periode'     => $this->getEmptyOrder(),
                ];
            }

            $bulanNumber = Carbon::parse($forecast->tanggal_sampling_min)->format('n');
            $bulan       = $monthNames[$bulanNumber];

            if (! isset($result[$sid]['periode'][$bulan])) {
                $result[$sid]['periode'][$bulan] = 0;
            }

            $result[$sid]['periode'][$bulan] += $forecast->revenue_forecast;
            $result[$sid]['total_tahun']     += $forecast->revenue_forecast;
        }

        return $result;
    }
}

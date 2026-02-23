<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ForecastSP;
use App\Services\GetBawahan;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DataForecastController extends Controller
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
        $tahun            = (int) $request->year;
        $this->emptyOrder = $this->getEmptyOrder();

        $allMembers   = $this->getAllTeamMembers();
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        $this->teamLookup = $this->buildTeamLookupMap($allMembers);

        $forecastPerSales = $this->getForecastPerSales($tahun);

        $resignedMemberIds = array_diff(array_keys($forecastPerSales), $allMemberIds);

        if (! empty($resignedMemberIds)) {
            $resignedMembers = $this->getResignedMembersWithTeam($resignedMemberIds);
            $allMembers      = array_merge($allMembers, $resignedMembers);
        }

        $teamsData = $this->processTeamData($allMembers, $forecastPerSales);

        $allteam_total_periode = $this->emptyOrder;

        foreach ($teamsData as &$teamData) {
            $teamTotal      = $this->emptyOrder;
            $teamTotalStaff = $this->emptyOrder;

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
        // Menggunakan query() agar efisien
        $data = ForecastSP::whereYear('tanggal_sampling_min', $request->year);

        // Paksa order ke kolom yang PASTI ADA, misalnya tanggal_sampling_min
        return Datatables::of($data)
            ->make(true);
    }

    private function getForecastPerSales(int $tahun)
    {
        $forecasts = ForecastSP::whereYear('tanggal_sampling_min', $tahun)->get();

        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

        $result = [];

        foreach ($forecasts as $forecast) {
            $sid = $forecast->sales_id;

            if (! isset($result[$sid])) {
                $result[$sid] = $this->emptyOrder;
            }

            // $bulan = $monthNames[intval(explode('-', $forecast->tanggal_sampling_min)[1])];
            $bulanNumber = Carbon::parse($forecast->tanggal_sampling_min)->format('n');
            $bulan       = $monthNames[$bulanNumber];

            // anti undefined index
            if (! isset($result[$sid][$bulan])) {
                $result[$sid][$bulan] = 0;
            }

            $result[$sid][$bulan] += $forecast->revenue_forecast;
        }

        return $result;
    }

    private function getEmptyOrder()
    {
        return [
            'Jan' => 0, 'Feb' => 0, 'Mar' => 0,
            'Apr' => 0, 'Mei' => 0, 'Jun' => 0,
            'Jul' => 0, 'Agt' => 0, 'Sep' => 0,
            'Okt' => 0, 'Nov' => 0, 'Des' => 0,
        ];
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

    // OPTIMASI: Process team data dalam satu loop
    private function processTeamData($allMembers, $bulkDPP)
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

            $memberData                = $member['data'];
            $memberData['order']       = $bulkDPP[$memberId] ?? $this->emptyOrder;
            $memberData['total_order'] = array_sum($memberData['order']);
            $memberData['is_resigned'] = $isResigned;

            if ($memberData['total_order'] > 0 || in_array($grade, ['manager', 'supervisor'])) {
                $teamsData[$teamIndex][$grade][] = $memberData;
            }

        }


        return $teamsData;
    }

    private function getResignedMembersWithTeam($resignedMemberIds)
    {


        $resignedUsers = DB::table('master_karyawan')
            ->select('id', 'nama_lengkap', 'grade', 'atasan_langsung', 'image')
            ->whereIn('id', $resignedMemberIds)
            ->get();

        $resignedMembers = [];

        foreach ($resignedUsers as $user) {
            // Gunakan lookup map yang sudah dibuat
            $teamIndex = $this->teamLookup[$user->atasan_langsung] ?? 0;

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

}

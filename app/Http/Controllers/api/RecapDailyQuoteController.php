<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\OrderHeader;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RecapDailyQuoteController extends Controller
{

    private $managerIds;

    public function __construct()
    {
        $this->managerIds = [19, 41, 14];
    }

    public function index(Request $request)
    {
        // Initialize empty order once
        $this->emptyOrder = $this->getEmptyOrder();

        // Get all team members with hierarchy
        $allMembers   = $this->getAllTeamMembers();
        $allMemberIds = collect($allMembers)->pluck('id')->unique()->toArray();

        $this->teamLookup = $this->buildTeamLookupMap($allMembers);

        $quotationData = $this->getQuotationData($request->date ?? Carbon::now()->format('Y-m-d'), $allMemberIds);

        $teamsData = $this->processTeamDataQuotation($allMembers, $quotationData);

        $allteam_total = 0;

        foreach ($teamsData as &$teamData) {
            $teamTotal      = 0;
            $teamTotalStaff = 0;

            foreach (['staff', 'supervisor', 'manager'] as $grade) {
                if (! empty($teamData[$grade])) {
                    foreach ($teamData[$grade] as &$member) {
                        $memberTotal    = $member['total_biaya_akhir'] ?? 0;
                        $teamTotal     += $memberTotal;
                        $allteam_total += $memberTotal;

                        if ($grade === 'staff') {
                            $teamTotalStaff += $memberTotal;
                        }
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

    private function getQuotationData($date, $allMemberIds)
    {
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
            // ->where('request_quotation.sales_id', 37)
            ->groupBy('request_quotation.sales_id');


            // dd($quotationNon->get());

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
            // ->where('request_quotation_kontrak_H.sales_id', 37)
            ->groupBy('request_quotation_kontrak_H.sales_id');

        $union = $quotationNon->unionAll($quotationKon);

        // dd($union->get());

        $data = DB::query()
            ->fromSub($union, 'x')
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
            $result[$record->sales_id] = [
                'total_request_quotation'    => $record->total_request_quotation,
                'total_biaya_akhir'          => $record->total_biaya_akhir,
                'pelanggan_lama'             => $record->pelanggan_lama,
                'pelanggan_baru'             => $record->pelanggan_baru,
                'total_biaya_pelanggan_lama' => $record->total_biaya_pelanggan_lama,
                'total_biaya_pelanggan_baru' => $record->total_biaya_pelanggan_baru,
            ];
        }

        return $result;
    }

    private function processTeamDataQuotation($allMembers, $quotationData)
    {
        $teamsData       = [];
        $staffJabatanIds = [24, 148]; // Filter untuk staff dengan id_jabatan ini

        foreach ($allMembers as $member) {
            $teamIndex = $member['team_index'];
            $grade     = $member['grade'];
            $memberId  = $member['id'];

            if (! isset($teamsData[$teamIndex])) {
                $teamsData[$teamIndex] = [
                    'staff'      => [],
                    'supervisor' => [],
                    'manager'    => [],
                ];
            }

            $memberData = $member['data'];
            $quotation  = $quotationData[$memberId] ?? null;

            if ($grade === 'staff' && ! in_array($memberData['id_jabatan'] ?? null, $staffJabatanIds)) {
                continue;
            }

            $memberData['total_request_quotation']    = $quotation['total_request_quotation'] ?? 0;
            $memberData['total_biaya_akhir']          = $quotation['total_biaya_akhir'] ?? 0;
            $memberData['pelanggan_lama']             = $quotation['pelanggan_lama'] ?? 0;
            $memberData['pelanggan_baru']             = $quotation['pelanggan_baru'] ?? 0;
            $memberData['total_biaya_pelanggan_lama'] = $quotation['total_biaya_pelanggan_lama'] ?? 0;
            $memberData['total_biaya_pelanggan_baru'] = $quotation['total_biaya_pelanggan_baru'] ?? 0;

            $teamsData[$teamIndex][$grade][] = $memberData;
        }

        return $teamsData;
    }

    private function getEmptyOrder()
    {
        return [
            'Jan' => 0, 'Feb' => 0, 'Mar' => 0, 'Apr' => 0,
            'Mei' => 0, 'Jun' => 0, 'Jul' => 0, 'Agt' => 0,
            'Sep' => 0, 'Okt' => 0, 'Nov' => 0, 'Des' => 0,
        ];
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
                        'id_jabatan'      => $item->id_jabatan,
                    ];
                })
                ->values();

            $teams[] = $team;
        }

        return $teams;
    }

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

}


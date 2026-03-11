<?php

namespace App\Http\Controllers\api;

use App\Models\QuotationKontrakH;
use App\Models\OrderHeader;
use App\Services\GenerateToken;
use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Datatables;
use Carbon\Carbon;


class ContractBySalesController extends Controller
{

    // Method baru: Contract per Sales (Logic Revenue)
    public function index(Request $request)
    {
        try {
            $subTahun = substr($request->year, -2);

            $rawOrders = OrderHeader::select('no_document', 'sales_id')
                ->where('is_active', 1)
                ->where('no_document', "LIKE", "%QTC/$subTahun%")
                ->get();

            $noDocs = $rawOrders->pluck('no_document')->toArray();

            $allQuotations = QuotationKontrakH::with('detail')
                ->whereIn('no_document', $noDocs)
                ->get()
                ->groupBy('no_document');

            $groupedBySales = $rawOrders->groupBy('sales_id')->map(function ($orders, $salesId) use ($allQuotations) {

                $bulanSales = [
                    'january' => 0,
                    'february' => 0,
                    'march' => 0,
                    'april' => 0,
                    'may' => 0,
                    'june' => 0,
                    'july' => 0,
                    'august' => 0,
                    'september' => 0,
                    'october' => 0,
                    'november' => 0,
                    'december' => 0,
                ];

                $totalRevenueSales = 0;

                foreach ($orders as $order) {
                    $quotation = $allQuotations[$order->no_document] ?? [];

                    $revenueData = $this->processRevenueDetails($quotation);

                    $totalRevenueSales += $revenueData['total_revenue'];

                    foreach ($revenueData['bulan'] as $key => $val) {
                        $bulanSales[$key] += $val;
                    }
                }

                return (object) [
                    'sales_name'    => MasterKaryawan::find($salesId)->nama_lengkap,
                    'summary'       => $totalRevenueSales,
                    'bulan_summary' => $bulanSales
                ];
            });

            $grandTotalSummary = $groupedBySales->sum('summary');
            $grandTotalBulan = [
                'january' => 0,
                'february' => 0,
                'march' => 0,
                'april' => 0,
                'may' => 0,
                'june' => 0,
                'july' => 0,
                'august' => 0,
                'september' => 0,
                'october' => 0,
                'november' => 0,
                'december' => 0,
            ];

            foreach ($groupedBySales as $salesData) {
                foreach ($salesData->bulan_summary as $k => $v) {
                    $grandTotalBulan[$k] += $v;
                }
            }

            return datatables()->of($groupedBySales)
                ->addColumn('bulan_summary', function ($row) {
                    return $row->bulan_summary;
                })
                ->with(array_merge([
                    'total_summary' => $grandTotalSummary,
                ], collect($grandTotalBulan)->mapWithKeys(fn($v, $k) => ["total_{$k}" => $v])->toArray()))
                ->make(true);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage() . ' line: ' . $th->getLine()], 500);
        }
    }

    private function processRevenueDetails($quotationHeaders)
    {
        $bulan = [
            'january' => 0,
            'february' => 0,
            'march' => 0,
            'april' => 0,
            'may' => 0,
            'june' => 0,
            'july' => 0,
            'august' => 0,
            'september' => 0,
            'october' => 0,
            'november' => 0,
            'december' => 0,
        ];

        $totalRevenue = 0;

        foreach ($quotationHeaders as $value) {
            foreach ($value->detail as $detail) {
                $nilaiRevenue = ($detail->biaya_akhir ?? 0) - ($detail->total_ppn ?? 0) + ($detail->total_pph ?? 0);

                $totalRevenue += $nilaiRevenue;

                $bulanStr = explode('-', $detail->periode_kontrak)[1] ?? null;

                switch ($bulanStr) {
                    case '01':
                        $bulan['january'] += $nilaiRevenue;
                        break;
                    case '02':
                        $bulan['february'] += $nilaiRevenue;
                        break;
                    case '03':
                        $bulan['march'] += $nilaiRevenue;
                        break;
                    case '04':
                        $bulan['april'] += $nilaiRevenue;
                        break;
                    case '05':
                        $bulan['may'] += $nilaiRevenue;
                        break;
                    case '06':
                        $bulan['june'] += $nilaiRevenue;
                        break;
                    case '07':
                        $bulan['july'] += $nilaiRevenue;
                        break;
                    case '08':
                        $bulan['august'] += $nilaiRevenue;
                        break;
                    case '09':
                        $bulan['september'] += $nilaiRevenue;
                        break;
                    case '10':
                        $bulan['october'] += $nilaiRevenue;
                        break;
                    case '11':
                        $bulan['november'] += $nilaiRevenue;
                        break;
                    case '12':
                        $bulan['december'] += $nilaiRevenue;
                        break;
                }
            }
        }

        return ['bulan' => $bulan, 'total_revenue' => $totalRevenue];
    }
}

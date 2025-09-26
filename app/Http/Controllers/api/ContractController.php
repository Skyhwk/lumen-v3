<?php

namespace App\Http\Controllers\api;

use App\Models\QuotationKontrakH;
use App\Models\OrderHeader;
use App\Services\GenerateToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Datatables;
use Carbon\Carbon;


class ContractController extends Controller
{

    public function index(Request $request)
    {
        try {
            $subTahun = substr($request->year, -2); 

            $ordersQuery = OrderHeader::select(
                'no_document',
                DB::raw('MAX(wilayah) as wilayah'),
                DB::raw('MAX(nama_perusahaan) as nama_perusahaan'),
                DB::raw('SUM(total_dpp) as summary')
            )
            ->where('is_active', 1)
            ->where('no_document', "LIKE", "%QTC/$subTahun%")
            ->groupBy('no_document');
            
            // Eksekusi query
            $orders = $ordersQuery->get();

            // Total summary
            $total_summary = $orders->sum('summary');

            // Init total bulanan
            $bulanTotals = [
                'january' => 0, 'february' => 0, 'march' => 0, 'april' => 0,
                'may' => 0, 'june' => 0, 'july' => 0, 'august' => 0,
                'september' => 0, 'october' => 0, 'november' => 0, 'december' => 0,
            ];
            // dd($orders);

            $noDocs = $orders->pluck('no_document')->toArray();

            $allQuotations = QuotationKontrakH::with('detail')
            ->whereIn('no_document', $noDocs)
            ->get()
            ->groupBy('no_document');

            // Tambahin properti bulan_summary di tiap order
            foreach ($orders as $order) {
                $bulan = $this->processDetails($allQuotations[$order->no_document] ?? []);
                $order->bulan_summary = $bulan;

                foreach ($bulan as $key => $val) {
                    $bulanTotals[$key] += $val;
                }
            }

            // Pake datatables dari collection
            return datatables()->of($orders)
                ->addColumn('bulan_summary', function ($row) {
                    return $row->bulan_summary;
                })
                ->with(array_merge([
                    'total_summary' => $total_summary,
                ], collect($bulanTotals)->mapWithKeys(fn($v, $k) => ["total_{$k}" => $v])->toArray()))
                ->make(true);

        } catch (\Throwable $th) {
            dd($th);
        }
    }


    private function processDetails($quotationHeaders)
    {
        $bulan = [
            'january' => 0, 'february' => 0, 'march' => 0, 'april' => 0,
            'may' => 0, 'june' => 0, 'july' => 0, 'august' => 0,
            'september' => 0, 'october' => 0, 'november' => 0, 'december' => 0,
        ];

        foreach ($quotationHeaders as $value) {
            foreach ($value->detail as $detail) {
                $bulanStr = explode('-', $detail->periode_kontrak)[1] ?? null;

                switch ($bulanStr) {
                    case '01': $bulan['january'] += $detail->total_dpp; break;
                    case '02': $bulan['february'] += $detail->total_dpp; break;
                    case '03': $bulan['march'] += $detail->total_dpp; break;
                    case '04': $bulan['april'] += $detail->total_dpp; break;
                    case '05': $bulan['may'] += $detail->total_dpp; break;
                    case '06': $bulan['june'] += $detail->total_dpp; break;
                    case '07': $bulan['july'] += $detail->total_dpp; break;
                    case '08': $bulan['august'] += $detail->total_dpp; break;
                    case '09': $bulan['september'] += $detail->total_dpp; break;
                    case '10': $bulan['october'] += $detail->total_dpp; break;
                    case '11': $bulan['november'] += $detail->total_dpp; break;
                    case '12': $bulan['december'] += $detail->total_dpp; break;
                }
            }
        }

        return $bulan;
    }
}
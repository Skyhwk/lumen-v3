<?php

namespace App\Http\Controllers\api;

date_default_timezone_set('Asia/Jakarta');

use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use App\Http\Controllers\Controller;
use App\Models\{OrderHeader, OrderDetail};
use DB;

class DailyQsdController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date;
        [$year, $month] = explode('-', $date);

        $data = OrderHeader::with([
            'orderDetail:id,id_order_header,tanggal_sampling',
            'quotationKontrakH' => function($q) use ($year, $month) {
                $q->select('id', 'no_document', 'sales_id', 'total_dpp')
                ->where('no_document', 'LIKE', 'QTC%')
                ->with([
                    'sales:id,nama_lengkap',
                    'quotationKontrakD:id,id_request_quotation_kontrak_h,total_dpp'
                ]);
            },
            'quotationNonKontrak' => function($q) {
                $q->select('no_document', 'total_dpp', 'sales_id')
                ->whereRaw("no_document NOT LIKE 'QTC%'")
                ->with('sales:id,nama_lengkap');
            }
        ])
        ->where('flag_status', 'ordered')
        ->where('is_active', true)
        ->select('id', 'no_document', 'tanggal_order', 'nama_perusahaan')
        ->get()
        ->map(function($order) {
            $tanggal = $order->orderDetail->pluck('tanggal_sampling')->min();
            if ($order->no_document && strpos($order->no_document, 'QTC') === 0) {
                $tipe = 'kontrak';
                $jumlah = $order->quotationKontrakH->flatMap->quotationKontrakD->sum('total_dpp');
            } else {
                $tipe = 'non_kontrak';
                $jumlah = $order->quotationNonKontrak->total_dpp ?? 0;
            }

            return [
                'tipe' => $tipe,
                'tanggal' => $tanggal,
                'jumlah' => $jumlah,
                'detail' => $order
            ];
        })
        ->filter(function($item) use ($year, $month) {
            return substr($item['tanggal'], 0, 4) == $year && substr($item['tanggal'], 5, 2) == $month;
        })
        ->groupBy('tanggal')
        ->map(function($items, $tanggal) {
            return [
                'tanggal' => $tanggal,
                'jumlah' => $items->sum('jumlah'),
                'detail' => $items->pluck('detail')->values()
            ];
        })
        ->values();

        $mappedDate = Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $mappedDate->daysInMonth;
        $existingData = collect($data)->keyBy('tanggal');

        $mappedData = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $tanggal = Carbon::createFromDate($year, $month, $day)->format('Y-m-d');
            if ($existingData->has($tanggal)) {
                $mappedData[] = $existingData->get($tanggal);
            } else {
                $mappedData[] = [
                    'tanggal' => $tanggal,
                    'jumlah' => 0,
                    'detail' => []
                ];
            }
        }

        return response()->json([
            'message' => 'success get data',
            'data' => $mappedData
        ], 200);
    }
}

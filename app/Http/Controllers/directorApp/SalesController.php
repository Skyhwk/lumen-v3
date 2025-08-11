<?php

namespace App\Http\Controllers\directorApp;

use Laravel\Lumen\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{QuotationKontrakH, QuotationNonKontrak, MasterKaryawan, OrderHeader, SalesIn};

class SalesController extends Controller
{
    public function getRecapDailyQuotations(Request $request)
    {
        $date = $request->date;

        $quotations = QuotationNonKontrak::getQuotationSummary($date)
            ->unionAll(QuotationKontrakH::getQuotationKontrakSummary($date));

        // $data = $quotations->get();
        $data = DB::table(DB::raw("({$quotations->toSql()}) as combined"))
            ->mergeBindings($quotations->getQuery())
            ->selectRaw('
                    master_karyawan.nama_lengkap as sales_name,
                    sales_id,
                    SUM(total_request_quotation) as total_request_quotation,
                    SUM(total_biaya_akhir) as total_biaya_akhir,
                    SUM(total_biaya_pelanggan_baru) as total_biaya_pelanggan_baru,
                    SUM(total_biaya_pelanggan_lama) as total_biaya_pelanggan_lama,
                    SUM(pelanggan_baru) as pelanggan_baru,
                    SUM(pelanggan_lama) as pelanggan_lama
                ')
            ->leftJoin('master_karyawan', 'sales_id', '=', 'master_karyawan.id')
            ->groupBy('sales_id')
            ->get();
        $data->transform(function ($quotation) {
            if ($quotation->sales_id) {
                $sales = MasterKaryawan::where('id', $quotation->sales_id)->first();
                $atasanIds = json_decode($sales->atasan_langsung, true);
                $quotation->supervisor = MasterKaryawan::whereIn('id', $atasanIds)->where('grade', 'SUPERVISOR')->where('department', 'SALES')->first();
                if ($quotation->supervisor === null) {
                    $quotation->supervisor = (object) [
                        'nama_lengkap' => $sales->nama_lengkap
                    ];
                }
                $quotation->manager = MasterKaryawan::whereIn('id', $atasanIds)->where('grade', 'Manager')->where('department', 'SALES')->first();
                if ($quotation->manager === null) {
                    $quotation->manager = (object) [
                        'nama_lengkap' => $sales->nama_lengkap
                    ];
                }
            }
            return $quotation;
        });

        $data = $data->sortBy(function ($quotation) {
            return optional($quotation->supervisor)->nama_lengkap ?? '';
        })->values();

        /* Paginator
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 10);
        $offset = ($page - 1) * $perPage;
        $paged = $sorted->slice($offset, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $paged,
            $sorted->count(),
            $perPage,
            $page,
            ['path' => url()->current(), 'query' => $request->query()]
        );*/

        return response()->json([
            'message' => 'Recap Quotations retrieved successfully',
            'recap_quotations' => $data
        ], 200);
    }

    public function getPointOfSales(Request $request)
    {
        $dataOrder = OrderHeader::where('is_active', true)
            ->whereYear('created_at', date('Y'))
            ->count();

        $ordertoday = OrderHeader::where('is_active', true)
            ->whereDate('created_at', date('Y-m-d'))
            ->sum('biaya_akhir');

        $rawData = DB::table('all_qt_active')
            ->whereRaw('SUBSTRING(periode_kontrak, 1, 4) = ?', [date('Y')])
            ->select('periode_kontrak', \DB::raw('SUM(biaya_akhir) as total_penjualan'))
            ->groupBy('periode_kontrak')
            ->get();

        /* Paginator
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 10);
        $offset = ($page - 1) * $perPage;

        $pagedData = $rawData->slice($offset, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $pagedData,
            $rawData->count(),
            $perPage,
            $page,
            ['path' => url()->current(), 'query' => $request->query()]
        );*/

        return response()->json([
            'message' => 'Point Of Sales data retrieved successfully',
            'dataOrder' => $dataOrder,
            'ordertoday' => $ordertoday,
            'point_of_sales' => $rawData
        ]);
    }

    public function getSalesIn(Request $request)
    {
        $salesIn = SalesIn::where('is_active', true)
            ->where('tanggal_masuk', $request->date)
            ->orderByDesc('id');

        if ($request->status) $salesIn = $salesIn->where('status', $request->status);
        if ($request->paymentType) $salesIn = $salesIn->where('type_pembayaran', $request->paymentType);
        if ($request->accountType) $salesIn = $salesIn->where('type_rekening', $request->accountType);
        if ($request->searchTerm) {
            $salesIn = $salesIn->where(function ($query) use ($request) {
                $query->where('no_dokumen', 'like', '%' . $request->searchTerm . '%')
                    ->orWhere('nominal', 'like', '%' . $request->searchTerm . '%')
                    ->orWhere('keterangan', 'like', '%' . $request->searchTerm . '%');
            });
        }

        $salesInCount = $salesIn->sum('nominal');

        $salesIn = $salesIn->paginate(10);

        return response()->json([
            'message' => 'Sales In data retrieved successfully',
            'data' => $salesIn,
            'salesInCount' => $salesInCount
        ], 200);
    }

    public function getSalesInFilterParams()
    {
        $statuses = SalesIn::select('status')
            ->distinct()
            ->pluck('status')
            ->toArray();

        $paymentTypes = SalesIn::select('type_pembayaran')
            ->distinct()
            ->pluck('type_pembayaran')
            ->toArray();

        $accountTypes = SalesIn::select('type_rekening')
            ->distinct()
            ->pluck('type_rekening')
            ->toArray();

        return response()->json([
            'message' => 'Sales In Filter Params retrieved successfully',
            'data' => [
                'statuses' => $statuses,
                'paymentTypes' => $paymentTypes,
                'accountTypes' => $accountTypes
            ]
        ], 200);
    }

    public function getSalesInReports(Request $request)
    {
        $data = SalesIn::where('is_active', true)
            ->whereYear('created_at', date('Y'))
            ->get();

        $dataOrder = OrderHeader::where('is_active', true)->whereYear('created_at', date('Y'))->count();

        return response()->json([
            'data' => $data,
            'dataOrder' => $dataOrder
        ]);
    }
}

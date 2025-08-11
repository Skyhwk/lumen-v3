<?php

namespace App\Http\Controllers\directorApp;

use Laravel\Lumen\Routing\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{QuotationKontrakH, QuotationNonKontrak, SalesIn, MasterKaryawan, LogDoor};

class DashboardController extends Controller
{
    public function perMenuCount(Request $request)
    {
        $date = $request->date;

        // Gabungkan data quotation non-kontrak dan kontrak
        $quotations = QuotationNonKontrak::getQuotationSummary($date)
            ->unionAll(QuotationKontrakH::getQuotationKontrakSummary($date));

        // Hitung jumlah unique sales_id dari gabungan data
        $dailQuotesCount = DB::table(DB::raw("({$quotations->toSql()}) as combined"))
            ->mergeBindings($quotations->getQuery())
            ->select(DB::raw('COUNT(DISTINCT sales_id) as total'))
            ->value('total');

        $pointOfSalesCount = 0;

        $salesInReportsCount = SalesIn::where('is_active', true)
            ->where('tanggal_masuk', $request->date)
            ->count();

        $employeesCount = MasterKaryawan::where('is_active', true)->where('id', '!=', 1)->count();

        $accessDoorCount = LogDoor::whereNotNull('userid')->where('tanggal', $request->date)->count();

        $gpsViewCount = 0;

        return response()->json([
            'dailQuotesCount' => $dailQuotesCount,
            'pointOfSalesCount' => $pointOfSalesCount,
            'salesInReportsCount' => $salesInReportsCount,
            'employeesCount' => $employeesCount,
            'accessDoorCount' => $accessDoorCount,
            'gpsViewCount' => $gpsViewCount
        ], 200);
    }
}

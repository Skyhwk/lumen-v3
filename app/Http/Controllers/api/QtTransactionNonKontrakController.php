<?php

namespace App\Http\Controllers\api;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\PersiapanSampelHeader;
use App\Models\QtTransactionNonKontrak;
use App\Models\QrDocument;

use Carbon\Carbon;

use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\Parameter;
use App\Services\QtTransactionNonKontrakService;
use Mpdf;

class QtTransactionNonKontrakController extends Controller
{
    public function index()
    {
        $data = QtTransactionNonKontrak::with(['quotation','sales']);
        return Datatables::of($data)
            ->editColumn('rekap_transactions', function ($data) {
                return json_decode($data->rekap_transactions);
            })
            ->make(true);
    }

    public function hitCreateorUpdateData(Request $request)
    {
        try {
            $service = new QtTransactionNonKontrakService();
            $data = $service->run();

            return response()->json([
                'message' => 'success',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTrace()
            ], 500);
        }
    }
}

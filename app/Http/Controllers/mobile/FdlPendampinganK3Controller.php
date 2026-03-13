<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganSampah;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

// SERVICE
use App\Services\SendTelegram;
use App\Services\GetAtasan;
use App\Services\InsertActivityFdl;

use App\Http\Controllers\Controller;
use App\Models\PendampinganK3Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlPendampinganK3Controller extends Controller
{
    public function getOrderByNoOrder(Request $request)
    {
        $order = OrderHeader::with('orderDetail')->where('no_order', $request->no_order)->first();

        return response()->json([
            'data' => $order
        ]);
    }

    public function index()
    {
        $template = PendampinganK3Template::where('is_active', 1)->get();

        return response()->json([
            'data' => $template
        ]);
    }
}

<?php

namespace App\Http\Controllers\api;

use App\Models\PengajuanFeeSampling;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Database\Eloquent\Collection;

class RekapFeeSamplingController extends Controller
{
    public function index(Request $request)
    {
        $data = PengajuanFeeSampling::with(['detail_fee' => function ($q) {
            $q->where('is_approve', 1);
        }])
            ->where('is_approve_finance', 1)
            ->whereNotNull('transfer_date')
            ->where('is_upload_bukti_pembayaran', 1);

        return Datatables::of($data)->make(true);
    }
}
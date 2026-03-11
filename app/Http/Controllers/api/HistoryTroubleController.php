<?php

namespace App\Http\Controllers\api;

use App\Models\HoldHp;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\WsValueAir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\DeviceIntilabRunning;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class HistoryTroubleController extends Controller
{
    public function index(Request $request)
    {
        $data = DeviceIntilabRunning::with('offlineReason', 'alat')->orderBy('start_at', 'desc')
            ->whereHas('offlineReason');

        return Datatables::of($data)->make(true);
    }
}

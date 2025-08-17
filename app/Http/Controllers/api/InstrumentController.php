<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\InstrumentIcp;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use DB;

class InstrumentController extends Controller
{
    public function indexIcp(Request $request){
        try {
            $data = InstrumentIcp::with(['colorimetri.ws_value','draft','draft_air'])
                ->orderBy('instrument_icp.created_at', 'desc')
                ->get();
            return Datatables::of($data)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data: '.$e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}

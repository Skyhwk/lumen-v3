<?php

namespace App\Http\Controllers\api;

use App\Models\PengajuanFeeSampling;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Database\Eloquent\Collection;

class RejectFeeSamplingController extends Controller
{
    public function index(Request $request)
    {
        $data = PengajuanFeeSampling::with(['detail_fee' => function ($q) {
            $q->where('is_reject', 1);
        }])
            ->where('is_reject_finance', 1)
            ->where('is_checked_by_admin', 0);
        return Datatables::of($data)->make(true);
    }

    public function indexChecked(Request $request)
    {
        $data = PengajuanFeeSampling::with(['detail_fee' => function ($q) {
            $q->where('is_reject', 1);
        }])
            ->where(function ($q) {
                $q->where('is_approve_finance', 1)
                    ->orWhere('is_reject_finance', 1);
            })
            ->where('is_checked_by_admin', 1)
            ->whereIn('status_payment', ["Approved by finance", "Approved by expanse", "Rejected"]);

        return Datatables::of($data)->make(true);
    }

    public function handleApprove(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = PengajuanFeeSampling::where('id', $request->id)->first();
            if ($data) {
                $data->is_checked_by_admin = 1;
                $data->save();
            } else {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan', 'status' => 400], 400);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data berhasil di approve', 'status' => 200], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage(), 'status' => 500], 500);
        }
    }
}

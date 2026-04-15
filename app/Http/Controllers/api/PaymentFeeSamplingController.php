<?php

namespace App\Http\Controllers\api;

use App\Models\PengajuanFeeSampling;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Database\Eloquent\Collection;
use App\Services\NotificationFdlService;

class PaymentFeeSamplingController extends Controller
{
    public function index(Request $request)
    {
        $data = PengajuanFeeSampling::with(['detail_fee' => function ($q) {
            $q->where('is_approve', 1);
        }])
            ->where('is_approve_finance', 1)
            ->whereNull('transfer_date')
            ->whereIn('status_payment', ["Approved by finance"]);

        return Datatables::of($data)->make(true);
    }

    public function handleUpdateTransfer(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = PengajuanFeeSampling::where('id', $request->id)->first();
            if ($data) {
                $data->is_approve_expanse = 1;
                $data->transfer_date = $request->transfer_date;
                $data->transfered_by = $this->karyawan;
                $data->status_payment = 'PAID';

                $data->save();
            } else {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan', 'status' => 400], 400);
            }

            DB::commit();
            app(NotificationFdlService::class)->sendNotification('Pembayaran Fee Sampling', "Pembayaran fee sampling telah dilakukan pada tanggal {$request->transfer_date}", $data->created_by);
            return response()->json(['success' => true, 'message' => 'Taggal Transfer berhasil di perbarui', 'status' => 200], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage(), 'status' => 500], 500);
        }
    }
}
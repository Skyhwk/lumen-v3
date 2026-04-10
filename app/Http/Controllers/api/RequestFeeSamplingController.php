<?php

namespace App\Http\Controllers\api;

use App\Models\PengajuanFeeSampling;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\PengajuanFeeSamplingDetail;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Database\Eloquent\Collection;

class RequestFeeSamplingController extends Controller
{
    public function index(Request $request)
    {
        $data = PengajuanFeeSampling::with('detail_fee')
            ->where('is_approve_finance', 0)
            ->whereIn('status_payment', ["Waiting", "Rejected by expanse"])
            ->where('is_active', 1)
            ->get()
            ->map(function ($item) {
                // $item->can_approve = $item->detail_fee->every(function ($detail) {
                //     return $detail->approved_at !== null || $detail->rejected_at !== null;
                // });
                $item->can_approve = true;

                return $item;
            });
        return Datatables::of($data)->make(true);
    }

    public function indexDetail(Request $request)
    {
        $data = PengajuanFeeSamplingDetail::where('pengajuan_fee_sampling_id', $request->id);

        return Datatables::of($data)->make(true);
    }

    public function handleRejectDetail(Request $request)
    {
        DB::beginTransaction();
        try {
            $ids = $request->ids ?? [];

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data dipilih',
                    'status' => 400
                ], 400);
            }

            $dataList = PengajuanFeeSamplingDetail::whereIn('id', $ids)->get();

            foreach ($dataList as $data) {
                $data->is_active = 0;
                $data->is_reject = true;
                $data->rejected_by = $this->karyawan;
                $data->alasan_reject = $request->keterangan;
                $data->rejected_at = Carbon::now();
                $data->save();
            }
            
            self::checkAutoApprove($dataList->first()->pengajuan_fee_sampling_id, 'reject', $request->keterangan);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil di reject',
                'status' => 200
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = PengajuanFeeSampling::where('id', $request->id)->first();
            if ($data) {
                $data->is_reject_finance = 1;
                $data->alasan_reject = $request->alasan_reject;
                $data->status_payment = "Rejected";
                $data->is_approve_finance = 0;
                $data->is_active = 0;
                $data->save();

                PengajuanFeeSamplingDetail::where('pengajuan_fee_sampling_id', $data->id)->update([
                    'is_active' => 0,
                    'is_reject' => true,
                    'alasan_reject' => $request->alasan_reject,
                    'rejected_by' => $this->karyawan,
                    'rejected_at' => Carbon::now()
                ]);

            } else {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan', 'status' => 400], 400);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data berhasil di reject', 'status' => 200], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage(), 'status' => 500], 500);
        }
    }

    public function handleApproveDetail(Request $request)
    {
        DB::beginTransaction();
        try {
            $ids = $request->ids ?? [];

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data dipilih',
                    'status' => 400
                ], 400);
            }

            $dataList = PengajuanFeeSamplingDetail::whereIn('id', $ids)->get();

            // 🔥 group by header biar efisien
            $grouped = $dataList->groupBy('pengajuan_fee_sampling_id');

            foreach ($dataList as $data) {
                $data->is_approve = 1;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now();
                $data->save();
            }

            // 🔥 update header sekali per parent
            foreach ($grouped as $headerId => $details) {
                $total = $details->sum('total_fee');

                $header = PengajuanFeeSampling::where('id', $headerId)->first();
                if ($header) {
                    $header->total_fee_approve += $total;
                    $header->save();
                }
            }

            self::checkAutoApprove($dataList->first()->pengajuan_fee_sampling_id, 'approve');

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil di approve',
                'status' => 200
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function handleApprove(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = PengajuanFeeSampling::where('id', $request->id)->first();
            if ($data) {
                $data->is_approve_finance = 1;
                $data->status_payment = "Approved by finance";
                $data->alasan_reject_expanse = null;
                $data->is_reject_expanse = 0;
                $data->save();

                PengajuanFeeSamplingDetail::where('pengajuan_fee_sampling_id', $data->id)->update([
                    'is_approve' => 1,
                    'approved_by' => $this->karyawan,
                    'approved_at' => Carbon::now()
                ]);

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

    private function checkAutoApprove($idHeader, $type, $alasan_reject = null) {
        $header = PengajuanFeeSampling::where('id', $idHeader)->first();

        $remaining = PengajuanFeeSamplingDetail::where('is_approve', 0)->where('is_reject', 0)->where('pengajuan_fee_sampling_id', $idHeader)->count();

        if($remaining == 0){
            if($type =='approve'){
                $header->is_approve_finance = 1;
                $header->status_payment = "Approved by finance";
                $header->alasan_reject_expanse = null;
                $header->is_reject_expanse = 0;
            } else {
                $header->is_reject_finance = 1;
                $header->alasan_reject = $alasan_reject;
                $header->status_payment = "Rejected";
                $header->is_approve_finance = 0;
                $header->is_active = 0;
            }
            $header->save();
        }
    }
}
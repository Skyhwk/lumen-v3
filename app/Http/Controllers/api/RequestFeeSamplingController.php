<?php

namespace App\Http\Controllers\api;

use App\Models\PengajuanFeeSampling;
use App\Models\PengajuanFeeSamplingDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class RequestFeeSamplingController extends Controller
{
    public function index(Request $request)
    {
        $data = PengajuanFeeSampling::with('detail_fee')
            ->where('is_approve_finance', 0)
            ->whereIn('status_payment', ['Waiting', 'Rejected by expanse'])
            ->where('is_active', 1)
            ->get()
            ->map(function ($item) {
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

    public function handleApprove(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = PengajuanFeeSampling::find($request->id);

            if (!$header) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                    'status' => 400
                ], 400);
            }

            PengajuanFeeSamplingDetail::where('pengajuan_fee_sampling_id', $header->id)
                ->where('is_approve', 0)
                ->where('is_reject', 0)
                ->update([
                    'is_approve' => 1,
                    'approved_by' => $this->karyawan,
                    'approved_at' => Carbon::now(),
                ]);

            $this->syncHeaderStatus($header->id);

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

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = PengajuanFeeSampling::find($request->id);

            if (!$header) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                    'status' => 400
                ], 400);
            }

            PengajuanFeeSamplingDetail::where('pengajuan_fee_sampling_id', $header->id)
                ->where('is_approve', 0)
                ->where('is_reject', 0)
                ->update([
                    'is_reject' => 1,
                    'rejected_by' => $this->karyawan,
                    'alasan_reject' => $request->alasan_reject,
                    'rejected_at' => Carbon::now(),
                    'is_active' => 1,
                ]);

            $this->syncHeaderStatus($header->id, $request->alasan_reject);

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

            $details = PengajuanFeeSamplingDetail::whereIn('id', $ids)
                ->where('is_approve', 0)
                ->where('is_reject', 0)
                ->get();

            if ($details->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data sudah diproses atau tidak ditemukan',
                    'status' => 400
                ], 400);
            }

            $headerIds = $details->pluck('pengajuan_fee_sampling_id')->unique()->values();

            foreach ($details as $detail) {
                $detail->is_approve = 1;
                $detail->approved_by = $this->karyawan;
                $detail->approved_at = Carbon::now();
                $detail->save();
            }

            foreach ($headerIds as $headerId) {
                $this->syncHeaderStatus($headerId);
            }

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

            $details = PengajuanFeeSamplingDetail::whereIn('id', $ids)
                ->where('is_approve', 0)
                ->where('is_reject', 0)
                ->get();

            if ($details->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data sudah diproses atau tidak ditemukan',
                    'status' => 400
                ], 400);
            }

            $headerIds = $details->pluck('pengajuan_fee_sampling_id')->unique()->values();

            foreach ($details as $detail) {
                $detail->is_reject = 1;
                $detail->rejected_by = $this->karyawan;
                $detail->alasan_reject = $request->keterangan;
                $detail->rejected_at = Carbon::now();
                $detail->is_active = 1;
                $detail->save();
            }

            foreach ($headerIds as $headerId) {
                $this->syncHeaderStatus($headerId, $request->keterangan);
            }

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

    private function syncHeaderStatus(int $headerId, ?string $alasanReject = null): void
    {
        $header = PengajuanFeeSampling::find($headerId);

        if (!$header) {
            return;
        }

        $details = PengajuanFeeSamplingDetail::where('pengajuan_fee_sampling_id', $headerId)->get();

        $totalDetail = $details->count();
        $approvedCount = $details->where('is_approve', 1)->count();
        $rejectedCount = $details->where('is_reject', 1)->count();
        $pendingCount = $totalDetail - ($approvedCount + $rejectedCount);

        // total approved fee selalu sinkron dari detail
        $header->total_fee_approve = $details
            ->where('is_approve', 1)
            ->sum('total_fee');

        // kalau masih ada pending, jangan finalkan status header
        if ($pendingCount > 0) {
            $header->save();
            return;
        }

        // semua selesai diproses
        if ($approvedCount > 0) {
            // partial approve tetap dianggap approved by finance
            $header->is_approve_finance = 1;
            $header->is_reject_finance = 0;
            $header->status_payment = 'Approved by finance';
            $header->alasan_reject = null;
            $header->alasan_reject_expanse = null;
            $header->is_reject_expanse = 0;
            $header->is_active = 1;
        } else {
            // semua detail reject
            $header->is_approve_finance = 0;
            $header->is_reject_finance = 1;
            $header->status_payment = 'Rejected';
            $header->alasan_reject = $alasanReject;
            $header->is_active = 1;
        }

        $header->save();
    }
}
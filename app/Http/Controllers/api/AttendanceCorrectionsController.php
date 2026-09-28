<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrRequest;
use App\Models\IntilabInternal\AttendanceCorrections;
use App\Models\MasterKaryawan;
use App\Services\Hr\ApprovalService;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\LegacyHrMirror;
use App\Services\Hr\WorkflowStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionsController extends Controller
{
    public function index(Request $request)
    {
        $data = AttendanceCorrections::with('karyawan');

        if ($request->has('status')) {
            $data->where('status', $request->status);
        }

        return datatables()->of($data)->make(true);
    }

    public function tabCounts(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'approved_atasan' => AttendanceCorrections::where('status', WorkflowStatus::APPROVED_ATASAN)->count(),
                'pending' => AttendanceCorrections::where('status', WorkflowStatus::PENDING)->count(),
                'approved_hrd' => AttendanceCorrections::where('status', WorkflowStatus::APPROVED_HRD)->count(),
            ],
        ]);
    }

    public function approve(Request $request)
    {
        $apiId = (int) $request->id;
        $hrResponse = $this->approveOnHrTables($apiId);
        if ($hrResponse !== null) {
            return $hrResponse;
        }

        DB::beginTransaction();
        try {
            $correction = AttendanceCorrections::find($apiId);
            if (!$correction) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $this->applyAbsensiCorrection(
                (int) $correction->employee_id,
                (string) $correction->date,
                (string) $correction->type,
                (string) $correction->time
            );

            $hrdName = $this->karyawan ?? 'HRD';
            $correction->approved_hrd_by = $hrdName;
            $correction->approved_hrd_at = date('Y-m-d H:i:s');
            $correction->status = WorkflowStatus::APPROVED_HRD;
            $correction->save();

            DB::commit();

            return response()->json(['message' => 'Absensi berhasil di-approve & diupdate'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function reject(Request $request)
    {
        $reason = trim((string) ($request->reason ?? $request->keterangan ?? ''));
        if ($reason === '') {
            return response()->json(['message' => 'Alasan penolakan wajib diisi'], 422);
        }

        $apiId = (int) $request->id;
        $hrResponse = $this->rejectOnHrTables($apiId, $reason);
        if ($hrResponse !== null) {
            return $hrResponse;
        }

        try {
            $correction = AttendanceCorrections::find($apiId);
            if (!$correction) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $hrdName = $this->karyawan ?? 'HRD';
            $correction->rejected_hrd_by = $hrdName;
            $correction->rejected_hrd_at = date('Y-m-d H:i:s');
            $correction->reject_hrd_reason = $reason;
            $correction->status = WorkflowStatus::REJECTED_HRD;
            $correction->save();

            return response()->json(['message' => 'Koreksi absensi berhasil di-reject'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null null = lanjut legacy apps saja
     */
    private function approveOnHrTables(int $apiId)
    {
        $hrRequest = HrRequestResolver::findByPortalSliceId(HrRequest::TYPE_ATTENDANCE_CORRECTION, $apiId);
        if (!$hrRequest) {
            return HrTableMode::portalReadsHrTables()
                ? response()->json(['message' => 'Data koreksi absensi tidak ditemukan di HR'], 404)
                : null;
        }

        if ($hrRequest->status !== WorkflowStatus::APPROVED_ATASAN) {
            return response()->json([
                'message' => 'Menunggu persetujuan atasan terlebih dahulu',
            ], 422);
        }

        $approver = MasterKaryawan::find($this->user_id);
        if (!$approver) {
            return response()->json(['message' => 'Data karyawan HRD tidak ditemukan'], 403);
        }

        DB::beginTransaction();
        try {
            $hrRequest->loadMissing('attendanceCorrectionDetail');
            $detail = $hrRequest->attendanceCorrectionDetail;
            if ($detail) {
                $this->applyAbsensiCorrection(
                    (int) $hrRequest->karyawan_id,
                    (string) $detail->correction_date,
                    (string) $detail->correction_type,
                    (string) $detail->correction_time
                );
            }

            app(ApprovalService::class)->approveHrd($hrRequest, $approver, $this->karyawan);
            app(LegacyHrMirror::class)->syncPortalHrdDecision($hrRequest->fresh());

            DB::commit();

            return response()->json(['message' => 'Absensi berhasil di-approve & diupdate'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null null = lanjut legacy apps saja
     */
    private function rejectOnHrTables(int $apiId, string $reason)
    {
        $hrRequest = HrRequestResolver::findByPortalSliceId(HrRequest::TYPE_ATTENDANCE_CORRECTION, $apiId);
        if (!$hrRequest) {
            return HrTableMode::portalReadsHrTables()
                ? response()->json(['message' => 'Data koreksi absensi tidak ditemukan di HR'], 404)
                : null;
        }

        $approver = MasterKaryawan::find($this->user_id);
        if (!$approver) {
            return response()->json(['message' => 'Data karyawan HRD tidak ditemukan'], 403);
        }

        app(ApprovalService::class)->rejectHrd($hrRequest, $approver, $reason, $this->karyawan);
        app(LegacyHrMirror::class)->syncPortalHrdDecision($hrRequest->fresh());

        return response()->json(['message' => 'Koreksi absensi berhasil di-reject'], 200);
    }

    private function applyAbsensiCorrection(int $employeeId, string $date, string $type, string $time): void
    {
        $time = $this->normalizeCorrectionTime($time);
        $conn = config('greatday.produksi_connection', config('database.default', 'mysql'));
        $isCheckIn = strcasecmp(trim($type), 'Check In') === 0
            || strcasecmp(trim($type), 'CheckIn') === 0;

        if ($isCheckIn) {
            $existing = DB::connection($conn)->table('absensi')
                ->where('karyawan_id', $employeeId)
                ->where('tanggal', $date)
                ->where('jam', '<=', '14:00:00')
                ->orderBy('jam')
                ->first();

            if ($existing) {
                DB::connection($conn)->table('absensi')
                    ->where('id', $existing->id)
                    ->update([
                        'jam' => $time,
                        'status' => 'Masuk',
                        'kode_kartu' => null,
                    ]);

                return;
            }

            $this->insertAbsensiRow($conn, $employeeId, $date, $time, 'Masuk');

            return;
        }

        $existing = DB::connection($conn)->table('absensi')
            ->where('karyawan_id', $employeeId)
            ->where('tanggal', $date)
            ->where('jam', '>', '14:00:00')
            ->orderByDesc('jam')
            ->first();

        if ($existing) {
            DB::connection($conn)->table('absensi')
                ->where('id', $existing->id)
                ->update([
                    'jam' => $time,
                    'status' => 'Keluar',
                    'kode_kartu' => null,
                ]);

            return;
        }

        $this->insertAbsensiRow($conn, $employeeId, $date, $time, 'Keluar');
    }

    private function normalizeCorrectionTime(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return $time;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return $time;
    }

    private function insertAbsensiRow(string $conn, int $employeeId, string $date, string $time, string $status): void
    {
        $hariArray = [
            'Sun' => 'Minggu', 'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu',
            'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu',
        ];
        $hari = $hariArray[date('D', strtotime($date))] ?? 'Tidak di ketahui';

        DB::connection($conn)->table('absensi')->insert([
            'karyawan_id' => $employeeId,
            'hari' => $hari,
            'tanggal' => $date,
            'jam' => $time,
            'status' => $status,
            'kode_kartu' => null,
            'kode_mesin' => null,
        ]);
    }
}

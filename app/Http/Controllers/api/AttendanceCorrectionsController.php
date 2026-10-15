<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrRequest;
use App\Models\IntilabInternal\AttendanceCorrections;
use App\Models\MasterKaryawan;
use App\Services\Greatday\DynamicApprovalChainService;
use App\Services\Hr\ApprovalService;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\LegacyHrMirror;
use App\Services\Hr\WorkflowStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionsController extends Controller
{
    /** @var array<int, list<int>> */
    private $atasanChainCache = [];

    public function index(Request $request)
    {
        $data = AttendanceCorrections::with('karyawan');

        if ($request->has('status')) {
            $data->where('status', $request->status);
        }

        return datatables()->of($data)
            ->addColumn('atasan_steps', function ($row) {
                return $this->atasanStepsForRow($row);
            })
            ->make(true);
    }

    public function tabCounts(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'approved_atasan' => AttendanceCorrections::where('status', WorkflowStatus::APPROVED_ATASAN)->count(),
                'pending' => AttendanceCorrections::where('status', WorkflowStatus::PENDING)->count(),
                'approved_hrd' => AttendanceCorrections::where('status', WorkflowStatus::APPROVED_HRD)->count(),
                'rejected_hrd' => AttendanceCorrections::where('status', WorkflowStatus::REJECTED_HRD)->count(),
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

    private function atasanStepsForRow($row): array
    {
        $employee = $row->relationLoaded('karyawan') ? $row->karyawan : null;
        if (!$employee && $row->employee_id) {
            $employee = MasterKaryawan::query()->find($row->employee_id);
        }

        $ids = $employee ? $this->approverIdsFor($employee) : [];
        if ($ids === []) {
            return [$this->singleAtasanStep($row)];
        }

        $names = MasterKaryawan::query()->whereIn('id', $ids)->pluck('nama_lengkap', 'id');
        $status = (string) $row->status;
        $rejected = $status === WorkflowStatus::REJECTED_ATASAN || $row->rejected_atasan_by;
        $done = !$rejected && (
            $row->approved_atasan_by
            || in_array($status, [
                WorkflowStatus::APPROVED_ATASAN,
                WorkflowStatus::APPROVED_HRD,
                WorkflowStatus::REJECTED_HRD,
            ], true)
        );

        $rejectIndex = 0;
        if ($rejected) {
            $rejectName = trim((string) $row->rejected_atasan_by);
            foreach ($ids as $index => $id) {
                $name = trim((string) ($names[$id] ?? ''));
                if ($rejectName !== '' && strcasecmp($name, $rejectName) === 0) {
                    $rejectIndex = $index;
                    break;
                }
            }
        }

        $steps = [];
        $total = count($ids);
        $last = $total - 1;

        foreach ($ids as $index => $id) {
            $name = $names[$id] ?? null;
            $state = 'waiting';
            $actor = $name;
            $actedAt = null;
            $note = null;

            if ($done) {
                $state = 'done';
                if ($index === $last && $row->approved_atasan_by) {
                    $actor = $row->approved_atasan_by;
                    $actedAt = $row->approved_atasan_at;
                }
            } elseif ($rejected) {
                if ($index < $rejectIndex) {
                    $state = 'done';
                } elseif ($index === $rejectIndex) {
                    $state = 'rejected';
                    $actor = $row->rejected_atasan_by ?: $name;
                    $actedAt = $row->rejected_atasan_at;
                    $note = $row->reject_atasan_reason;
                }
            } elseif ($index === 0) {
                $state = 'active';
            }

            $steps[] = [
                'key' => 'atasan-' . ($index + 1),
                'title' => $total > 1 ? 'Persetujuan atasan ' . ($index + 1) : 'Persetujuan Atasan',
                'state' => $state,
                'actor' => $actor,
                'acted_at' => $actedAt ? (string) $actedAt : null,
                'note' => $note,
            ];
        }

        return $steps;
    }

    private function approverIdsFor(MasterKaryawan $employee): array
    {
        $id = (int) $employee->id;
        if (!array_key_exists($id, $this->atasanChainCache)) {
            $this->atasanChainCache[$id] = app(DynamicApprovalChainService::class)->orderedApproverIds($employee);
        }

        return $this->atasanChainCache[$id];
    }

    private function singleAtasanStep($row): array
    {
        $status = (string) $row->status;
        $state = 'waiting';
        $actor = null;
        $actedAt = null;
        $note = null;

        if ($status === WorkflowStatus::REJECTED_ATASAN || $row->rejected_atasan_by) {
            $state = 'rejected';
            $actor = $row->rejected_atasan_by;
            $actedAt = $row->rejected_atasan_at;
            $note = $row->reject_atasan_reason;
        } elseif (
            $row->approved_atasan_by
            || in_array($status, [
                WorkflowStatus::APPROVED_ATASAN,
                WorkflowStatus::APPROVED_HRD,
                WorkflowStatus::REJECTED_HRD,
            ], true)
        ) {
            $state = 'done';
            $actor = $row->approved_atasan_by;
            $actedAt = $row->approved_atasan_at;
        } elseif ($status === WorkflowStatus::PENDING || $status === '') {
            $state = 'active';
        }

        return [
            'key' => 'atasan',
            'title' => 'Persetujuan Atasan',
            'state' => $state,
            'actor' => $actor,
            'acted_at' => $actedAt ? (string) $actedAt : null,
            'note' => $note,
        ];
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

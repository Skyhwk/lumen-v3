<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\SalaryAdjustmentApprovalToken;
use App\Models\SalaryAdjustmentRequest;
use App\Services\EmployeeAdjustmentTypeRegistry;
use App\Services\SalaryAdjustmentApprovalService;
use App\Services\SalaryAdjustmentEmailService;
use App\Services\SalaryAdjustmentWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Http\Controllers\api\Concerns\ServesAtsClientSideList;

class ApprovalPenyesuaianController extends Controller
{
    use ServesAtsClientSideList;

    /**
     * Get counts for each tab in Approval Permohonan Penyesuaian Karyawan.
     */
    public function counts(Request $request)
    {
        $year = $request->input('year') ?? $request->input('periode');

        $base = DB::connection('mysql')
            ->table('salary_adjustment_requests as sar')
            ->where('sar.is_active', true)
            ->when($year, function ($q) use ($year) {
                return $q->whereYear('sar.created_at', $year);
            });

        $waitingCount = (clone $base)
            ->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK)
            ->count();

        $approvedCount = (clone $base)
            ->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_COMPLETED)
            ->count();

        $rejectedCount = (clone $base)
            ->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_REJECTED)
            ->where(function ($q) {
                $q->where('sar.rejected_stage', SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK)
                    ->orWhereNotNull('sar.bapak_rejected_at');
            })
            ->count();

        $allCount = $waitingCount + $approvedCount + $rejectedCount;

        return response()->json([
            'status' => 'success',
            'data' => [
                'counts' => [
                    'waiting'  => $waitingCount,
                    'approved' => $approvedCount,
                    'rejected' => $rejectedCount,
                    'all'      => $allCount,
                ],
            ],
            'message' => 'Counts retrieved successfully',
        ], 200);
    }

    /**
     * DataTables list for Approval Permohonan Penyesuaian Karyawan.
     * Tabs: waiting | approved | rejected | all
     */
    public function index(Request $request)
    {
        $year = $request->input('year') ?? $request->input('periode');
        $tab = $request->input('tab', 'waiting');

        $query = DB::connection('mysql')
            ->table('salary_adjustment_requests as sar')
            ->leftJoin('master_karyawan as karyawan', 'sar.employee_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('master_karyawan as manager', 'sar.requested_by_id', '=', 'manager.id')
            ->where('sar.is_active', true)
            ->when($year, function ($q) use ($year) {
                return $q->whereYear('sar.created_at', $year);
            })
            ->select(
                'sar.id',
                'sar.no_document',
                'sar.request_type',
                'sar.employee_id',
                'sar.jabatan',
                'sar.current_gaji_pokok',
                'sar.current_tunjangan_kerja',
                'sar.adjustment_gaji_pokok',
                'sar.adjustment_tunjangan',
                'sar.hrd_final_adjustment_gaji_pokok',
                'sar.hrd_final_adjustment_tunjangan',
                'sar.requested_gaji_pokok',
                'sar.requested_tunjangan_kerja',
                'sar.bulan_efektif',
                'sar.tanggal_efektif',
                'sar.status',
                'sar.rejected_stage',
                'sar.reject_reason',
                'sar.created_by',
                'sar.created_at',
                'sar.updated_at',
                'karyawan.nama_lengkap as nama_karyawan',
                'karyawan.nik_karyawan',
                'd.nama_divisi',
                'manager.nama_lengkap as manager_nama'
            )
            ->orderByDesc('sar.id');

        if ($tab === 'waiting') {
            $query->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK);
        } elseif ($tab === 'approved') {
            $query->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_COMPLETED);
        } elseif ($tab === 'rejected') {
            $query->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_REJECTED)
                ->where(function ($q) {
                    $q->where('sar.rejected_stage', SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK)
                        ->orWhereNotNull('sar.bapak_rejected_at');
                });
        } elseif ($tab === 'all') {
            $query->where(function ($q) {
                $q->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK)
                    ->orWhere('sar.status', SalaryAdjustmentWorkflowService::STATUS_COMPLETED)
                    ->orWhere(function ($sub) {
                        $sub->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_REJECTED)
                            ->where(function ($rej) {
                                $rej->where('sar.rejected_stage', SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK)
                                    ->orWhereNotNull('sar.bapak_rejected_at');
                            });
                    });
            });
        }

        $dt = DataTables::of($query)
            ->addColumn('request_type_label', function ($row) {
                return EmployeeAdjustmentTypeRegistry::label($row->request_type);
            })
            ->addColumn('gaji_pokok_saat_ini', function ($row) {
                return (float) ($row->current_gaji_pokok ?? 0);
            })
            ->addColumn('tunjangan_saat_ini', function ($row) {
                return (float) ($row->current_tunjangan_kerja ?? 0);
            })
            ->addColumn('penyesuaian_gaji_pokok', function ($row) {
                $val = $row->hrd_final_adjustment_gaji_pokok ?? $row->adjustment_gaji_pokok ?? 0;
                return (float) $val;
            })
            ->addColumn('penyesuaian_tunjangan', function ($row) {
                $val = $row->hrd_final_adjustment_tunjangan ?? $row->adjustment_tunjangan ?? 0;
                return (float) $val;
            })
            ->addColumn('total_penyesuaian', function ($row) {
                $gp = $row->hrd_final_adjustment_gaji_pokok ?? $row->adjustment_gaji_pokok ?? 0;
                $tj = $row->hrd_final_adjustment_tunjangan ?? $row->adjustment_tunjangan ?? 0;
                return (float) $gp + (float) $tj;
            })
            ->addColumn('gaji_pokok_akhir', function ($row) {
                return (float) ($row->requested_gaji_pokok ?? 0);
            })
            ->addColumn('tunjangan_akhir', function ($row) {
                return (float) ($row->requested_tunjangan_kerja ?? 0);
            })
            ->addColumn('tanggal_efektif', function ($row) {
                if (!empty($row->tanggal_efektif)) {
                    return Carbon::parse($row->tanggal_efektif)->format('Y-m-d');
                }
                return $row->bulan_efektif ?: null;
            })
            ->addColumn('tanggal_pengajuan', function ($row) {
                return $row->created_at ? Carbon::parse($row->created_at)->format('Y-m-d H:i:s') : null;
            })
            ->addColumn('request_by', function ($row) {
                return $row->manager_nama ?: ($row->created_by ?: '-');
            })
            ->addColumn('approval_status', function ($row) {
                $canDecide = ($row->status === SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK);

                $label = SalaryAdjustmentWorkflowService::statusLabel($row->status);

                return [
                    'code' => $row->status,
                    'label' => $label,
                    'can_decide' => $canDecide,
                ];
            })
            ->addColumn('reject_reason', function ($row) {
                return $row->reject_reason ?: '-';
            });

        if ($response = $this->serveAtsClientSideList($dt)) {
            return $response;
        }

        return $dt->make(true);
    }

    /**
     * Setujui permohonan penyesuaian karyawan.
     */
    public function approve(Request $request)
    {
        $id = (int) $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'ID permohonan tidak ditemukan'], 400);
        }

        $record = SalaryAdjustmentRequest::find($id);
        if (!$record || !$record->is_active) {
            return response()->json(['message' => 'Data permohonan tidak ditemukan'], 404);
        }

        if ($record->status !== SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK) {
            return response()->json(['message' => 'Permohonan ini tidak dalam status menunggu approval.'], 422);
        }

        $role = SalaryAdjustmentEmailService::ROLE_BAPAK;

        $tokenRecord = SalaryAdjustmentApprovalToken::where('request_id', $record->id)
            ->where('approver_role', $role)
            ->where('is_active', true)
            ->first();

        if (!$tokenRecord) {
            $token = bin2hex(random_bytes(32));
            $tokenRecord = SalaryAdjustmentApprovalToken::create([
                'request_id' => $record->id,
                'approver_role' => $role,
                'token' => $token,
                'email_to' => 'direct-web-approval',
                'is_active' => true,
                'created_by' => 'Administrator',
            ]);
        }

        try {
            $service = app(SalaryAdjustmentApprovalService::class);
            $result = $service->decide($tokenRecord->token, 'approve');

            if (($result['result'] ?? '') === 'invalid' || ($result['result'] ?? '') === 'unavailable') {
                return response()->json(['message' => $result['message'] ?? 'Gagal memproses approval'], 422);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Permohonan penyesuaian karyawan berhasil disetujui.',
                'data' => $result,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Tolak permohonan penyesuaian karyawan.
     */
    public function reject(Request $request)
    {
        $id = (int) $request->input('id');
        if (!$id) {
            return response()->json(['message' => 'ID permohonan tidak ditemukan'], 400);
        }

        $rejectReason = trim((string) ($request->input('reject_reason') ?? $request->input('reason') ?? ''));
        if ($rejectReason === '') {
            return response()->json(['message' => 'Alasan penolakan wajib diisi.'], 422);
        }

        $record = SalaryAdjustmentRequest::find($id);
        if (!$record || !$record->is_active) {
            return response()->json(['message' => 'Data permohonan tidak ditemukan'], 404);
        }

        if ($record->status !== SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK) {
            return response()->json(['message' => 'Permohonan ini tidak dalam status menunggu approval.'], 422);
        }

        $role = SalaryAdjustmentEmailService::ROLE_BAPAK;

        $tokenRecord = SalaryAdjustmentApprovalToken::where('request_id', $record->id)
            ->where('approver_role', $role)
            ->where('is_active', true)
            ->first();

        if (!$tokenRecord) {
            $token = bin2hex(random_bytes(32));
            $tokenRecord = SalaryAdjustmentApprovalToken::create([
                'request_id' => $record->id,
                'approver_role' => $role,
                'token' => $token,
                'email_to' => 'direct-web-approval',
                'is_active' => true,
                'created_by' => 'Administrator',
            ]);
        }

        try {
            $service = app(SalaryAdjustmentApprovalService::class);
            $result = $service->decide($tokenRecord->token, 'reject', $rejectReason);

            if (($result['result'] ?? '') === 'invalid' || ($result['result'] ?? '') === 'unavailable') {
                return response()->json(['message' => $result['message'] ?? 'Gagal menolak permohonan'], 422);
            }

            return response()->json([
                'status' => 200,
                'message' => 'Permohonan penyesuaian karyawan berhasil ditolak.',
                'data' => $result,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}

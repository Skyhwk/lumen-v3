<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\SalaryAdjustmentRequest;
use App\Services\EmployeeAdjustmentTypeRegistry;
use App\Services\SalaryAdjustmentEmailService;
use App\Services\SalaryAdjustmentEvaluationService;
use App\Services\SalaryAdjustmentLogService;
use App\Services\SalaryAdjustmentWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class PenyesuaianGajiFinanceController extends Controller
{
    public function tabCounts(Request $request)
    {
        $periode = $request->periode ?? date('Y');

        $base = DB::connection('mysql')
            ->table('salary_adjustment_requests')
            ->where('is_active', true)
            ->whereYear('created_at', $periode);

        return response()->json([
            'success' => true,
            'data' => [
                'waiting_review' => (clone $base)
                    ->where('status', SalaryAdjustmentWorkflowService::STATUS_FINANCE_REVIEW)
                    ->count(),
                'processed' => (clone $base)
                    ->whereNotNull('finance_approved_at')
                    ->count(),
            ],
        ]);
    }

    public function indexWaitingReview(Request $request)
    {
        return $this->indexByScope($request, 'waiting_review');
    }

    public function indexProcessed(Request $request)
    {
        return $this->indexByScope($request, 'processed');
    }

    public function show(Request $request)
    {
        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $bundle = (new SalaryAdjustmentEvaluationService())->buildBundle($record);

        return response()->json([
            'success' => true,
            'data' => $bundle,
        ]);
    }

    public function approve(Request $request)
    {
        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if ($record->status !== SalaryAdjustmentWorkflowService::STATUS_FINANCE_REVIEW) {
            return response()->json(['success' => false, 'message' => 'Permohonan tidak dapat disetujui pada status ini'], 400);
        }

        $adjustmentGaji = $this->parseAmount(
            $request->adjustment_gaji_pokok ?? $record->adjustment_gaji_pokok
        );
        $adjustmentTunjangan = $this->parseAmount(
            $request->adjustment_tunjangan ?? $record->adjustment_tunjangan
        );
        if ($adjustmentGaji <= 0 && $adjustmentTunjangan <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal salah satu penyesuaian gaji pokok atau tunjangan harus diisi',
            ], 400);
        }

        DB::connection('mysql')->beginTransaction();
        try {
            $from = $record->status;
            $financeNotes = trim((string) ($request->finance_final_adjustment_notes ?? ''));
            $notes = trim((string) ($request->notes ?? '')) ?: 'Finance menyetujui permohonan penyesuaian gaji';

            SalaryAdjustmentEvaluationService::ensureHrdFinalSnapshot($record);

            $hrdSnapshot = [
                'adjustment_gaji_pokok' => (float) ($record->hrd_final_adjustment_gaji_pokok ?? 0),
                'adjustment_tunjangan' => (float) ($record->hrd_final_adjustment_tunjangan ?? 0),
                'requested_gaji_pokok' => (float) ($record->hrd_final_requested_gaji_pokok ?? 0),
                'requested_tunjangan_kerja' => (float) ($record->hrd_final_requested_tunjangan_kerja ?? 0),
            ];

            $currentGaji = (float) $record->current_gaji_pokok;
            $currentTunjangan = (float) $record->current_tunjangan_kerja;

            $record->adjustment_gaji_pokok = $adjustmentGaji > 0 ? $adjustmentGaji : null;
            $record->adjustment_tunjangan = $adjustmentTunjangan > 0 ? $adjustmentTunjangan : null;
            $record->requested_gaji_pokok = $currentGaji + max(0, $adjustmentGaji);
            $record->requested_tunjangan_kerja = $currentTunjangan + max(0, $adjustmentTunjangan);
            $record->finance_final_adjustment_notes = $financeNotes !== '' ? $financeNotes : null;
            $record->status = SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_IBU;
            $record->finance_approved_by = $this->karyawan;
            $record->finance_approved_at = Carbon::now();
            $record->updated_by = $this->karyawan;
            $record->save();

            $financeSnapshot = [
                'adjustment_gaji_pokok' => (float) ($record->adjustment_gaji_pokok ?? 0),
                'adjustment_tunjangan' => (float) ($record->adjustment_tunjangan ?? 0),
                'requested_gaji_pokok' => (float) $record->requested_gaji_pokok,
                'requested_tunjangan_kerja' => (float) $record->requested_tunjangan_kerja,
            ];

            $changedByFinance = round($hrdSnapshot['adjustment_gaji_pokok'], 2) !== round($financeSnapshot['adjustment_gaji_pokok'], 2)
                || round($hrdSnapshot['adjustment_tunjangan'], 2) !== round($financeSnapshot['adjustment_tunjangan'], 2)
                || round($hrdSnapshot['requested_gaji_pokok'], 2) !== round($financeSnapshot['requested_gaji_pokok'], 2)
                || round($hrdSnapshot['requested_tunjangan_kerja'], 2) !== round($financeSnapshot['requested_tunjangan_kerja'], 2);
            if ($changedByFinance && $notes === 'Finance menyetujui permohonan penyesuaian gaji') {
                $notes = 'Finance menyetujui dengan penyesuaian nominal kebijakan keuangan';
            }

            SalaryAdjustmentLogService::log(
                $record->id,
                $from,
                $record->status,
                'finance_approve',
                $this->user_id,
                $this->karyawan,
                $notes,
                [
                    'hrd_final' => $hrdSnapshot,
                    'finance_final' => $financeSnapshot,
                    'changed_by_finance' => $changedByFinance,
                    'finance_final_adjustment_notes' => $financeNotes !== '' ? $financeNotes : null,
                ]
            );

            DB::connection('mysql')->commit();

            $emailSent = (new SalaryAdjustmentEmailService())->sendForRole(
                $record->fresh(),
                SalaryAdjustmentEmailService::ROLE_IBU,
                $this->karyawan ?: 'Finance'
            );

            return response()->json([
                'success' => true,
                'message' => $emailSent
                    ? 'Permohonan berhasil disetujui Finance. Email Waiting Approval terkirim.'
                    : 'Permohonan berhasil disetujui Finance. Email Waiting Approval gagal dikirim — periksa konfigurasi email Waiting Approval.',
                'email_sent' => $emailSent,
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request)
    {
        $reason = trim((string) ($request->reject_reason ?? $request->keterangan ?? ''));
        if ($reason === '') {
            return response()->json(['success' => false, 'message' => 'Alasan penolakan wajib diisi'], 422);
        }

        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if ($record->status !== SalaryAdjustmentWorkflowService::STATUS_FINANCE_REVIEW) {
            return response()->json(['success' => false, 'message' => 'Permohonan tidak dapat ditolak pada status ini'], 400);
        }

        DB::connection('mysql')->beginTransaction();
        try {
            $from = $record->status;

            SalaryAdjustmentEvaluationService::ensureHrdFinalSnapshot($record);

            $record->status = SalaryAdjustmentWorkflowService::STATUS_FINANCE_RETURNED;
            $record->finance_return_reason = $reason;
            $record->finance_rejected_by = $this->karyawan;
            $record->finance_rejected_at = Carbon::now();
            $record->updated_by = $this->karyawan;
            $record->save();

            SalaryAdjustmentLogService::log(
                $record->id,
                $from,
                $record->status,
                'finance_return',
                $this->user_id,
                $this->karyawan,
                $reason,
                [
                    'hrd_final' => [
                        'adjustment_gaji_pokok' => (float) ($record->hrd_final_adjustment_gaji_pokok ?? 0),
                        'adjustment_tunjangan' => (float) ($record->hrd_final_adjustment_tunjangan ?? 0),
                        'requested_gaji_pokok' => (float) ($record->hrd_final_requested_gaji_pokok ?? 0),
                        'requested_tunjangan_kerja' => (float) ($record->hrd_final_requested_tunjangan_kerja ?? 0),
                    ],
                ]
            );

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan dikembalikan ke HRD untuk banding',
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function indexByScope(Request $request, string $scope)
    {
        $periode = $request->periode ?? date('Y');

        $query = DB::connection('mysql')
            ->table('salary_adjustment_requests as sar')
            ->leftJoin('master_karyawan as karyawan', 'sar.employee_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('master_karyawan as manager', 'sar.requested_by_id', '=', 'manager.id')
            ->leftJoin('salary_adjustment_kpi as sk', 'sar.id', '=', 'sk.request_id')
            ->leftJoin('salary_adjustment_assessments as sa', 'sar.id', '=', 'sa.request_id')
            ->where('sar.is_active', true)
            ->whereYear('sar.created_at', $periode)
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
                'sar.requested_gaji_pokok',
                'sar.requested_tunjangan_kerja',
                'sar.bulan_efektif',
                'sar.status',
                'sar.finance_approved_at',
                'sar.finance_rejected_at',
                'sar.rejected_stage',
                'sar.created_at',
                'karyawan.nama_lengkap',
                'd.nama_divisi',
                'manager.nama_lengkap as manager_nama',
                'sk.total_score_avg as kpi_score',
                'sa.total_score as assessment_score'
            )
            ->orderByDesc('sar.id');

        if ($scope === 'waiting_review') {
            $query->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_FINANCE_REVIEW);
        } else {
            $query->whereNotNull('sar.finance_approved_at');
        }

        return Datatables::of($query)
            ->addColumn('request_type_label', function ($row) {
                return EmployeeAdjustmentTypeRegistry::label(
                    $row->request_type ?? EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI
                );
            })
            ->addColumn('status_label', function ($row) {
                if ($row->finance_approved_at) {
                    return 'Disetujui Finance';
                }
                if ($row->rejected_stage === SalaryAdjustmentWorkflowService::STATUS_FINANCE_REVIEW) {
                    return 'Ditolak Finance';
                }

                return SalaryAdjustmentWorkflowService::statusLabel($row->status);
            })
            ->addColumn('can_review', fn ($row) => $row->status === SalaryAdjustmentWorkflowService::STATUS_FINANCE_REVIEW)
            ->filterColumn('no_document', function ($query, $keyword) {
                $query->where('sar.no_document', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_lengkap', function ($query, $keyword) {
                $query->where('karyawan.nama_lengkap', 'like', "%{$keyword}%");
            })
            ->filterColumn('manager_nama', function ($query, $keyword) {
                $query->where('manager.nama_lengkap', 'like', "%{$keyword}%");
            })
            ->filterColumn('request_type_label', function ($query, $keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('sar.request_type', 'like', "%{$keyword}%");
                    foreach (EmployeeAdjustmentTypeRegistry::all() as $type => $definition) {
                        if (stripos($definition['label'], $keyword) !== false) {
                            $sub->orWhere('sar.request_type', $type);
                        }
                    }
                });
            })
            ->filterColumn('jabatan', function ($query, $keyword) {
                $query->where('sar.jabatan', 'like', "%{$keyword}%");
            })
            ->filterColumn('current_gaji_pokok', function ($query, $keyword) {
                $query->where('sar.current_gaji_pokok', 'like', "%{$keyword}%");
            })
            ->filterColumn('current_tunjangan_kerja', function ($query, $keyword) {
                $query->where('sar.current_tunjangan_kerja', 'like', "%{$keyword}%");
            })
            ->filterColumn('requested_gaji_pokok', function ($query, $keyword) {
                $query->where('sar.requested_gaji_pokok', 'like', "%{$keyword}%");
            })
            ->filterColumn('requested_tunjangan_kerja', function ($query, $keyword) {
                $query->where('sar.requested_tunjangan_kerja', 'like', "%{$keyword}%");
            })
            ->filterColumn('adjustment_gaji_pokok', function ($query, $keyword) {
                $query->where('sar.adjustment_gaji_pokok', 'like', "%{$keyword}%");
            })
            ->filterColumn('adjustment_tunjangan', function ($query, $keyword) {
                $query->where('sar.adjustment_tunjangan', 'like', "%{$keyword}%");
            })
            ->filterColumn('bulan_efektif', function ($query, $keyword) {
                $query->where('sar.bulan_efektif', 'like', "%{$keyword}%");
            })
            ->filterColumn('kpi_score', function ($query, $keyword) {
                $query->where('sk.total_score_avg', 'like', "%{$keyword}%");
            })
            ->filterColumn('assessment_score', function ($query, $keyword) {
                $query->where('sa.total_score', 'like', "%{$keyword}%");
            })
            ->filterColumn('status_label', function ($query, $keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('sar.status', 'like', "%{$keyword}%");
                    if (stripos('Disetujui Finance', $keyword) !== false) {
                        $sub->orWhereNotNull('sar.finance_approved_at');
                    }
                    if (stripos('Ditolak Finance', $keyword) !== false) {
                        $sub->orWhere(function ($inner) {
                            $inner->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_REJECTED)
                                ->where('sar.rejected_stage', SalaryAdjustmentWorkflowService::STATUS_FINANCE_REVIEW);
                        });
                    }
                });
            })
            ->filterColumn('finance_approved_at', function ($query, $keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('sar.finance_approved_at', 'like', "%{$keyword}%")
                        ->orWhere('sar.finance_rejected_at', 'like', "%{$keyword}%");
                });
            })
            ->make(true);
    }

    private function parseAmount($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return max(0, (float) $value);
        }

        $normalized = preg_replace('/[^\d]/', '', (string) $value);

        return max(0, (float) ($normalized ?: 0));
    }
}

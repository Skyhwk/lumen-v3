<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\MasterSallary;
use App\Models\SalaryAdjustmentRequest;
use App\Services\GetBawahan;
use App\Services\KaryawanProfileService;
use App\Services\SalaryAdjustmentEvaluationService;
use App\Services\SalaryAdjustmentKpiService;
use App\Services\SalaryAdjustmentLogService;
use App\Services\SalaryAdjustmentWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class PermohonanPenyesuaianGajiController extends Controller
{
    public function getKpiCriteria()
    {
        $criteria = (new SalaryAdjustmentKpiService())->getActiveCriteria();

        return response()->json([
            'success' => true,
            'data' => $criteria,
        ]);
    }

    public function getBawahanList()
    {
        if (!SalaryAdjustmentWorkflowService::isManagerGrade($this->grade)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Manager yang dapat mengakses daftar bawahan',
            ], 403);
        }

        $bawahan = GetBawahan::where('id', $this->user_id)->get()
            ->filter(function ($row) {
                return (int) $row->id !== (int) $this->user_id;
            })
            ->values()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'nama_lengkap' => $row->nama_lengkap,
                    'grade' => $row->grade,
                    'nik_karyawan' => $row->nik_karyawan,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $bawahan,
        ]);
    }

    public function getEmployeeDetail(Request $request)
    {
        $employeeId = (int) $request->employee_id;
        if (!$employeeId) {
            return response()->json(['success' => false, 'message' => 'Karyawan wajib dipilih'], 400);
        }

        if (!$this->isAllowedSubordinate($employeeId)) {
            return response()->json(['success' => false, 'message' => 'Karyawan bukan bawahan Anda'], 403);
        }

        $employee = MasterKaryawan::with('jabatan')->where('id', $employeeId)->where('is_active', true)->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Data karyawan tidak ditemukan'], 404);
        }

        $salary = MasterSallary::where('is_active', true)
            ->where(function ($q) use ($employee) {
                $q->where('nik_karyawan', $employee->nik_karyawan)
                    ->orWhere('karyawan', $employee->nama_lengkap);
            })
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'employee_id' => $employee->id,
                'nama_lengkap' => $employee->nama_lengkap,
                'jabatan' => KaryawanProfileService::resolveJabatan($employee),
                'nik_karyawan' => $employee->nik_karyawan,
                'current_gaji_pokok' => (float) ($salary->gaji_pokok ?? 0),
                'current_tunjangan_kerja' => (float) ($salary->tunjangan_kerja ?? 0),
            ],
        ]);
    }

    public function tabCounts(Request $request)
    {
        $periode = $request->periode ?? date('Y');
        $query = $this->baseManagerQuery($periode);

        $counts = [
            SalaryAdjustmentWorkflowService::MANAGER_TAB_WAITING => (clone $query)
                ->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_SUBMITTED)
                ->count(),
            SalaryAdjustmentWorkflowService::MANAGER_TAB_IN_PROGRESS => (clone $query)
                ->whereIn('sar.status', SalaryAdjustmentWorkflowService::statusesForManagerTab(
                    SalaryAdjustmentWorkflowService::MANAGER_TAB_IN_PROGRESS
                ))
                ->count(),
            SalaryAdjustmentWorkflowService::MANAGER_TAB_COMPLETED => (clone $query)
                ->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_COMPLETED)
                ->count(),
            SalaryAdjustmentWorkflowService::MANAGER_TAB_REJECTED => (clone $query)
                ->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_REJECTED)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $counts,
        ]);
    }

    public function indexWaiting(Request $request)
    {
        return $this->indexByManagerTab($request, SalaryAdjustmentWorkflowService::MANAGER_TAB_WAITING);
    }

    public function indexInProgress(Request $request)
    {
        return $this->indexByManagerTab($request, SalaryAdjustmentWorkflowService::MANAGER_TAB_IN_PROGRESS);
    }

    public function indexCompleted(Request $request)
    {
        return $this->indexByManagerTab($request, SalaryAdjustmentWorkflowService::MANAGER_TAB_COMPLETED);
    }

    public function indexRejected(Request $request)
    {
        return $this->indexByManagerTab($request, SalaryAdjustmentWorkflowService::MANAGER_TAB_REJECTED);
    }

    public function store(Request $request)
    {
        if (!SalaryAdjustmentWorkflowService::isManagerGrade($this->grade)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Manager yang dapat membuat permohonan penyesuaian gaji',
            ], 403);
        }

        $employeeId = (int) $request->employee_id;
        if (!$employeeId) {
            return response()->json(['success' => false, 'message' => 'Nama bawahan wajib dipilih'], 400);
        }

        if ((int) $employeeId === (int) $this->user_id) {
            return response()->json(['success' => false, 'message' => 'Manager tidak dapat mengajukan untuk diri sendiri'], 400);
        }

        if (!$this->isAllowedSubordinate($employeeId)) {
            return response()->json(['success' => false, 'message' => 'Karyawan bukan bawahan Anda'], 403);
        }

        $adjustmentGaji = $this->parseAmount($request->adjustment_gaji_pokok);
        $adjustmentTunjangan = $this->parseAmount($request->adjustment_tunjangan);
        if ($adjustmentGaji <= 0 && $adjustmentTunjangan <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal salah satu penyesuaian gaji pokok atau tunjangan harus diisi',
            ], 400);
        }

        $bulanEfektif = trim((string) ($request->bulan_efektif ?? ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $bulanEfektif)) {
            return response()->json(['success' => false, 'message' => 'Mulai berlaku wajib diisi (format YYYY-MM)'], 400);
        }

        $catatan = trim((string) ($request->catatan_tambahan ?? ''));

        $kpiService = new SalaryAdjustmentKpiService();
        $kpiItems = $request->input('kpi.items', $request->input('kpi_items', []));
        $kpiError = $kpiService->validatePayload(
            is_array($kpiItems) ? $kpiItems : [],
            $request->input('kpi.summary', $request->input('kpi_summary')),
            $request->input('kpi.strengths', $request->input('kpi_strengths')),
            $request->input('kpi.improvements', $request->input('kpi_improvements'))
        );
        if ($kpiError) {
            return response()->json(['success' => false, 'message' => $kpiError], 400);
        }

        $employee = MasterKaryawan::with('jabatan')->where('id', $employeeId)->where('is_active', true)->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Data karyawan tidak ditemukan'], 404);
        }

        $salary = MasterSallary::where('is_active', true)
            ->where(function ($q) use ($employee) {
                $q->where('nik_karyawan', $employee->nik_karyawan)
                    ->orWhere('karyawan', $employee->nama_lengkap);
            })
            ->orderByDesc('id')
            ->first();

        $currentGaji = (float) ($salary->gaji_pokok ?? 0);
        $currentTunjangan = (float) ($salary->tunjangan_kerja ?? 0);
        $namaJabatan = KaryawanProfileService::resolveJabatan($employee);

        DB::connection('mysql')->beginTransaction();
        try {
            $record = SalaryAdjustmentRequest::create([
                'no_document' => $this->generateDocumentNumber(),
                'employee_id' => $employeeId,
                'requested_by_id' => $this->user_id,
                'jabatan' => $namaJabatan,
                'current_gaji_pokok' => $currentGaji,
                'current_tunjangan_kerja' => $currentTunjangan,
                'adjustment_gaji_pokok' => $adjustmentGaji > 0 ? $adjustmentGaji : null,
                'adjustment_tunjangan' => $adjustmentTunjangan > 0 ? $adjustmentTunjangan : null,
                'requested_gaji_pokok' => $currentGaji + max(0, $adjustmentGaji),
                'requested_tunjangan_kerja' => $currentTunjangan + max(0, $adjustmentTunjangan),
                'submitted_adjustment_gaji_pokok' => $adjustmentGaji > 0 ? $adjustmentGaji : null,
                'submitted_adjustment_tunjangan' => $adjustmentTunjangan > 0 ? $adjustmentTunjangan : null,
                'submitted_requested_gaji_pokok' => $currentGaji + max(0, $adjustmentGaji),
                'submitted_requested_tunjangan_kerja' => $currentTunjangan + max(0, $adjustmentTunjangan),
                'bulan_efektif' => $bulanEfektif,
                'catatan_tambahan' => $catatan !== '' ? $catatan : null,
                'status' => SalaryAdjustmentWorkflowService::STATUS_SUBMITTED,
                'created_by' => $this->karyawan,
                'updated_by' => $this->karyawan,
                'is_active' => true,
            ]);

            $kpiService->store($record->id, [
                'items' => $kpiItems,
                'summary' => $request->input('kpi.summary', $request->input('kpi_summary')),
                'strengths' => $request->input('kpi.strengths', $request->input('kpi_strengths')),
                'improvements' => $request->input('kpi.improvements', $request->input('kpi_improvements')),
            ], $this->karyawan);

            SalaryAdjustmentLogService::log(
                $record->id,
                null,
                SalaryAdjustmentWorkflowService::STATUS_SUBMITTED,
                'create',
                $this->user_id,
                $this->karyawan,
                'Permohonan penyesuaian gaji dibuat'
            );

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan penyesuaian gaji berhasil dibuat',
                'data' => ['id' => $record->id, 'no_document' => $record->no_document],
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request)
    {
        $record = $this->findAccessibleRequest((int) $request->id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatDetail($record),
        ]);
    }

    private function indexByManagerTab(Request $request, string $tab)
    {
        $periode = $request->periode ?? date('Y');
        $statuses = SalaryAdjustmentWorkflowService::statusesForManagerTab($tab);

        $query = $this->listQuery($periode)->whereIn('sar.status', $statuses);

        return $this->applyDatatablesFilter(
            Datatables::of($query)
                ->addColumn('status_label', function ($row) {
                    return SalaryAdjustmentWorkflowService::statusLabel($row->status);
                })
                ->editColumn('adjustment_gaji_pokok', fn ($row) => $this->formatRupiah($row->adjustment_gaji_pokok))
                ->editColumn('adjustment_tunjangan', fn ($row) => $this->formatRupiah($row->adjustment_tunjangan))
        )->make(true);
    }

    private function applyDatatablesFilter($datatables)
    {
        return $datatables
            ->filterColumn('no_document', function ($query, $keyword) {
                $query->where('sar.no_document', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_lengkap', function ($query, $keyword) {
                $query->where('karyawan.nama_lengkap', 'like', "%{$keyword}%");
            })
            ->filterColumn('jabatan', function ($query, $keyword) {
                $query->where('sar.jabatan', 'like', "%{$keyword}%");
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
            ->filterColumn('status_label', function ($query, $keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('sar.status', 'like', "%{$keyword}%");
                    foreach (SalaryAdjustmentWorkflowService::STATUS_LABELS as $status => $label) {
                        if (stripos($label, $keyword) !== false) {
                            $sub->orWhere('sar.status', $status);
                        }
                    }
                });
            })
            ->filterColumn('nama_divisi', function ($query, $keyword) {
                $query->where('d.nama_divisi', 'like', "%{$keyword}%");
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('sar.created_by', 'like', "%{$keyword}%");
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('sar.created_at', 'like', "%{$keyword}%");
            })
            ->orderColumn('nama_lengkap', fn ($query, $order) => $query->orderBy('karyawan.nama_lengkap', $order))
            ->orderColumn('nama_divisi', fn ($query, $order) => $query->orderBy('d.nama_divisi', $order))
            ->orderColumn('status_label', fn ($query, $order) => $query->orderBy('sar.status', $order));
    }

    private function listQuery($periode)
    {
        return $this->baseManagerQuery($periode)
            ->select(
                'sar.id',
                'sar.no_document',
                'sar.employee_id',
                'sar.jabatan',
                'sar.adjustment_gaji_pokok',
                'sar.adjustment_tunjangan',
                'sar.requested_gaji_pokok',
                'sar.requested_tunjangan_kerja',
                'sar.bulan_efektif',
                'sar.status',
                'sar.created_by',
                'sar.created_at',
                'sar.processed_by',
                'sar.processed_at',
                'sar.rejected_by',
                'sar.rejected_at',
                'sar.reject_reason',
                'karyawan.nama_lengkap',
                'd.nama_divisi'
            )
            ->orderByDesc('sar.id');
    }

    private function baseManagerQuery($periode)
    {
        $query = DB::connection('mysql')
            ->table('salary_adjustment_requests as sar')
            ->leftJoin('master_karyawan as karyawan', 'sar.employee_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->where('sar.is_active', true)
            ->whereYear('sar.created_at', $periode);

        if (SalaryAdjustmentWorkflowService::isManagerGrade($this->grade)) {
            $bawahanIds = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();
            $query->where(function ($q) use ($bawahanIds) {
                $q->where('sar.requested_by_id', $this->user_id)
                    ->orWhereIn('sar.employee_id', $bawahanIds);
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function findAccessibleRequest(int $id): ?SalaryAdjustmentRequest
    {
        $record = SalaryAdjustmentRequest::with(['kpi.items', 'statusLogs'])->find($id);
        if (!$record || !$record->is_active) {
            return null;
        }

        if (!SalaryAdjustmentWorkflowService::isManagerGrade($this->grade)) {
            return null;
        }

        $allowedIds = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();
        if ((int) $record->requested_by_id !== (int) $this->user_id && !in_array((int) $record->employee_id, $allowedIds, true)) {
            return null;
        }

        return $record;
    }

    private function formatDetail(SalaryAdjustmentRequest $record): array
    {
        $employee = MasterKaryawan::with('jabatan')->find($record->employee_id);
        $namaJabatan = KaryawanProfileService::resolveJabatan($employee);

        return [
            'id' => $record->id,
            'no_document' => $record->no_document,
            'employee_id' => $record->employee_id,
            'nama_lengkap' => $employee->nama_lengkap ?? '-',
            'jabatan' => $namaJabatan !== '-' ? $namaJabatan : ($record->jabatan ?: '-'),
            'current_gaji_pokok' => (float) $record->current_gaji_pokok,
            'current_tunjangan_kerja' => (float) $record->current_tunjangan_kerja,
            'adjustment_gaji_pokok' => (float) ($record->adjustment_gaji_pokok ?? 0),
            'adjustment_tunjangan' => (float) ($record->adjustment_tunjangan ?? 0),
            'requested_gaji_pokok' => (float) $record->requested_gaji_pokok,
            'requested_tunjangan_kerja' => (float) $record->requested_tunjangan_kerja,
            ...SalaryAdjustmentEvaluationService::formatAdjustmentSnapshot($record),
            'bulan_efektif' => $record->bulan_efektif,
            'catatan_tambahan' => $record->catatan_tambahan,
            'status' => $record->status,
            'status_label' => SalaryAdjustmentWorkflowService::statusLabel($record->status),
            'reject_reason' => $record->reject_reason,
            'processed_by' => $record->processed_by,
            'processed_at' => $record->processed_at,
            'rejected_by' => $record->rejected_by,
            'rejected_at' => $record->rejected_at,
            'created_by' => $record->created_by,
            'created_at' => $record->created_at,
            'kpi' => optional($record->kpi)->load('items'),
            'logs' => SalaryAdjustmentWorkflowService::formatLogs($record->statusLogs),
        ];
    }

    private function isAllowedSubordinate(int $employeeId): bool
    {
        if ((int) $employeeId === (int) $this->user_id) {
            return false;
        }

        $allowedIds = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return in_array($employeeId, $allowedIds, true);
    }

    private function parseAmount($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $normalized = str_replace(['Rp', ' ', '.', ','], ['', '', '', '.'], (string) $value);

        return max(0, (float) $normalized);
    }

    private function formatRupiah($value): string
    {
        return 'Rp ' . number_format((float) ($value ?? 0), 0, ',', '.');
    }

    private function generateDocumentNumber(): string
    {
        $prefix = 'SAG-' . date('ymd') . '-';

        do {
            $noDocument = $prefix . strtoupper(bin2hex(random_bytes(4)));
        } while (SalaryAdjustmentRequest::where('no_document', $noDocument)->exists());

        return $noDocument;
    }
}

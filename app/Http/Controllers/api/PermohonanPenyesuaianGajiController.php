<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Models\MasterSallary;
use App\Models\SalaryAdjustmentRequest;
use App\Services\EmployeeAdjustmentMutasiService;
use App\Services\EmployeeAdjustmentRekapService;
use App\Services\EmployeeAdjustmentTypeRegistry;
use App\Services\EmployeeAdjustmentValidationService;
use App\Services\EmployeeAdjustmentWorkflowResolver;
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

    public function getRequestTypes()
    {
        return response()->json([
            'success' => true,
            'data' => EmployeeAdjustmentTypeRegistry::listForApi(),
        ]);
    }

    public function getJabatanList()
    {
        if (!SalaryAdjustmentWorkflowService::isManagerGrade($this->grade)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Manager yang dapat mengakses daftar jabatan',
            ], 403);
        }

        $jabatan = MasterJabatan::where('is_active', true)
            ->orderBy('nama_jabatan')
            ->get(['id', 'nama_jabatan']);

        return response()->json([
            'success' => true,
            'data' => $jabatan,
        ]);
    }

    public function getPeerManagers()
    {
        if (!SalaryAdjustmentWorkflowService::isManagerGrade($this->grade)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Manager yang dapat mengakses daftar manager penerima',
            ], 403);
        }

        $managers = MasterKaryawan::with('jabatan')
            ->where('is_active', true)
            ->where('id', '!=', (int) $this->user_id)
            ->whereIn(DB::raw("UPPER(REPLACE(COALESCE(grade, ''), '_', ' '))"), ['MANAGER', 'SENIOR MANAGER'])
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'nama_lengkap' => $row->nama_lengkap,
                    'grade' => $row->grade,
                    'jabatan' => KaryawanProfileService::resolveJabatan($row),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $managers,
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
                ->whereIn('sar.status', SalaryAdjustmentWorkflowService::statusesForManagerTab(
                    SalaryAdjustmentWorkflowService::MANAGER_TAB_WAITING
                ))
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
            SalaryAdjustmentWorkflowService::MANAGER_TAB_MUTASI_INBOX => $this->mutasiInboxCount($periode),
        ];

        return response()->json([
            'success' => true,
            'data' => $counts,
        ]);
    }

    public function getMutasiRespondOptions()
    {
        if (!SalaryAdjustmentWorkflowService::isManagerGrade($this->grade)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Manager yang dapat merespons mutasi',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'salary_decisions' => EmployeeAdjustmentMutasiService::salaryDecisionOptions(),
            ],
        ]);
    }

    public function indexMutasiInbox(Request $request)
    {
        if (!SalaryAdjustmentWorkflowService::isManagerGrade($this->grade)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $periode = $request->periode ?? date('Y');

        $query = DB::connection('mysql')
            ->table('salary_adjustment_requests as sar')
            ->leftJoin('master_karyawan as karyawan', 'sar.employee_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('master_karyawan as sender', 'sar.requested_by_id', '=', 'sender.id')
            ->where('sar.is_active', true)
            ->whereYear('sar.created_at', $periode)
            ->where('sar.request_type', EmployeeAdjustmentTypeRegistry::TYPE_MUTASI)
            ->where('sar.receiver_manager_id', (int) $this->user_id)
            ->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_WAITING_RECEIVER)
            ->select(
                'sar.id',
                'sar.no_document',
                'sar.request_type',
                'sar.employee_id',
                'sar.jabatan',
                'sar.status',
                'sar.created_at',
                'sar.catatan_tambahan',
                'karyawan.nama_lengkap',
                'd.nama_divisi',
                'sender.nama_lengkap as manager_pengaju'
            )
            ->orderByDesc('sar.id');

        return $this->applyDatatablesFilter(
            $this->decorateRequestTypeColumn(
                Datatables::of($query)
                    ->addColumn('status_label', function ($row) {
                        return SalaryAdjustmentWorkflowService::statusLabel($row->status);
                    })
                    ->addColumn('can_respond', fn () => true)
            )
        )->make(true);
    }

    public function respondMutasi(Request $request)
    {
        if (!SalaryAdjustmentWorkflowService::isManagerGrade($this->grade)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $mutasiService = new EmployeeAdjustmentMutasiService();
        if (!$mutasiService->canRespond($record, (int) $this->user_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan mutasi tidak dapat direspons pada status ini',
            ], 400);
        }

        $validation = $mutasiService->validateRespondRequest($request, $record);
        if ($validation['error']) {
            return response()->json(['success' => false, 'message' => $validation['error']], 400);
        }

        $payload = $validation['data'];
        $from = $record->status;

        DB::connection('mysql')->beginTransaction();
        try {
            if ($payload['decision'] === EmployeeAdjustmentMutasiService::DECISION_REJECT) {
                $record->status = SalaryAdjustmentWorkflowService::STATUS_REJECTED;
                $record->rejected_stage = $from;
                $record->reject_reason = $payload['reject_reason'];
                $record->rejected_by = $this->karyawan;
                $record->rejected_at = Carbon::now();
                $record->receiver_responded_at = Carbon::now();
                $record->updated_by = $this->karyawan;
                $record->save();

                SalaryAdjustmentLogService::log(
                    $record->id,
                    $from,
                    $record->status,
                    'receiver_reject',
                    $this->user_id,
                    $this->karyawan,
                    $payload['reject_reason']
                );

                DB::connection('mysql')->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Permohonan mutasi ditolak',
                ]);
            }

            $mutasiService->applyAcceptance($record, $payload);
            $record->updated_by = $this->karyawan;
            $record->save();

            $logNotes = $payload['notes'] ?? 'Manager penerima menerima mutasi';
            if ($payload['has_salary_adjustment']) {
                $logNotes .= ' — dengan penyesuaian gaji/tunjangan';
            } else {
                $logNotes .= ' — gaji/tunjangan tetap';
            }

            SalaryAdjustmentLogService::log(
                $record->id,
                $from,
                $record->status,
                'receiver_accept',
                $this->user_id,
                $this->karyawan,
                $logNotes,
                [
                    'receiver_salary_decision' => $payload['receiver_salary_decision'],
                    'has_salary_adjustment' => $payload['has_salary_adjustment'],
                ]
            );

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Mutasi diterima. Permohonan diteruskan ke HRD untuk proses selanjutnya.',
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
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
                'message' => 'Hanya Manager yang dapat membuat permohonan penyesuaian karyawan',
            ], 403);
        }

        $validation = (new EmployeeAdjustmentValidationService())->validateStoreRequest($request, (int) $this->user_id);
        if ($validation['error']) {
            return response()->json(['success' => false, 'message' => $validation['error']], 400);
        }

        $data = $validation['data'];
        /** @var MasterKaryawan $employee */
        $employee = $data['employee'];

        if (!$this->isAllowedSubordinate((int) $employee->id)) {
            return response()->json(['success' => false, 'message' => 'Karyawan bukan bawahan Anda'], 403);
        }

        if ($data['request_type'] === EmployeeAdjustmentTypeRegistry::TYPE_MUTASI) {
            $receiverError = $this->validateMutasiReceiver((int) $data['receiver_manager_id']);
            if ($receiverError) {
                return response()->json(['success' => false, 'message' => $receiverError], 400);
            }
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

        $adjustmentGaji = (float) ($data['adjustment_gaji_pokok'] ?? 0);
        $adjustmentTunj = (float) ($data['adjustment_tunjangan'] ?? 0);
        $requestedGaji = $currentGaji + $adjustmentGaji;
        $requestedTunj = $currentTunjangan + $adjustmentTunj;

        $initialStatus = $data['request_type'] === EmployeeAdjustmentTypeRegistry::TYPE_MUTASI
            ? SalaryAdjustmentWorkflowService::STATUS_WAITING_RECEIVER
            : SalaryAdjustmentWorkflowService::STATUS_SUBMITTED;

        DB::connection('mysql')->beginTransaction();
        try {
            $record = SalaryAdjustmentRequest::create([
                'no_document' => $this->generateDocumentNumber($data['request_type']),
                'request_type' => $data['request_type'],
                'workflow_profile' => $data['workflow_profile'],
                'employee_id' => $employee->id,
                'requested_by_id' => $this->user_id,
                'receiver_manager_id' => $data['receiver_manager_id'],
                'jabatan' => $namaJabatan,
                'new_jabatan_id' => $data['new_jabatan_id'],
                'current_gaji_pokok' => $currentGaji,
                'current_tunjangan_kerja' => $currentTunjangan,
                'adjustment_gaji_pokok' => $data['adjustment_gaji_pokok'],
                'adjustment_tunjangan' => $data['adjustment_tunjangan'],
                'requested_gaji_pokok' => $requestedGaji,
                'requested_tunjangan_kerja' => $requestedTunj,
                'submitted_adjustment_gaji_pokok' => $data['adjustment_gaji_pokok'],
                'submitted_adjustment_tunjangan' => $data['adjustment_tunjangan'],
                'submitted_requested_gaji_pokok' => $requestedGaji,
                'submitted_requested_tunjangan_kerja' => $requestedTunj,
                'has_salary_adjustment' => $data['has_salary_adjustment'],
                'bulan_efektif' => $data['bulan_efektif'],
                'tanggal_efektif' => $data['tanggal_efektif'],
                'tanggal_mulai' => $data['tanggal_mulai'],
                'tanggal_selesai' => $data['tanggal_selesai'],
                'tanggal_berakhir_kerja' => $data['tanggal_berakhir_kerja'],
                'scheduled_apply_at' => $data['scheduled_apply_at'],
                'apply_status' => EmployeeAdjustmentWorkflowResolver::APPLY_STATUS_PENDING,
                'catatan_tambahan' => $data['catatan_tambahan'],
                'status' => $initialStatus,
                'created_by' => $this->karyawan,
                'updated_by' => $this->karyawan,
                'is_active' => true,
            ]);

            $kpiService = new SalaryAdjustmentKpiService();
            if (EmployeeAdjustmentTypeRegistry::kpiRequired($data['request_type'])
                || !empty($data['kpi']['items'])
                || trim((string) ($data['kpi']['summary'] ?? '')) !== '') {
                $kpiService->store($record->id, $data['kpi'], $this->karyawan);
            }

            SalaryAdjustmentLogService::log(
                $record->id,
                null,
                $initialStatus,
                'create',
                $this->user_id,
                $this->karyawan,
                'Permohonan ' . EmployeeAdjustmentTypeRegistry::label($data['request_type']) . ' dibuat'
            );

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan ' . EmployeeAdjustmentTypeRegistry::label($data['request_type']) . ' berhasil dibuat',
                'data' => [
                    'id' => $record->id,
                    'no_document' => $record->no_document,
                    'request_type' => $record->request_type,
                ],
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

        $detail = $this->formatDetail($record);
        $mutasiService = new EmployeeAdjustmentMutasiService();
        $detail['can_respond_mutasi'] = $mutasiService->canRespond($record, (int) $this->user_id);

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }

    private function indexByManagerTab(Request $request, string $tab)
    {
        $periode = $request->periode ?? date('Y');
        $statuses = SalaryAdjustmentWorkflowService::statusesForManagerTab($tab);

        $query = $this->listQuery($periode)->whereIn('sar.status', $statuses);

        return $this->applyDatatablesFilter(
            $this->decorateRequestTypeColumn(
                Datatables::of($query)
                    ->addColumn('status_label', function ($row) {
                        return SalaryAdjustmentWorkflowService::statusLabel($row->status);
                    })
                    ->editColumn('adjustment_gaji_pokok', fn ($row) => $this->formatRupiah($row->adjustment_gaji_pokok))
                    ->editColumn('adjustment_tunjangan', fn ($row) => $this->formatRupiah($row->adjustment_tunjangan))
            )
        )->make(true);
    }

    private function applyDatatablesFilter($datatables)
    {
        return $datatables
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
            ->filterColumn('manager_pengaju', function ($query, $keyword) {
                $query->where('sender.nama_lengkap', 'like', "%{$keyword}%");
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
                'sar.request_type',
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
        $record = SalaryAdjustmentRequest::with(['kpi.items', 'statusLogs', 'newJabatan', 'receiverManager', 'rekap'])->find($id);
        if (!$record || !$record->is_active) {
            return null;
        }

        if (!SalaryAdjustmentWorkflowService::isManagerGrade($this->grade)) {
            return null;
        }

        $mutasiService = new EmployeeAdjustmentMutasiService();
        if ($mutasiService->isReceiver($record, (int) $this->user_id)) {
            return $record;
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
        $senderManager = MasterKaryawan::find($record->requested_by_id);

        $newJabatan = $record->newJabatan;
        $mutasiService = new EmployeeAdjustmentMutasiService();
        $rekapService = new EmployeeAdjustmentRekapService();

        return array_merge([
            'id' => $record->id,
            'no_document' => $record->no_document,
            'request_type' => $record->request_type ?: EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI,
            'request_type_label' => EmployeeAdjustmentTypeRegistry::label($record->request_type),
            'workflow_profile' => $record->workflow_profile,
            'employee_id' => $record->employee_id,
            'nama_lengkap' => $employee->nama_lengkap ?? '-',
            'jabatan' => $namaJabatan !== '-' ? $namaJabatan : ($record->jabatan ?: '-'),
            'current_gaji_pokok' => (float) $record->current_gaji_pokok,
            'current_tunjangan_kerja' => (float) $record->current_tunjangan_kerja,
            'adjustment_gaji_pokok' => (float) ($record->adjustment_gaji_pokok ?? 0),
            'adjustment_tunjangan' => (float) ($record->adjustment_tunjangan ?? 0),
            'requested_gaji_pokok' => (float) $record->requested_gaji_pokok,
            'requested_tunjangan_kerja' => (float) $record->requested_tunjangan_kerja,
            'bulan_efektif' => $record->bulan_efektif,
            'tanggal_efektif' => $record->tanggal_efektif,
            'tanggal_mulai' => $record->tanggal_mulai,
            'tanggal_selesai' => $record->tanggal_selesai,
            'tanggal_berakhir_kerja' => $record->tanggal_berakhir_kerja,
            'new_jabatan_id' => $record->new_jabatan_id,
            'new_jabatan_nama' => $newJabatan->nama_jabatan ?? null,
            'has_salary_adjustment' => (bool) $record->has_salary_adjustment,
            'manager_nama' => $senderManager->nama_lengkap ?? $record->created_by,
            'receiver_manager_id' => $record->receiver_manager_id,
            'receiver_manager_nama' => optional($record->receiverManager)->nama_lengkap,
            'scheduled_apply_at' => $record->scheduled_apply_at,
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
            'kpi' => $record->kpi,
            'logs' => SalaryAdjustmentWorkflowService::formatLogs($record->statusLogs),
        ],
            SalaryAdjustmentEvaluationService::formatAdjustmentSnapshot($record),
            $mutasiService->formatReceiverFields($record),
            $rekapService->formatForDetail($record)
        );
    }

    private function mutasiInboxCount($periode): int
    {
        if (!SalaryAdjustmentWorkflowService::isManagerGrade($this->grade)) {
            return 0;
        }

        return DB::connection('mysql')
            ->table('salary_adjustment_requests')
            ->where('is_active', true)
            ->whereYear('created_at', $periode)
            ->where('request_type', EmployeeAdjustmentTypeRegistry::TYPE_MUTASI)
            ->where('receiver_manager_id', (int) $this->user_id)
            ->where('status', SalaryAdjustmentWorkflowService::STATUS_WAITING_RECEIVER)
            ->count();
    }

    private function isAllowedSubordinate(int $employeeId): bool
    {
        if ((int) $employeeId === (int) $this->user_id) {
            return false;
        }

        $allowedIds = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        return in_array($employeeId, $allowedIds, true);
    }

    private function decorateRequestTypeColumn($datatables)
    {
        return $datatables->addColumn('request_type_label', function ($row) {
            return EmployeeAdjustmentTypeRegistry::label($row->request_type ?? EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI);
        });
    }

    private function validateMutasiReceiver(int $receiverId): ?string
    {
        if ($receiverId <= 0) {
            return 'Manager penerima mutasi wajib dipilih';
        }

        if ($receiverId === (int) $this->user_id) {
            return 'Manager penerima mutasi tidak boleh diri sendiri';
        }

        $receiver = MasterKaryawan::where('id', $receiverId)->where('is_active', true)->first();
        if (!$receiver) {
            return 'Manager penerima mutasi tidak ditemukan';
        }

        if (!SalaryAdjustmentWorkflowService::isManagerGrade($receiver->grade)) {
            return 'Manager penerima mutasi harus bergrade Manager atau Senior Manager';
        }

        return null;
    }

    private function formatRupiah($value): string
    {
        return 'Rp ' . number_format((float) ($value ?? 0), 0, ',', '.');
    }

    private function generateDocumentNumber(?string $requestType = null): string
    {
        $prefix = EmployeeAdjustmentTypeRegistry::documentPrefix($requestType) . '-' . date('ymd') . '-';

        do {
            $noDocument = $prefix . strtoupper(bin2hex(random_bytes(4)));
        } while (SalaryAdjustmentRequest::where('no_document', $noDocument)->exists());

        return $noDocument;
    }
}

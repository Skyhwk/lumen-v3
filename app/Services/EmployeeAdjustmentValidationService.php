<?php

namespace App\Services;

use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use Illuminate\Http\Request;

class EmployeeAdjustmentValidationService
{
    public static function validateBulanEfektif(?string $bulanEfektif, bool $required = true): ?string
    {
        $bulanEfektif = trim((string) $bulanEfektif);

        if ($required && !preg_match('/^\d{4}-\d{2}$/', $bulanEfektif)) {
            return 'Periode mulai berlaku wajib diisi (format YYYY-MM)';
        }

        if ($bulanEfektif !== '' && preg_match('/^\d{4}-\d{2}$/', $bulanEfektif) && $bulanEfektif < date('Y-m')) {
            return 'Mulai berlaku tidak boleh periode yang sudah lampau';
        }

        return null;
    }

    /** @var SalaryAdjustmentKpiService */
    private $kpiService;

    /** @var EmployeeAdjustmentWorkflowResolver */
    private $workflowResolver;

    public function __construct(
        SalaryAdjustmentKpiService $kpiService = null,
        EmployeeAdjustmentWorkflowResolver $workflowResolver = null
    ) {
        $this->kpiService = $kpiService ?: new SalaryAdjustmentKpiService();
        $this->workflowResolver = $workflowResolver ?: new EmployeeAdjustmentWorkflowResolver();
    }

    /**
     * @return array{error: ?string, data: array<string, mixed>}
     */
    public function validateStoreRequest(Request $request, int $requesterId): array
    {
        $requestType = EmployeeAdjustmentTypeRegistry::normalizeType($request->input('request_type'));

        if (!EmployeeAdjustmentTypeRegistry::isValid($requestType)) {
            return ['error' => 'Jenis permohonan tidak valid', 'data' => []];
        }

        $employeeId = (int) $request->employee_id;
        if (!$employeeId) {
            return ['error' => 'Nama bawahan wajib dipilih', 'data' => []];
        }

        if ($employeeId === $requesterId) {
            return ['error' => 'Manager tidak dapat mengajukan untuk diri sendiri', 'data' => []];
        }

        $employee = MasterKaryawan::with('jabatan')->where('id', $employeeId)->where('is_active', true)->first();
        if (!$employee) {
            return ['error' => 'Data karyawan tidak ditemukan', 'data' => []];
        }

        $definition = EmployeeAdjustmentTypeRegistry::get($requestType);
        $adjustmentGaji = $this->parseSignedAmount($request->adjustment_gaji_pokok, $definition['allows_negative_salary']);
        $adjustmentTunj = $this->parseSignedAmount($request->adjustment_tunjangan, $definition['allows_negative_salary']);
        $hasSalaryAdjustment = $this->resolveHasSalaryAdjustment(
            $requestType,
            $request,
            $adjustmentGaji,
            $adjustmentTunj
        );

        $typeError = $this->validateTypeSpecificFields($request, $requestType, $hasSalaryAdjustment, $adjustmentGaji, $adjustmentTunj);
        if ($typeError) {
            return ['error' => $typeError, 'data' => []];
        }

        $kpiItems = $request->input('kpi.items', $request->input('kpi_items', []));
        $kpiSummary = $request->input('kpi.summary', $request->input('kpi_summary'));
        $kpiStrengths = $request->input('kpi.strengths', $request->input('kpi_strengths'));
        $kpiImprovements = $request->input('kpi.improvements', $request->input('kpi_improvements'));

        if (EmployeeAdjustmentTypeRegistry::kpiRequired($requestType)) {
            $kpiError = $this->kpiService->validatePayload(
                is_array($kpiItems) ? $kpiItems : [],
                $kpiSummary,
                $kpiStrengths,
                $kpiImprovements
            );
            if ($kpiError) {
                return ['error' => $kpiError, 'data' => []];
            }
        } elseif ($this->hasAnyKpiInput($kpiItems, $kpiSummary, $kpiStrengths, $kpiImprovements)) {
            $kpiError = $this->kpiService->validatePayload(
                is_array($kpiItems) ? $kpiItems : [],
                $kpiSummary,
                $kpiStrengths,
                $kpiImprovements
            );
            if ($kpiError) {
                return ['error' => $kpiError, 'data' => []];
            }
        }

        $bulanEfektif = trim((string) ($request->bulan_efektif ?? ''));
        if ($hasSalaryAdjustment) {
            $bulanError = self::validateBulanEfektif($bulanEfektif, true);
            if ($bulanError) {
                return ['error' => $bulanError, 'data' => []];
            }
        } else {
            $bulanEfektif = $bulanEfektif !== '' ? $bulanEfektif : null;
            if ($bulanEfektif !== null) {
                $bulanError = self::validateBulanEfektif($bulanEfektif, true);
                if ($bulanError) {
                    return ['error' => $bulanError, 'data' => []];
                }
            }
        }

        $tanggalEfektif = $this->normalizeDate($request->input('tanggal_efektif'));
        $tanggalMulai = $this->normalizeDate($request->input('tanggal_mulai'));
        $tanggalSelesai = $this->normalizeDate($request->input('tanggal_selesai'));
        $tanggalBerakhirKerja = $this->normalizeDate($request->input('tanggal_berakhir_kerja'));
        $newJabatanId = (int) $request->input('new_jabatan_id');
        $receiverManagerId = (int) $request->input('receiver_manager_id');

        $payloadForSchedule = [
            'request_type' => $requestType,
            'tanggal_efektif' => $tanggalEfektif,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_berakhir_kerja' => $tanggalBerakhirKerja,
            'bulan_efektif' => $bulanEfektif,
        ];

        $scheduledApplyAt = $this->workflowResolver->resolveScheduledApplyDate($payloadForSchedule, $requestType);

        return [
            'error' => null,
            'data' => [
                'request_type' => $requestType,
                'workflow_profile' => EmployeeAdjustmentTypeRegistry::workflowProfile($requestType),
                'employee' => $employee,
                'adjustment_gaji_pokok' => $hasSalaryAdjustment && $adjustmentGaji !== 0.0 ? $adjustmentGaji : null,
                'adjustment_tunjangan' => $hasSalaryAdjustment && $adjustmentTunj !== 0.0 ? $adjustmentTunj : null,
                'has_salary_adjustment' => $hasSalaryAdjustment,
                'bulan_efektif' => $hasSalaryAdjustment ? $bulanEfektif : ($bulanEfektif ?: date('Y-m')),
                'tanggal_efektif' => $tanggalEfektif,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'tanggal_berakhir_kerja' => $tanggalBerakhirKerja,
                'new_jabatan_id' => $newJabatanId > 0 ? $newJabatanId : null,
                'receiver_manager_id' => $receiverManagerId > 0 ? $receiverManagerId : null,
                'scheduled_apply_at' => $scheduledApplyAt ? $scheduledApplyAt->toDateString() : null,
                'catatan_tambahan' => trim((string) ($request->catatan_tambahan ?? '')) ?: null,
                'kpi' => [
                    'items' => is_array($kpiItems) ? $kpiItems : [],
                    'summary' => $kpiSummary,
                    'strengths' => $kpiStrengths,
                    'improvements' => $kpiImprovements,
                ],
            ],
        ];
    }

    private function validateTypeSpecificFields(
        Request $request,
        string $requestType,
        bool $hasSalaryAdjustment,
        float $adjustmentGaji,
        float $adjustmentTunj
    ): ?string {
        switch ($requestType) {
            case EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI:
                if (!$hasSalaryAdjustment) {
                    return 'Minimal salah satu penyesuaian gaji pokok atau tunjangan harus diisi';
                }
                if ($adjustmentGaji < 0 || $adjustmentTunj < 0) {
                    return 'Penyesuaian gaji tidak boleh bernilai negatif';
                }
                return null;

            case EmployeeAdjustmentTypeRegistry::TYPE_PROMOSI:
            case EmployeeAdjustmentTypeRegistry::TYPE_DEMOSI:
                if (!(int) $request->input('new_jabatan_id')) {
                    return 'Jabatan baru wajib dipilih';
                }
                if (!$this->jabatanExists((int) $request->input('new_jabatan_id'))) {
                    return 'Jabatan baru tidak ditemukan';
                }
                if (!$this->normalizeDate($request->input('tanggal_efektif'))) {
                    return 'Tanggal efektif promosi/demosi wajib diisi';
                }
                if ($hasSalaryAdjustment && $adjustmentGaji === 0.0 && $adjustmentTunj === 0.0) {
                    return 'Minimal salah satu penyesuaian gaji pokok atau tunjangan harus diisi';
                }
                return null;

            case EmployeeAdjustmentTypeRegistry::TYPE_PENGANGKATAN_TETAP:
            case EmployeeAdjustmentTypeRegistry::TYPE_PENGANGKATAN_KONTRAK:
                if (!$this->normalizeDate($request->input('tanggal_efektif'))) {
                    return 'Tanggal efektif pengangkatan wajib diisi';
                }
                if ($requestType === EmployeeAdjustmentTypeRegistry::TYPE_PENGANGKATAN_KONTRAK
                    && !$this->normalizeDate($request->input('tanggal_selesai'))) {
                    return 'Tanggal berakhir kontrak wajib diisi';
                }
                if ($hasSalaryAdjustment && $adjustmentGaji === 0.0 && $adjustmentTunj === 0.0) {
                    return 'Minimal salah satu penyesuaian gaji pokok atau tunjangan harus diisi';
                }
                return null;

            case EmployeeAdjustmentTypeRegistry::TYPE_PERPANJANG_KONTRAK:
            case EmployeeAdjustmentTypeRegistry::TYPE_PERPANJANG_PELATIHAN:
                if (!$this->normalizeDate($request->input('tanggal_mulai'))) {
                    return 'Tanggal efektif mulai perpanjangan wajib diisi';
                }
                if (!$this->normalizeDate($request->input('tanggal_selesai'))) {
                    return 'Tanggal efektif selesai perpanjangan wajib diisi';
                }
                if ($hasSalaryAdjustment && $adjustmentGaji === 0.0 && $adjustmentTunj === 0.0) {
                    return 'Minimal salah satu penyesuaian gaji pokok atau tunjangan harus diisi';
                }
                return null;

            case EmployeeAdjustmentTypeRegistry::TYPE_GAGAL_PELATIHAN:
            case EmployeeAdjustmentTypeRegistry::TYPE_PHK:
                if (!$this->normalizeDate($request->input('tanggal_berakhir_kerja'))) {
                    return 'Tanggal efektif berakhirnya masa kerja wajib diisi';
                }
                return null;

            case EmployeeAdjustmentTypeRegistry::TYPE_PENSIUN:
                if (!$this->normalizeDate($request->input('tanggal_efektif'))) {
                    return 'Tanggal efektif pensiun wajib diisi';
                }
                return null;

            case EmployeeAdjustmentTypeRegistry::TYPE_MUTASI:
                if (!(int) $request->input('new_jabatan_id')) {
                    return 'Posisi baru wajib dipilih';
                }
                if (!$this->jabatanExists((int) $request->input('new_jabatan_id'))) {
                    return 'Posisi baru tidak ditemukan';
                }
                $receiverId = (int) $request->input('receiver_manager_id');
                if (!$receiverId) {
                    return 'Manager penerima mutasi wajib dipilih';
                }
                return null;

            default:
                return 'Jenis permohonan belum didukung';
        }
    }

    private function resolveHasSalaryAdjustment(
        string $requestType,
        Request $request,
        float $adjustmentGaji,
        float $adjustmentTunj
    ): bool {
        if (EmployeeAdjustmentTypeRegistry::salaryRequired($requestType)) {
            return true;
        }

        if ($request->has('has_salary_adjustment')) {
            return filter_var($request->input('has_salary_adjustment'), FILTER_VALIDATE_BOOLEAN);
        }

        return abs($adjustmentGaji) > 0 || abs($adjustmentTunj) > 0;
    }

    private function parseSignedAmount($value, bool $allowNegative): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $normalized = str_replace(['Rp', ' ', '.'], ['', '', ''], (string) $value);
        $normalized = str_replace(',', '.', $normalized);
        $amount = (float) $normalized;

        if (!$allowNegative) {
            return max(0.0, $amount);
        }

        return $amount;
    }

    private function normalizeDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function jabatanExists(int $jabatanId): bool
    {
        return MasterJabatan::where('id', $jabatanId)->where('is_active', true)->exists();
    }

    private function hasAnyKpiInput($items, $summary, $strengths, $improvements): bool
    {
        if (is_array($items) && count($items) > 0) {
            return true;
        }

        return trim((string) $summary) !== ''
            || trim((string) $strengths) !== ''
            || trim((string) $improvements) !== '';
    }
}
